<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Enums\RejectionReason;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeadFeedbackResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Services\AnalyticsService;
use App\Services\LeadWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The agent's leads (BACKEND_GUIDE §6). Every query is scoped to
 * `assigned_to = auth id` — a client-supplied id is never trusted.
 */
class LeadController extends Controller
{
    public function __construct(private LeadWorkflow $workflow) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['new', 'in_progress', 'converted', 'rejected'])],
            'intent' => ['nullable', Rule::in(['buying', 'selling'])],
            'q' => ['nullable', 'string', 'max:190'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'price_high', 'price_low', 'name_az'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $leads = Lead::query()
            ->assignedTo($request->user()->id)
            ->when($request->filled('status'), fn ($q) => $q->status($request->string('status')->toString()))
            ->when($request->filled('intent'), fn ($q) => $q->where('intent', $request->string('intent')->toString()))
            ->search($request->input('q'))
            ->sorted($request->input('sort'))
            ->paginate($request->integer('per_page') ?: 15);

        return response()->json([
            'data' => LeadResource::collection($leads->items()),
            'meta' => [
                'page' => $leads->currentPage(),
                'per_page' => $leads->perPage(),
                'total' => $leads->total(),
                'last_page' => $leads->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $lead): JsonResponse
    {
        $model = Lead::query()
            ->assignedTo($request->user()->id)
            ->with('feedback')
            ->findOrFail($lead);

        return response()->json(['data' => new LeadResource($model)]);
    }

    /** Dashboard cards: { total, new, in_progress, converted, rejected }. */
    public function stats(Request $request, AnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->statusCounts($request->user()->id)]);
    }

    /** Weekly activity chart + pipeline breakdown. */
    public function analytics(Request $request, AnalyticsService $analytics): JsonResponse
    {
        $request->validate([
            'range' => ['nullable', Rule::in(['week', 'fortnight', 'month', 'quarter', 'year'])],
        ]);

        $agentId = $request->user()->id;
        $range = $request->input('range', 'week');

        return response()->json([
            'range' => $range,
            'series' => $analytics->leadsPerDay($range, $agentId),
            'conversions' => $analytics->conversionsPerDay($range, $agentId),
            'pipeline' => $analytics->statusCounts($agentId),
            'conversion_rate' => $analytics->conversionRate($agentId),
            'acceptance_rate' => $analytics->acceptanceRate($agentId),
            'avg_response_hours' => $analytics->avgResponseHours($agentId),
        ]);
    }

    /**
     * Move an already-accepted lead through the pipeline.
     *
     * The initial accept/reject of a `new` lead goes through /respond; this
     * handles everything after that (in_progress → converted, or a late
     * rejection). Staff and admin are notified of the change.
     */
    public function updateStatus(Request $request, int $lead): JsonResponse
    {
        $agent = $request->user();

        // 404 when the lead does not exist at all, 403 when it belongs to
        // someone else — the contract the mobile app expects.
        $model = Lead::findOrFail($lead);

        if ($model->assigned_to !== $agent->id) {
            return response()->json([
                'code' => 'lead_not_assigned',
                'message' => 'This lead is not assigned to you.',
            ], 403);
        }

        if ($model->status === LeadStatus::NEW) {
            return response()->json([
                'message' => 'Accept the lead before changing its status.',
                'errors' => ['status' => ['Accept the lead before changing its status.']],
            ], 422);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'converted', 'rejected'])],
            'reason' => ['nullable', Rule::in(array_keys(RejectionReason::options()))],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workflow->agentChangeStatus(
            $model,
            $agent,
            LeadStatus::from($data['status']),
            $data['reason'] ?? null,
            $data['comments'] ?? null,
        );

        // Same shape as GET /leads/{id} — the app re-parses it with one model.
        return response()->json(['data' => new LeadResource($model->refresh()->load('feedback'))]);
    }

    /** Accept or reject with feedback — fires events #4/#5/#6. */
    public function respond(Request $request, int $lead): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'reject'])],
            'reason' => [
                'nullable',
                'required_if:decision,reject',
                Rule::in(array_keys(RejectionReason::options())),
            ],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $agent = $request->user();

        $model = Lead::query()->assignedTo($agent->id)->findOrFail($lead);

        $feedback = $this->workflow->respond(
            $model,
            $agent,
            $data['decision'],
            $data['reason'] ?? null,
            $data['comments'] ?? null,
        );

        return response()->json([
            'message' => $data['decision'] === 'accept'
                ? 'Lead accepted — it is now in progress.'
                : 'Lead rejected. Our team has been notified.',
            'data' => new LeadResource($model->refresh()->load('feedback')),
            'feedback' => new LeadFeedbackResource($feedback),
        ]);
    }
}

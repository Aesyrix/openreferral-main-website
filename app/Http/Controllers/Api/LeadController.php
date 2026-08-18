<?php

namespace App\Http\Controllers\Api;

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

    /** Accept or reject with feedback — fires events #4/#5/#6. */
    public function respond(Request $request, int $lead): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'reject'])],
            'reason' => [
                'nullable',
                'required_if:decision,reject',
                Rule::in(array_keys(\App\Enums\RejectionReason::options())),
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\AnalyticsService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Revenue — admin only (BACKEND_GUIDE §8). */
class RevenueController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        $this->authorize('view-revenue');

        $request->validate([
            'range' => ['nullable', Rule::in(['week', 'fortnight', 'month', 'quarter', 'year'])],
            'status' => ['nullable', Rule::in(['pending', 'completed', 'refunded', 'failed'])],
            'agent_id' => ['nullable', 'integer'],
        ]);

        $range = $request->input('range', 'month');

        return view('admin.revenue.index', [
            'range' => $range,
            'totalCents' => $this->analytics->totalRevenueCents(),
            'monthCents' => $this->analytics->revenueThisMonthCents(),
            'series' => $this->analytics->revenuePerDay($range),
            'byType' => $this->analytics->revenueByType(),
            'topAgents' => $this->analytics->topEarningAgents(),
            'transactions' => Transaction::with(['agent', 'lead'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->filled('agent_id'), fn ($q) => $q->where('agent_id', $request->integer('agent_id')))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function series(Request $request): JsonResponse
    {
        $this->authorize('view-revenue');

        $range = $request->input('range', 'month');

        return response()->json([
            'range' => $range,
            'revenue' => $this->analytics->revenuePerDay($range),
            'total_cents' => $this->analytics->totalRevenueCents(),
            'by_type' => $this->analytics->revenueByType(),
        ]);
    }

    /** Manual adjustment (credit or refund) against an agent. */
    public function storeAdjustment(Request $request): RedirectResponse
    {
        $this->authorize('view-revenue');

        $data = $request->validate([
            'agent_id' => ['required', 'integer', 'exists:users,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'amount_dollars' => ['required', 'numeric', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'type' => ['required', Rule::in(['lead_fee', 'subscription', 'adjustment'])],
            'status' => ['required', Rule::in(['pending', 'completed', 'refunded', 'failed'])],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $transaction = Transaction::create([
            'agent_id' => $data['agent_id'],
            'lead_id' => $data['lead_id'] ?? null,
            'type' => $data['type'],
            'amount_cents' => (int) round(((float) $data['amount_dollars']) * 100),
            'currency' => 'USD',
            'status' => $data['status'],
            'reference' => $data['reference'] ?? null,
            'created_at' => now(),
        ]);

        ActivityLogger::log('revenue.transaction_created', null, 'transaction', $transaction->id, [
            'amount_cents' => $transaction->amount_cents,
            'type' => $data['type'],
        ]);

        return back()->with('success', 'Transaction recorded.');
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $this->authorize('view-revenue');

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'completed', 'refunded', 'failed'])],
        ]);

        $transaction = Transaction::findOrFail($id);
        $transaction->update(['status' => $data['status']]);

        ActivityLogger::log('revenue.transaction_updated', null, 'transaction', $transaction->id, [
            'status' => $data['status'],
        ]);

        return back()->with('success', "Transaction marked {$data['status']}.");
    }
}

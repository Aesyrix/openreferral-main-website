<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\AnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Leads analytics for staff + admin (BACKEND_GUIDE §8). */
class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lead::class);

        $request->validate([
            'range' => ['nullable', Rule::in(['week', 'fortnight', 'month', 'quarter', 'year'])],
        ]);

        $range = $request->input('range', 'month');

        return view('admin.analytics.index', [
            'range' => $range,
            'counts' => $this->analytics->statusCounts(),
            'conversionRate' => $this->analytics->conversionRate(),
            'acceptanceRate' => $this->analytics->acceptanceRate(),
            'avgResponseHours' => $this->analytics->avgResponseHours(),
            'trend' => $this->analytics->leadsPerDay($range),
            'conversions' => $this->analytics->conversionsPerDay($range),
            'rejectionReasons' => $this->analytics->rejectionReasons(),
            'scorecard' => $this->analytics->agentScorecard(),
        ]);
    }

    /** JSON feed for the dashboard charts. */
    public function series(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $request->validate([
            'range' => ['nullable', Rule::in(['week', 'fortnight', 'month', 'quarter', 'year'])],
        ]);

        $range = $request->input('range', 'month');

        return response()->json([
            'range' => $range,
            'leads' => $this->analytics->leadsPerDay($range),
            'conversions' => $this->analytics->conversionsPerDay($range),
            'pipeline' => $this->analytics->statusCounts(),
            'rejection_reasons' => $this->analytics->rejectionReasons(),
        ]);
    }
}

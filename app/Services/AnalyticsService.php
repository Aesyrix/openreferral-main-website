<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\RejectionReason;
use App\Models\Lead;
use App\Models\LeadFeedback;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Everything in BACKEND_GUIDE §8, derived from leads / lead_feedback /
 * lead_status_history / transactions — no extra storage.
 */
class AnalyticsService
{
    /** @return array{total:int,new:int,in_progress:int,converted:int,rejected:int} */
    public function statusCounts(?int $agentId = null): array
    {
        $rows = Lead::query()
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId))
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->pluck('aggregate', 'status');

        $counts = [
            'new' => (int) ($rows[LeadStatus::NEW->value] ?? 0),
            'in_progress' => (int) ($rows[LeadStatus::IN_PROGRESS->value] ?? 0),
            'converted' => (int) ($rows[LeadStatus::CONVERTED->value] ?? 0),
            'rejected' => (int) ($rows[LeadStatus::REJECTED->value] ?? 0),
        ];

        return ['total' => array_sum($counts)] + $counts;
    }

    public function conversionRate(?int $agentId = null): float
    {
        $counts = $this->statusCounts($agentId);

        return $counts['total'] > 0
            ? round($counts['converted'] / $counts['total'] * 100, 1)
            : 0.0;
    }

    public function acceptanceRate(?int $agentId = null): float
    {
        $responded = LeadFeedback::query()
            ->when($agentId, fn ($q) => $q->where('agent_id', $agentId))
            ->count();

        if ($responded === 0) {
            return 0.0;
        }

        $accepted = LeadFeedback::query()
            ->when($agentId, fn ($q) => $q->where('agent_id', $agentId))
            ->where('decision', 'accept')
            ->count();

        return round($accepted / $responded * 100, 1);
    }

    /**
     * Leads created per day for the mobile dashboard chart and the web trend
     * graph. Days with no leads are returned as zeroes so the series is dense.
     *
     * @return array<int,array{date:string,count:int}>
     */
    public function leadsPerDay(string $range = 'week', ?int $agentId = null): array
    {
        $days = $this->rangeDays($range);
        $start = CarbonImmutable::today()->subDays($days - 1);

        // For an agent the meaningful date is when the lead reached them.
        $dateColumn = $agentId ? 'assigned_at' : 'created_at';

        $rows = Lead::query()
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId))
            ->whereNotNull($dateColumn)
            ->where($dateColumn, '>=', $start->startOfDay())
            ->groupBy('day')
            ->selectRaw("DATE({$dateColumn}) AS day, COUNT(*) AS aggregate")
            ->pluck('aggregate', 'day');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i)->toDateString();
            $series[] = ['date' => $date, 'count' => (int) ($rows[$date] ?? 0)];
        }

        return $series;
    }

    /** Conversions per day over the same window (for a second chart line). */
    public function conversionsPerDay(string $range = 'week', ?int $agentId = null): array
    {
        $days = $this->rangeDays($range);
        $start = CarbonImmutable::today()->subDays($days - 1);

        $rows = Lead::query()
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId))
            ->whereNotNull('converted_at')
            ->where('converted_at', '>=', $start->startOfDay())
            ->groupBy('day')
            ->selectRaw('DATE(converted_at) AS day, COUNT(*) AS aggregate')
            ->pluck('aggregate', 'day');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i)->toDateString();
            $series[] = ['date' => $date, 'count' => (int) ($rows[$date] ?? 0)];
        }

        return $series;
    }

    /**
     * Rejection reasons breakdown.
     *
     * @return array<int,array{reason:string,label:string,count:int,percent:float}>
     */
    public function rejectionReasons(?int $agentId = null): array
    {
        $rows = LeadFeedback::query()
            ->where('decision', 'reject')
            ->when($agentId, fn ($q) => $q->where('agent_id', $agentId))
            ->groupBy('reason')
            ->selectRaw('reason, COUNT(*) AS aggregate')
            ->pluck('aggregate', 'reason');

        $total = (int) $rows->sum();

        return collect($rows)
            ->map(fn ($count, $reason) => [
                'reason' => (string) $reason,
                'label' => RejectionReason::tryFrom((string) $reason)?->label() ?? 'Unspecified',
                'count' => (int) $count,
                'percent' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Per-agent scorecard: assigned, accepted, rejected, converted,
     * conversion %, and average response time in hours.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    public function agentScorecard()
    {
        $leadStats = Lead::query()
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->selectRaw('assigned_to')
            ->selectRaw('COUNT(*) AS assigned_count')
            ->selectRaw('SUM(status = ?) AS in_progress_count', [LeadStatus::IN_PROGRESS->value])
            ->selectRaw('SUM(status = ?) AS converted_count', [LeadStatus::CONVERTED->value])
            ->selectRaw('SUM(status = ?) AS rejected_count', [LeadStatus::REJECTED->value])
            ->selectRaw('SUM(status = ?) AS pending_count', [LeadStatus::NEW->value])
            ->selectRaw('AVG(CASE WHEN responded_at IS NOT NULL AND assigned_at IS NOT NULL
                                  THEN TIMESTAMPDIFF(MINUTE, assigned_at, responded_at) END) AS avg_response_minutes')
            ->get()
            ->keyBy('assigned_to');

        $feedbackStats = LeadFeedback::query()
            ->groupBy('agent_id')
            ->selectRaw('agent_id')
            ->selectRaw('SUM(decision = ?) AS accepted_count', ['accept'])
            ->selectRaw('SUM(decision = ?) AS rejected_count', ['reject'])
            ->get()
            ->keyBy('agent_id');

        $revenue = Transaction::completed()
            ->groupBy('agent_id')
            ->selectRaw('agent_id, SUM(amount_cents) AS total_cents')
            ->pluck('total_cents', 'agent_id');

        return User::query()->agents()->with('agentProfile')->orderBy('name')->get()
            ->map(function (User $agent) use ($leadStats, $feedbackStats, $revenue) {
                $leads = $leadStats->get($agent->id);
                $feedback = $feedbackStats->get($agent->id);

                $assigned = (int) ($leads->assigned_count ?? 0);
                $converted = (int) ($leads->converted_count ?? 0);
                $accepted = (int) ($feedback->accepted_count ?? 0);
                $rejected = (int) ($feedback->rejected_count ?? 0);
                $avgMinutes = $leads->avg_response_minutes ?? null;

                return (object) [
                    'agent' => $agent,
                    'assigned' => $assigned,
                    'accepted' => $accepted,
                    'rejected' => $rejected,
                    'pending' => (int) ($leads->pending_count ?? 0),
                    'in_progress' => (int) ($leads->in_progress_count ?? 0),
                    'converted' => $converted,
                    'conversion_rate' => $assigned > 0 ? round($converted / $assigned * 100, 1) : 0.0,
                    'acceptance_rate' => ($accepted + $rejected) > 0
                        ? round($accepted / ($accepted + $rejected) * 100, 1)
                        : 0.0,
                    'avg_response_hours' => $avgMinutes !== null ? round($avgMinutes / 60, 1) : null,
                    'revenue_cents' => (int) ($revenue[$agent->id] ?? 0),
                ];
            });
    }

    // ------------------------------------------------------------- Revenue

    public function totalRevenueCents(): int
    {
        return (int) Transaction::completed()->sum('amount_cents');
    }

    /** @return array<int,array{date:string,cents:int}> */
    public function revenuePerDay(string $range = 'month'): array
    {
        $days = $this->rangeDays($range);
        $start = CarbonImmutable::today()->subDays($days - 1);

        $rows = Transaction::completed()
            ->where('created_at', '>=', $start->startOfDay())
            ->groupBy('day')
            ->selectRaw('DATE(created_at) AS day, SUM(amount_cents) AS cents')
            ->pluck('cents', 'day');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i)->toDateString();
            $series[] = ['date' => $date, 'cents' => (int) ($rows[$date] ?? 0)];
        }

        return $series;
    }

    /** @return array<string,int> revenue in cents keyed by transaction type */
    public function revenueByType(): array
    {
        return Transaction::completed()
            ->groupBy('type')
            ->selectRaw('type, SUM(amount_cents) AS cents')
            ->pluck('cents', 'type')
            ->map(fn ($cents) => (int) $cents)
            ->all();
    }

    public function revenueThisMonthCents(): int
    {
        return (int) Transaction::completed()
            ->where('created_at', '>=', CarbonImmutable::today()->startOfMonth())
            ->sum('amount_cents');
    }

    /** Highest-earning agents for the revenue page. */
    public function topEarningAgents(int $limit = 10)
    {
        return Transaction::completed()
            ->with('agent')
            ->groupBy('agent_id')
            ->selectRaw('agent_id, SUM(amount_cents) AS total_cents, COUNT(*) AS txn_count')
            ->orderByDesc(DB::raw('SUM(amount_cents)'))
            ->limit($limit)
            ->get();
    }

    /** Average hours between assignment and the agent's response, platform-wide. */
    public function avgResponseHours(?int $agentId = null): ?float
    {
        $minutes = Lead::query()
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId))
            ->whereNotNull('assigned_at')
            ->whereNotNull('responded_at')
            ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, assigned_at, responded_at)'));

        return $minutes !== null ? round($minutes / 60, 1) : null;
    }

    private function rangeDays(string $range): int
    {
        return match ($range) {
            'day' => 1,
            'week' => 7,
            'fortnight' => 14,
            'month' => 30,
            'quarter' => 90,
            'year' => 365,
            default => 7,
        };
    }
}

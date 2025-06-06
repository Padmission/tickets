<?php

namespace Padmission\Tickets\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class TicketMetricsService
{
    protected int $cacheTimeInSeconds = 5;

    public function setCacheTime(int $seconds): self
    {
        $this->cacheTimeInSeconds = $seconds ? $seconds : 5;

        return $this;
    }

    /**
     * Calculate the average time to close tickets
     *
     * @param  int|null  $days  Number of days to look back (null for all time)
     * @return array Returns average time and count of tickets analyzed
     */
    public function getAverageCloseTime(?int $days = 30): array
    {
        $cacheKey = __METHOD__.'-'.$days;

        return Cache::remember($cacheKey, $this->cacheTimeInSeconds, function () use ($days) {
            $query = TicketPlugin::resolveModelClass(Ticket::class)::query()
                ->whereNotNull('closed_at');

            if ($days !== null) {
                $query->where('created_at', '>=', Carbon::now()->subDays($days));
            }

            $result = $query->select([
                DB::raw('COUNT(*) as total_tickets'),
                DB::raw('AVG(TIMESTAMPDIFF(SECOND, created_at, closed_at)) as avg_seconds'),
            ])->first();

            $totalClosedTickets = $result->total_tickets ?? 0;
            $avgSeconds = $result->avg_seconds ?? 0;
            $avgTime = $this->formatTimespan($avgSeconds);

            return [
                'average_close_time' => $avgTime,
                'average_seconds' => $avgSeconds,
                'total_closed_tickets' => $totalClosedTickets,
            ];
        });
    }

    /**
     * Get metrics for ticket closure times (min, max, average)
     *
     * @param  int|null  $days  Number of days to look back
     */
    public function getCloseTimeMetrics(?int $days = 30): array
    {
        $cacheKey = __METHOD__.'-'.$days;

        return Cache::remember($cacheKey, $this->cacheTimeInSeconds, function () use ($days) {
            $query = TicketPlugin::resolveModelClass(Ticket::class)::query()
                ->whereNotNull('closed_at');
            if ($days !== null) {
                $query->where('created_at', '>=', Carbon::now()->subDays($days));
            }
            $result = $query->select([
                DB::raw('COUNT(*) as total_tickets'),
                DB::raw('AVG(TIMESTAMPDIFF(SECOND, created_at, closed_at)) as avg_seconds'),
                DB::raw('MIN(TIMESTAMPDIFF(SECOND, created_at, closed_at)) as min_seconds'),
                DB::raw('MAX(TIMESTAMPDIFF(SECOND, created_at, closed_at)) as max_seconds'),
            ])->first();

            return [
                'average' => $this->formatTimespan($result->avg_seconds ?? 0),
                'minimum' => $this->formatTimespan($result->min_seconds ?? 0),
                'maximum' => $this->formatTimespan($result->max_seconds ?? 0),
                'total_closed_tickets' => $result->total_tickets ?? 0,
            ];
        });
    }

    /**
     * Format a time span in seconds to a human-readable format.
     * If we plan on selling this, we need to make it i18n compatible.
     *
     * @param  int  $seconds
     */
    public function formatTimespan(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds).' seconds';
        }
        if ($seconds < 3600) {
            $minutes = round($seconds / 60, 1);

            return $minutes.' '.($minutes == 1 ? 'minute' : 'minutes');
        }
        if ($seconds < 86400) {
            $hours = round($seconds / 3600, 1);

            return $hours.' '.($hours == 1 ? 'hour' : 'hours');
        }
        $days = round($seconds / 86400, 1);

        return $days.' '.($days == 1 ? 'day' : 'days');
    }

    /**
     * Get stacked bar chart data for tickets opened and closed by day
     */
    public function getTicketsByDayChartData(int $days = 14): array
    {
        $cacheKey = __METHOD__.'-'.$days;

        return Cache::remember($cacheKey, $this->cacheTimeInSeconds, function () use ($days) {
            $statusModel = TicketPlugin::resolveModelClass(\Padmission\Tickets\Models\Status::class);
            $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
            $openStatusIds = $statusModel::getOpenStatuses()->pluck('id')->all();
            $closedStatusId = optional($statusModel::getClosedStatus())->id;
            $startDate = now()->subDays($days - 1)->startOfDay();
            $endDate = now()->endOfDay();
            $opened = $ticketModel::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date');
            $closed = $ticketModel::query()
                ->whereNotNull('closed_at')
                ->whereBetween('closed_at', [$startDate, $endDate])
                ->selectRaw('DATE(closed_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date');
            $labels = [];
            $openCounts = [];
            $closedCounts = [];
            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i)->toDateString();
                $labels[] = $date;
                $openCounts[] = (int) ($opened[$date] ?? 0);
                $closedCounts[] = (int) ($closed[$date] ?? 0);
            }

            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => __('Tickets Opened'),
                        'backgroundColor' => '#3b82f6',
                        'data' => $openCounts,
                    ],
                    [
                        'label' => __('Tickets Closed'),
                        'backgroundColor' => '#10b981',
                        'data' => $closedCounts,
                    ],
                ],
            ];
        });
    }

    /**
     * Get the number of tickets waiting on support (open tickets)
     */
    public function getOpenTicketsCount(): int
    {
        $cacheKey = __METHOD__;

        return Cache::remember($cacheKey, $this->cacheTimeInSeconds, function () {
            $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
            $statusModel = TicketPlugin::resolveModelClass(\Padmission\Tickets\Models\Status::class);
            $openStatusIds = $statusModel::getOpenStatuses()->pluck('id')->all();

            return $ticketModel::query()->whereIn('status_id', $openStatusIds)->count();
        });
    }

    /**
     * Get stacked bar chart data for tickets open vs closed by day.
     * For each day, shows tickets that were open at any point that day, split into:
     *   - closed that day
     *   - open but not closed that day
     */
    public function getOpenVsClosedByDayChartData(int $days = 14): array
    {
        $cacheKey = __METHOD__.'-'.$days;

        return Cache::remember($cacheKey, $this->cacheTimeInSeconds, function () use ($days) {
            $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
            $startDate = now()->subDays($days - 1)->startOfDay();
            $endDate = now()->endOfDay();
            $labels = [];
            $openNotClosedCounts = [];
            $closedCounts = [];
            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i);
                $dateStr = $date->toDateString();
                $labels[] = $dateStr;
                $openTicketsQuery = $ticketModel::query()
                    ->where('created_at', '<=', $date->endOfDay())
                    ->where(function ($q) use ($date) {
                        $q->whereNull('closed_at')
                            ->orWhere('closed_at', '>=', $date->startOfDay());
                    });
                $openTickets = $openTicketsQuery->get(['id', 'closed_at']);
                $closedThatDay = $openTickets->filter(function ($t) use ($date) {
                    return $t->closed_at && $t->closed_at->isSameDay($date);
                })->count();
                $openNotClosedThatDay = $openTickets->count() - $closedThatDay;
                $openNotClosedCounts[] = $openNotClosedThatDay;
                $closedCounts[] = $closedThatDay;
            }

            return [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => __('Opened that day'),
                        'data' => $openNotClosedCounts,
                    ],
                    [
                        'label' => __('Closed that day'),
                        'data' => $closedCounts,
                    ],
                ],
            ];
        });
    }
}

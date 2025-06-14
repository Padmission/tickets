<?php

namespace Padmission\Tickets\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class TicketMetricsService
{
    protected int $cacheTimeInSeconds = 10;

    public function setCacheTime(int|string $duration): self
    {
        $seconds = is_string($duration)
            ? CarbonInterval::make($duration)->totalSeconds
            : $duration;

        $this->cacheTimeInSeconds = min(10, $seconds);

        return $this;
    }

    public function getOpenTicketsCount(): int
    {
        $cacheKey = __METHOD__;

        return Cache::remember($cacheKey, $this->cacheTimeInSeconds, function () {
            return TicketPlugin::resolveModelClass(Ticket::class)::query()
                ->whereNull('closed_at')
                ->count();
        });
    }

    public function getOpenTicketsWaitingOnSupportCount(): int
    {
        $cacheKey = __METHOD__;

        return Cache::remember($cacheKey, $this->cacheTimeInSeconds, function () {
            return TicketPlugin::resolveModelClass(Ticket::class)::query()
                ->where('turn', Turn::Supporter)
                ->whereNull('closed_at')
                ->count();
        });
    }

    /**
     * @param  int|null  $days  Number of days to look back (null for all time)
     * @return array{
     *     averageSeconds: int,
     *     totalClosed: int
     * }
     */
    public function getAverageCloseTime(?int $days = 30): array
    {
        $cacheKey = __METHOD__.'-'.$days;

        return Cache::remember($cacheKey, $this->cacheTimeInSeconds, function () use ($days) {
            $query = TicketPlugin::resolveModelClass(Ticket::class)::query()
                ->whereNotNull('closed_at');

            if ($days !== null && $days > 0) {
                $query->where('created_at', '>=', Carbon::now()->subDays($days));
            }

            $result = $query->select([
                DB::raw('COUNT(*) as total_tickets'),
                DB::raw($this->getDurationExpression($query->getConnection(), 'created_at', 'closed_at')),
            ])->first();

            $totalClosedTickets = $result->total_tickets ?? 0;
            $avgSeconds = $result->avg_seconds ?? 0;

            return [
                'averageSeconds' => (int) $avgSeconds,
                'totalClosed' => $totalClosedTickets,
            ];
        });
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     openCounts: list<int>,
     *     closedCounts: list<int>,
     * }
     */
    public function getBurndownChartData(int $days): array
    {
        $startDate = CarbonImmutable::today()->subDays($days - 1);

        [
            'openAtStart' => $openAtStart,
            'opened' => $opened,
            'closed' => $closed,
        ] = $this->getBurndownData($days);

        $labels = [];
        $openCounts = [];
        $closedCounts = [];

        $cumulativeOpened = 0;
        $cumulativeClosed = 0;

        $period = CarbonPeriod::start($startDate)->setRecurrences($days);

        foreach ($period as $date) {
            $dateString = $date->toDateString();
            $labels[] = $date->translatedFormat('M j');

            $openedToday = (int) ($opened[$dateString] ?? 0);
            $closedToday = (int) ($closed[$dateString] ?? 0);

            $cumulativeOpened += $openedToday;
            $cumulativeClosed += $closedToday;

            $openCounts[] = $openAtStart + $cumulativeOpened - $cumulativeClosed;
            $closedCounts[] = $closedToday;
        }

        return [
            'labels' => $labels,
            'openCounts' => $openCounts,
            'closedCounts' => $closedCounts,
        ];
    }

    /**
     * @return array{
     *     opened: int,
     *     closed: int,
     *     openAtStart: int,
     *     startDate: Carbon,
     *     endDate: Carbon
     * }
     */
    public function getBurndownData(int $days): array
    {
        $cacheKey = __METHOD__.'-'.$days;

        return Cache::remember($cacheKey, $this->cacheTimeInSeconds, function () use ($days) {
            $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
            $startDate = CarbonImmutable::today()->subDays($days - 1);
            $endDate = CarbonImmutable::today()->endOfDay();

            $opened = $ticketModel::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
                ->groupBy('day')
                ->pluck('count', 'day')
                ->all();

            $closed = $ticketModel::query()
                ->whereNotNull('closed_at')
                ->whereBetween('closed_at', [$startDate, $endDate])
                ->selectRaw('DATE(closed_at) as day, COUNT(*) as count')
                ->groupBy('day')
                ->pluck('count', 'day')
                ->all();

            $openAtStart = $ticketModel::query()
                ->where('created_at', '<', $startDate)
                ->where(function ($query) use ($startDate) {
                    $query
                        ->whereNull('closed_at')
                        ->orWhere('closed_at', '>=', $startDate);
                })
                ->count();

            return [
                'opened' => $opened,
                'closed' => $closed,
                'openAtStart' => $openAtStart,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ];
        });
    }

    protected function getDurationExpression(ConnectionInterface $connection, string $startColumn, string $endColumn): string
    {
        if (! method_exists($connection, 'getDriverName')) {
            throw new Exception('Unsupported DB driver for TicketMetrics.');
        }

        return match ($connection->getDriverName()) {
            'sqlite' => "AVG(strftime('%s', $endColumn) - strftime('%s', $startColumn)) as avg_seconds",
            'mysql', 'mariadb' => "AVG(TIMESTAMPDIFF(SECOND, $startColumn, $endColumn)) as avg_seconds",
            'pgsql' => "AVG(EXTRACT(EPOCH FROM ($endColumn - $startColumn))) as avg_seconds",
            'sqlsrv' => "AVG(DATEDIFF(SECOND, $startColumn, $endColumn)) as avg_seconds",
            default => throw new Exception("Unsupported DB driver for TicketMetrics: {$connection->getDriverName()}"),
        };
    }
}

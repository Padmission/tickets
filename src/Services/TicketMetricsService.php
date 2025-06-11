<?php

namespace Padmission\Tickets\Services;

use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Padmission\Tickets\Exceptions\DriverNameResolutionException;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class TicketMetricsService
{
    protected int $cacheTimeInSeconds = 5;

    public function setCacheTime(int $seconds): self
    {
        $this->cacheTimeInSeconds = $seconds ?: 5;

        return $this;
    }

    /**
     * @param  int|null  $days  Number of days to look back (null for all time)
     * @return array Returns average time and count of tickets analyzed
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

            $connection = $query->getConnection();
            $driver = $this->getDriverName($connection);

            if ($driver === 'sqlite') {
                $result = $query->select([
                    DB::raw('COUNT(*) as total_tickets'),
                    DB::raw("AVG(strftime('%s', closed_at) - strftime('%s', created_at)) as avg_seconds"),
                ])->first();
            } else {
                $result = $query->select([
                    DB::raw('COUNT(*) as total_tickets'),
                    DB::raw('AVG(TIMESTAMPDIFF(SECOND, created_at, closed_at)) as avg_seconds'),
                ])->first();
            }

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
                ->whereNotNull('closed_at')
                ->when($days, function ($sub) use ($days) {
                    $sub->where('created_at', '>=', Carbon::now()->subDays($days));
                });
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

    public function formatTimespan(float $seconds): string
    {
        return now()->subSeconds($seconds)->diffForHumans(syntax: true);
    }

    /**
     * Get the number of tickets waiting on support (open tickets)
     */
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

    public function getOpenTicketsCount(): int
    {
        $cacheKey = __METHOD__;

        return Cache::remember($cacheKey, $this->cacheTimeInSeconds, function () {
            return TicketPlugin::resolveModelClass(Ticket::class)::query()
                ->where('turn', Turn::Supporter)
                ->whereNull('closed_at')
                ->count();
        });
    }

    public function getOpenVsClosedByDayChartData(int $days): array
    {
        $cacheKey = __METHOD__.'-'.$days;

        return Cache::remember($cacheKey, $this->cacheTimeInSeconds, function () use ($days) {
            $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
            $startDate = now()->subDays($days - 1)->startOfDay();
            $endDate = now()->endOfDay();

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
                    $query->whereNull('closed_at')
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

    protected function getDriverName(ConnectionInterface $connection): string
    {
        if (method_exists($connection, 'getDriverName')) {
            return $connection->getDriverName();
        } else if (method_exists($connection, 'getConfig')) {
            $config = $connection->getConfig();
            if (is_array($config) && array_key_exists('driver', $config)) {
                return $config['driver'];
            }
        }
        throw new DriverNameResolutionException();
    }
}

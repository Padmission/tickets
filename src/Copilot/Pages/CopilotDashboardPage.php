<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Pages;

use Padmission\Tickets\Copilot\CopilotPlugin;
use Padmission\Tickets\Copilot\Widgets\CopilotStatsOverview;
use Padmission\Tickets\Copilot\Widgets\TokenUsageChart;
use Padmission\Tickets\Copilot\Widgets\TopUsersTable;
use Filament\Pages\Page;

class CopilotDashboardPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string | \UnitEnum | null $navigationGroup = 'Copilot';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament-copilot::pages.copilot-dashboard';

    public static function canAccess(): bool
    {
        $guard = CopilotPlugin::get()->getManagementGuard();

        if ($guard) {
            try {
                return auth()->guard($guard)->check();
            } catch (\Throwable) {
                return false;
            }
        }

        return parent::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-copilot::filament-copilot.dashboard');
    }

    public function getTitle(): string
    {
        return __('filament-copilot::filament-copilot.copilot_dashboard');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CopilotStatsOverview::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            TokenUsageChart::class,
            TopUsersTable::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }
}

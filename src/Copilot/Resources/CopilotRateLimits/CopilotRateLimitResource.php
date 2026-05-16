<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Resources\CopilotRateLimits;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Padmission\Tickets\Copilot\CopilotPlugin;
use Padmission\Tickets\Copilot\Models\CopilotRateLimit;
use Padmission\Tickets\Copilot\Resources\CopilotRateLimits\Pages\CreateCopilotRateLimit;
use Padmission\Tickets\Copilot\Resources\CopilotRateLimits\Pages\EditCopilotRateLimit;
use Padmission\Tickets\Copilot\Resources\CopilotRateLimits\Pages\ListCopilotRateLimits;
use Padmission\Tickets\Copilot\Resources\CopilotRateLimits\Schemas\CopilotRateLimitForm;
use Padmission\Tickets\Copilot\Resources\CopilotRateLimits\Tables\CopilotRateLimitsTable;

class CopilotRateLimitResource extends Resource
{
    protected static ?string $model = CopilotRateLimit::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Copilot';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('filament-copilot::filament-copilot.rate_limits');
    }

    public static function getModelLabel(): string
    {
        return __('filament-copilot::filament-copilot.rate_limit');
    }

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

    public static function form(Schema $schema): Schema
    {
        return CopilotRateLimitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CopilotRateLimitsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCopilotRateLimits::route('/'),
            'create' => CreateCopilotRateLimit::route('/create'),
            'edit' => EditCopilotRateLimit::route('/{record}/edit'),
        ];
    }
}

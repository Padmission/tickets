<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Resources\CopilotRateLimits\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CopilotRateLimitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-copilot::filament-copilot.target'))
                    ->schema([
                        TextInput::make('panel_id')
                            ->label(__('filament-copilot::filament-copilot.panel'))
                            ->required(),
                        TextInput::make('participant_type')
                            ->label(__('filament-copilot::filament-copilot.participant_type'))
                            ->required(),
                        TextInput::make('participant_id')
                            ->label(__('filament-copilot::filament-copilot.participant_id'))
                            ->required(),
                    ])->columns(3),
                Section::make(__('filament-copilot::filament-copilot.limits'))
                    ->schema([
                        TextInput::make('max_messages_per_hour')
                            ->label(__('filament-copilot::filament-copilot.max_messages_per_hour'))
                            ->numeric()
                            ->default(60),
                        TextInput::make('max_messages_per_day')
                            ->label(__('filament-copilot::filament-copilot.max_messages_per_day'))
                            ->numeric()
                            ->default(500),
                        TextInput::make('max_tokens_per_hour')
                            ->label(__('filament-copilot::filament-copilot.max_tokens_per_hour'))
                            ->numeric()
                            ->default(100000),
                        TextInput::make('max_tokens_per_day')
                            ->label(__('filament-copilot::filament-copilot.max_tokens_per_day'))
                            ->numeric()
                            ->default(1000000),
                    ])->columns(2),
                Section::make(__('filament-copilot::filament-copilot.status'))
                    ->schema([
                        Toggle::make('copilot_enabled')
                            ->label(__('filament-copilot::filament-copilot.copilot_enabled'))
                            ->default(true),
                        Toggle::make('is_blocked')
                            ->label(__('filament-copilot::filament-copilot.is_blocked'))
                            ->default(false),
                        DateTimePicker::make('blocked_until')
                            ->label(__('filament-copilot::filament-copilot.blocked_until'))
                            ->visible(fn (Get $get) => $get('is_blocked')),
                        Textarea::make('blocked_reason')
                            ->label(__('filament-copilot::filament-copilot.blocked_reason'))
                            ->visible(fn (Get $get) => $get('is_blocked'))
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}

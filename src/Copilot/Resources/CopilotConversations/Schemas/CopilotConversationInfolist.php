<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Resources\CopilotConversations\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;

class CopilotConversationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-copilot::filament-copilot.conversation_details'))
                    ->schema([
                        TextEntry::make('title')
                            ->label(__('filament-copilot::filament-copilot.title')),
                        TextEntry::make('panel_id')
                            ->label(__('filament-copilot::filament-copilot.panel'))
                            ->badge(),
                        TextEntry::make('participant_type')
                            ->label(__('filament-copilot::filament-copilot.participant_type')),
                        TextEntry::make('participant_id')
                            ->label(__('filament-copilot::filament-copilot.participant_id')),
                        TextEntry::make('created_at')
                            ->label(__('filament-copilot::filament-copilot.created_at'))
                            ->dateTime(),
                    ])->columns(3),
                Section::make(__('filament-copilot::filament-copilot.messages'))
                    ->schema([
                        RepeatableEntry::make('messages')
                            ->state(fn ($record): array => $record->ticket?->ticketActivities()
                                ->where('type', ActivityType::Message)
                                ->oldest()
                                ->get()
                                ->map(fn ($activity): array => [
                                    'role' => $activity->sender === ActivitySender::Ai ? 'assistant' : $activity->sender->value,
                                    'content' => $activity->content ?: json_encode($activity->data['blocks'] ?? [], JSON_PRETTY_PRINT),
                                    'input_tokens' => $activity->data['input_tokens'] ?? null,
                                    'output_tokens' => $activity->data['output_tokens'] ?? null,
                                    'created_at' => $activity->created_at,
                                ])
                                ->all() ?? [])
                            ->schema([
                                TextEntry::make('role')
                                    ->label(__('filament-copilot::filament-copilot.role'))
                                    ->badge()
                                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                                        'user' => 'info',
                                        'assistant' => 'success',
                                        'system' => 'warning',
                                        'tool' => 'gray',
                                        default => 'gray',
                                    }),
                                TextEntry::make('content')
                                    ->label(__('filament-copilot::filament-copilot.content'))
                                    ->markdown()
                                    ->columnSpanFull(),
                                TextEntry::make('input_tokens')
                                    ->label(__('filament-copilot::filament-copilot.input_tokens'))
                                    ->numeric()
                                    ->placeholder('—')
                                    ->visible(fn ($state): bool => $state !== null),
                                TextEntry::make('output_tokens')
                                    ->label(__('filament-copilot::filament-copilot.output_tokens'))
                                    ->numeric()
                                    ->placeholder('—')
                                    ->visible(fn ($state): bool => $state !== null),
                                TextEntry::make('created_at')
                                    ->label(__('filament-copilot::filament-copilot.time'))
                                    ->dateTime(),
                            ])->columns(4),
                    ]),
            ]);
    }
}

<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Resources\CopilotConversations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CopilotConversationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-copilot::filament-copilot.conversation_details'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('filament-copilot::filament-copilot.title'))
                            ->maxLength(255),
                        TextInput::make('panel_id')
                            ->label(__('filament-copilot::filament-copilot.panel'))
                            ->disabled(),
                        TextInput::make('participant_type')
                            ->label(__('filament-copilot::filament-copilot.participant_type'))
                            ->disabled(),
                        TextInput::make('participant_id')
                            ->label(__('filament-copilot::filament-copilot.participant_id'))
                            ->disabled(),
                    ])->columns(2),
            ]);
    }
}

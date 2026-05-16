<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Resources\CopilotConversations;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Padmission\Tickets\Copilot\CopilotPlugin;
use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Padmission\Tickets\Copilot\Resources\CopilotConversations\Pages\ListCopilotConversations;
use Padmission\Tickets\Copilot\Resources\CopilotConversations\Pages\ViewCopilotConversation;
use Padmission\Tickets\Copilot\Resources\CopilotConversations\Schemas\CopilotConversationForm;
use Padmission\Tickets\Copilot\Resources\CopilotConversations\Schemas\CopilotConversationInfolist;
use Padmission\Tickets\Copilot\Resources\CopilotConversations\Tables\CopilotConversationsTable;

class CopilotConversationResource extends Resource
{
    protected static ?string $model = CopilotConversation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Copilot';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('filament-copilot::filament-copilot.conversations');
    }

    public static function getModelLabel(): string
    {
        return __('filament-copilot::filament-copilot.conversation');
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
        return CopilotConversationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CopilotConversationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CopilotConversationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCopilotConversations::route('/'),
            'view' => ViewCopilotConversation::route('/{record}'),
        ];
    }
}

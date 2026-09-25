<?php

namespace Padmission\Tickets\Actions;

use Filament\Facades\Filament;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Models\Contracts\HasTicketDisplayName;
use Padmission\Tickets\TicketPlugin;

class GetUserDisplayName
{
    public function __invoke(?int $userId): string
    {
        if (! $userId) {
            return __('padmission-tickets::activities.user_display.unassigned');
        }

        $query = TicketPlugin::resolveUserModelClass()::query();

        // The user may sit outside the viewer's scope, such as a tenant user
        // named on a ticket read from a cross-tenant panel.
        $modifier = TicketPlugin::find(Filament::getCurrentOrDefaultPanel()?->getId())?->getRelationshipScopeModifier();

        if ($modifier) {
            app()->call($modifier, ['relation' => $query, 'model' => 'user']);
        }

        $user = $query->find($userId);

        if (! $user) {
            return __('padmission-tickets::activities.user_display.user_not_found', ['id' => $userId]);
        }

        return $this->forUser($user);
    }

    public function forUser(Model $user): string
    {
        if ($user instanceof HasTicketDisplayName) {
            return $user->getNameForTickets();
        }

        if ($user instanceof HasName) {
            return $user->getFilamentName();
        }

        // Fallback to common name attributes
        if (isset($user->name)) {
            return $user->name;
        }

        if (isset($user->email)) {
            return $user->email;
        }

        // Last resort fallback
        return __('padmission-tickets::activities.user_display.user_not_found', ['id' => $user->getKey()]);
    }
}

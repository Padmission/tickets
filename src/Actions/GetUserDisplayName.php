<?php

namespace Padmission\Tickets\Actions;

use Filament\Facades\Filament;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Models\Contracts\HasTicketDisplayName;
use Padmission\Tickets\TicketPlugin;

class GetUserDisplayName
{
    /*
     * The user may sit outside the viewer's scope, such as a tenant user
     * named on a ticket read from a cross-tenant panel, or another panel's
     * staff named on its ticket, which that panel's scopes reveal. Each panel
     * is tried in turn, a null one being the current panel.
     */
    public function __invoke(?int $userId, ?string ...$panelIds): string
    {
        if (! $userId) {
            return __('padmission-tickets::activities.user_display.unassigned');
        }

        $panelIds = collect($panelIds === [] ? [null] : $panelIds)
            ->map(fn (?string $panelId): ?string => $panelId ?? Filament::getCurrentOrDefaultPanel()?->getId())
            ->unique();

        foreach ($panelIds as $panelId) {
            $user = $this->find($userId, $panelId);

            if ($user !== null) {
                return $this->forUser($user);
            }
        }

        return __('padmission-tickets::activities.user_display.user_not_found', ['id' => $userId]);
    }

    protected function find(int $userId, ?string $panelId): ?Model
    {
        $query = TicketPlugin::resolveUserModelClass()::query();
        $modifier = TicketPlugin::find($panelId)?->getRelationshipScopeModifier();

        if ($modifier) {
            app()->call($modifier, ['relation' => $query, 'model' => 'user']);
        }

        return $query->find($userId);
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

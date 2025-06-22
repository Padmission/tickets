<?php

namespace Padmission\Tickets\Actions;

use Filament\Models\Contracts\HasName;
use Padmission\Tickets\Models\Contracts\HasTicketDisplayName;
use Padmission\Tickets\TicketPlugin;

class GetUserDisplayName
{
    public function __invoke(?int $userId): string
    {
        if (! $userId) {
            return __('padmission-tickets::activities.user_display.unassigned');
        }

        $userModel = TicketPlugin::resolveUserModelClass();
        $user = $userModel::find($userId);

        if (! $user) {
            return __('padmission-tickets::activities.user_display.user_not_found', ['id' => $userId]);
        }

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
        return __('padmission-tickets::activities.user_display.user_not_found', ['id' => $userId]);
    }
}

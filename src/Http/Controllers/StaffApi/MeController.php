<?php

namespace Padmission\Tickets\Http\Controllers\StaffApi;

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Padmission\Tickets\Http\DataMappers\StaffApi\StaffTicketMapper;

class MeController
{
    public function __invoke(Request $request): array
    {
        $user = $request->user();

        return [
            'data' => [
                'user' => StaffTicketMapper::person($user),
                'panel' => Filament::getCurrentPanel()?->getId(),
                'counts' => [
                    'needs_you' => ListTicketsController::forView('needs_you')->count(),
                    'mine' => ListTicketsController::forView('mine')->count(),
                    'unassigned' => ListTicketsController::forView('unassigned')->count(),
                ],
            ],
        ];
    }
}

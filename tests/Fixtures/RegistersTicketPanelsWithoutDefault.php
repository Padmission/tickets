<?php

namespace Padmission\Tickets\Tests\Fixtures;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

trait RegistersTicketPanelsWithoutDefault
{
    protected function defineEnvironment($app): void
    {
        config()->set('padmission-tickets.models.'.Authenticatable::class, User::class);
        Gate::policy(Ticket::class, TestTicketPolicy::class);

        foreach (['organization' => CustomListTickets::class, 'platform' => OtherListTickets::class] as $id => $page) {
            Filament::registerPanel(Panel::make()->id($id)->path($id)->plugin(
                TicketPlugin::make()
                    ->listPage($page)
                    ->allSupportersQuery(fn () => User::query())
                    ->registerResources(),
            ));
        }
    }
}

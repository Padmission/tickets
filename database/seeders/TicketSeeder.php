<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Seeder;
use Padmission\Tickets\Database\Seeders\Concerns\SeedForPanels;
use Padmission\Tickets\Database\Seeders\Concerns\SeedForTenants;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class TicketSeeder extends Seeder
{
    use SeedForPanels;
    use SeedForTenants;

    public function run(?int $tenantId = null): void
    {
        $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

        if ($ticketModel::query()->exists()) {
            return;
        }

        foreach ($this->getTenants($tenantId) as $tenantId) {
            foreach ($this->getPanels() as $panel) {
                Filament::setCurrentPanel($panel);

                $tenantKey = $this->getTenantKey();

                if (config('padmission-tickets.tenancy.enabled')) {
                    $scopeToTenant = fn ($query) => $query->where($tenantKey, $tenantId);
                } else {
                    $scopeToTenant = fn ($query) => null;
                }

                $statuses = TicketPlugin::resolveModelClass(TicketStatus::class)::query()
                    ->tap(new CurrentPanelScope)
                    ->tap($scopeToTenant)
                    ->orderBy('order')
                    ->get()
                    ->tap(fn ($collection) => $collection->pop());

                $priorities = TicketPlugin::resolveModelClass(TicketPriority::class)::query()
                    ->tap($scopeToTenant)
                    ->get();

                $dispositions = TicketPlugin::resolveModelClass(TicketDisposition::class)::query()
                    ->tap($scopeToTenant)
                    ->get();

                // Create users with tenant data if tenancy is enabled
                $userModel = TicketPlugin::resolveModelClass(Authenticatable::class);
                $userFactory = $userModel::factory()->count(3);

                if ($tenantId) {
                    $userFactory = $userFactory->state([$this->getTenantKey() => $tenantId]);
                }

                $users = $userModel::all()->merge($userFactory->create());

                $firstUser = $users->first();

                $baseTicketFactory = $ticketModel::factory()
                    ->recycle($statuses)
                    ->recycle($priorities)
                    ->recycle($dispositions)
                    ->recycle($users)
                    ->state(['panel' => $panel->getId()]);

                if ($tenantId) {
                    $baseTicketFactory = $baseTicketFactory->state([$this->getTenantKey() => $tenantId]);
                }

                $mainTickets = collect([
                    ['subject' => 'Users Turn', 'turn' => Turn::User, 'assignee_id' => $firstUser->id],
                    ['subject' => 'Supporters Turn', 'turn' => Turn::Supporter, 'assignee_id' => $firstUser->id],
                    ['subject' => 'Closed Ticket', 'turn' => Turn::Supporter, 'closed' => true],
                    ['subject' => 'Non-user submitter', 'turn' => Turn::Supporter, 'withSubmitterData' => true],
                    ['subject' => 'Parent Ticket - Login Issues', 'turn' => Turn::Supporter],
                    ['subject' => 'Parent Ticket - Payment Problems', 'turn' => Turn::User],
                ])->map(function ($ticketData) use ($baseTicketFactory) {
                    $factory = clone $baseTicketFactory;

                    if (isset($ticketData['closed'])) {
                        $factory = $factory->closed();
                        unset($ticketData['closed']);
                    }

                    if (isset($ticketData['withSubmitterData'])) {
                        $factory = $factory->withSubmitterData();
                        unset($ticketData['withSubmitterData']);
                    }

                    return $factory->create($ticketData);
                });

                $linkedTickets = collect([
                    ['subject' => 'Related: Password Reset Not Working', 'turn' => Turn::User, 'linked_ticket_id' => $mainTickets[4]->id, 'submitter_id' => $firstUser->id, 'source_panel' => $panel->getId()],
                    ['subject' => 'Related: 2FA Authentication Issue', 'turn' => Turn::Supporter, 'linked_ticket_id' => $mainTickets[4]->id, 'submitter_id' => $firstUser->id, 'source_panel' => $panel->getId()],
                    ['subject' => 'Related: Credit Card Declined', 'turn' => Turn::User, 'linked_ticket_id' => $mainTickets[5]->id, 'source_panel' => $panel->getId()],
                    ['subject' => 'Related: PayPal Integration Error', 'turn' => Turn::Supporter, 'linked_ticket_id' => $mainTickets[5]->id, 'source_panel' => $panel->getId()],
                ])->map(function ($ticketData) use ($baseTicketFactory) {
                    $factory = clone $baseTicketFactory;

                    return $factory->create($ticketData);
                });

                $tickets = $mainTickets->concat($linkedTickets);

                foreach ($tickets as $ticket) {
                    $this->addActivities($ticket, $users);
                }
            }
        }
    }

    /**
     * @param  Ticket  $ticket
     */
    protected function addActivities($ticket, $users): void
    {
        $ticketActivityModel = TicketPlugin::resolveModelClass(TicketActivity::class);
        $baseActivityFactory = $ticketActivityModel::factory()->recycle($users);

        $baseActivityFactory->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Opened,
            'content' => null,
        ]);

        $baseActivityFactory->create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::Supporter,
            'content' => str()->markdown("# Welcome to Support\nHello there! I'm Sarah from the support team. How can I assist you today?"),
        ]);

        $baseActivityFactory->create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::User,
            'content' => str()->markdown("Hi Sarah, thanks for the quick response! I'm having trouble with the app's login feature. When I try to sign in, I keep getting an **\"Invalid Credentials\"** error, even though I'm *certain* my password is correct."),
        ]);

        $baseActivityFactory->create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::Supporter,
            'content' => str()->markdown("I understand how frustrating that can be. Let's troubleshoot this together:\n\n1. First, please try resetting your password using the \"Forgot Password\" link\n2. Make sure caps lock is turned off\n3. Clear your browser cache and cookies\n\nCould you try these steps and let me know if they help?"),
        ]);

        $baseActivityFactory->create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::User,
            'content' => str()->markdown("I've just tried resetting my password and it worked! I can log in now. I think the issue might have been that I recently changed my email address, but the system was still trying to use my old credentials.\n\nThank you for your help!"),
        ]);

        $baseActivityFactory->create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::Supporter,
            'content' => str()->markdown("Excellent! I'm glad to hear that the password reset solved your issue. That's a common occurrence when email addresses are changed.\n\n> **TIP:** Remember to update your email address in all connected services to avoid similar issues in the future.\n\nIs there anything else I can help you with today?"),
        ]);

        $baseActivityFactory->create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::User,
            'content' => str()->markdown("No, that's all for now. Thanks for the quick and effective support!"),
        ]);

        $baseActivityFactory->create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::Supporter,
            'content' => str()->markdown("You're very welcome! If you have any other questions in the future, don't hesitate to reach out to us. Have a great day! 😊"),
        ]);

        $baseActivityFactory->create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::System,
            'type' => ActivityType::Closed,
            'content' => null,
        ]);
    }
}

<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

class TicketSeeder extends Seeder
{
    public function run(?int $tenantId = null): void
    {
        // Get tenancy configuration
        $tenancyEnabled = config('padmission-tickets.tenancy.enabled', false);

        if (! $tenancyEnabled) {
            // No tenancy - seed normally
            $this->seedForTenant(null);

            return;
        }

        // Get tenant model and determine foreign key
        $tenantModelClass = config('padmission-tickets.tenancy.tenancy_model');
        $tenantModel = new $tenantModelClass;
        $tenantKey = Str::snake(class_basename($tenantModelClass)).'_id';

        if ($tenantId !== null) {
            // Seed for specific tenant
            $this->seedForTenant($tenantId, $tenantKey);
        } else {
            // Seed for all tenants
            $tenants = $tenantModel::all();
            foreach ($tenants as $tenant) {
                $this->seedForTenant($tenant->getKey(), $tenantKey);
            }
        }
    }

    protected function seedForTenant(?int $tenantId, ?string $tenantKey = null): void
    {
        foreach (Filament::getPanels() as $panel) {
            // Skip panels where TicketPlugin is not registered
            if (! $panel->hasPlugin(TicketPlugin::$id)) {
                continue;
            }

            Filament::setCurrentPanel($panel);

            $statuses = TicketPlugin::resolveModelClass(TicketStatus::class)::getOpenStatuses();
            $priorities = TicketPlugin::resolveModelClass(TicketPriority::class)::all();
            $dispositions = TicketPlugin::resolveModelClass(TicketDisposition::class)::all();

            // Create users with tenant data if tenancy is enabled
            $userModel = TicketPlugin::resolveModelClass(Authenticatable::class);
            $userFactory = $userModel::factory()->count(3);

            if ($tenantId && $tenantKey) {
                $userFactory = $userFactory->state([$tenantKey => $tenantId]);
            }

            $users = $userFactory->create();

            $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

            // Create ticket factory base with tenant data if needed
            $baseTicketFactory = $ticketModel::factory()
                ->recycle($statuses)
                ->recycle($priorities)
                ->recycle($dispositions)
                ->recycle($users);

            if ($tenantId && $tenantKey) {
                $baseTicketFactory = $baseTicketFactory->state([$tenantKey => $tenantId]);
            }

            // Create different types of tickets using collection
            $tickets = collect([
                ['subject' => 'Users Turn', 'turn' => Turn::User],
                ['subject' => 'Supporters Turn', 'turn' => Turn::Supporter],
                ['subject' => 'Closed Ticket', 'turn' => Turn::Supporter, 'closed' => true],
                ['subject' => 'Non-user submitter', 'turn' => Turn::Supporter, 'withSubmitterData' => true],
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

            $tickets->each(function ($ticket) use ($users, $tenantId, $tenantKey) {
                $this->addActivities($ticket, $users, $tenantId, $tenantKey);
            });
        }
    }

    /**
     * @param  Ticket  $ticket
     */
    protected function addActivities($ticket, $users, ?int $tenantId = null, ?string $tenantKey = null): void
    {
        $ticketActivityModel = TicketPlugin::resolveModelClass(TicketActivity::class);

        // Create base factory for activities
        $baseActivityFactory = $ticketActivityModel::factory()->recycle($users);

        if ($tenantId && $tenantKey) {
            $baseActivityFactory = $baseActivityFactory->state([$tenantKey => $tenantId]);
        }

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

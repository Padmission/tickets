<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Seeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Activity;
use Padmission\Tickets\Models\Priority;
use Padmission\Tickets\Models\Status;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Filament::getPanels() as $panel) {
            Filament::setCurrentPanel($panel);

            $statuses = TicketPlugin::resolveModelClass(Status::class)::getOpenStatuses();
            $priorities = TicketPlugin::resolveModelClass(Priority::class)::all();

            $users = TicketPlugin::resolveModelClass(Authenticatable::class)::factory()
                ->count(3)
                ->create();

            $tickets = [];

            $tickets[] = Ticket::factory()
                ->recycle($statuses)
                ->recycle($priorities)
                ->recycle($users)
                ->create([
                    'subject' => 'Users Turn',
                    'turn' => Turn::User,
                ]);

            $tickets[] = Ticket::factory()
                ->recycle($statuses)
                ->recycle($priorities)
                ->recycle($users)
                ->create([
                    'subject' => 'Supporters Turn',
                    'turn' => Turn::Supporter,
                ]);

            $tickets[] = Ticket::factory()
                ->recycle($statuses)
                ->recycle($priorities)
                ->recycle($users)
                ->closed()
                ->create([
                    'subject' => 'Closed Ticket',
                    'turn' => Turn::Supporter,
                ]);

            $tickets[] = Ticket::factory()
                ->recycle($statuses)
                ->recycle($priorities)
                ->recycle($users)
                ->withSubmitterData()
                ->create([
                    'subject' => 'Non-user submitter',
                    'turn' => Turn::Supporter,
                ]);

            foreach ($tickets as $ticket) {
                $this->addActivities($ticket);
            }
        }
    }

    protected function addActivities(Ticket $ticket): void
    {
        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::System,
            'type' => ActivityType::InternalMessage,
            'content' => str()->markdown('Conversation started'),
        ]);

        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::Supporter,
            'type' => ActivityType::Message,
            'content' => str()->markdown("# Welcome to Support\nHello there! I'm Sarah from the support team. How can I assist you today?"),
        ]);

        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::User,
            'type' => ActivityType::Message,
            'content' => str()->markdown("Hi Sarah, thanks for the quick response! I'm having trouble with the app's login feature. When I try to sign in, I keep getting an **\"Invalid Credentials\"** error, even though I'm *certain* my password is correct."),
        ]);

        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::Supporter,
            'type' => ActivityType::Message,
            'content' => str()->markdown("I understand how frustrating that can be. Let's troubleshoot this together:\n\n1. First, please try resetting your password using the \"Forgot Password\" link\n2. Make sure caps lock is turned off\n3. Clear your browser cache and cookies\n\nCould you try these steps and let me know if they help?"),
        ]);

        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::User,
            'type' => ActivityType::Message,
            'content' => str()->markdown("I've just tried resetting my password and it worked! I can log in now. I think the issue might have been that I recently changed my email address, but the system was still trying to use my old credentials.\n\nThank you for your help!"),
        ]);

        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::Supporter,
            'type' => ActivityType::Message,
            'content' => str()->markdown("Excellent! I'm glad to hear that the password reset solved your issue. That's a common occurrence when email addresses are changed.\n\n> **TIP:** Remember to update your email address in all connected services to avoid similar issues in the future.\n\nIs there anything else I can help you with today?"),
        ]);

        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::User,
            'type' => ActivityType::Message,
            'content' => str()->markdown("No, that's all for now. Thanks for the quick and effective support!"),
        ]);

        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::Supporter,
            'type' => ActivityType::Message,
            'content' => str()->markdown("You're very welcome! If you have any other questions in the future, don't hesitate to reach out to us. Have a great day! 😊"),
        ]);

        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::System,
            'type' => ActivityType::InternalMessage,
            'content' => str()->markdown('Ticket resolved and closed'),
        ]);
    }
}

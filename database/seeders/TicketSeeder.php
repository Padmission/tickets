<?php

namespace Padmission\Tickets\Database\Seeders;

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

    protected function addActivities(Ticket $ticket): void
    {
        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::System,
            'type' => ActivityType::InternalMessage,
            'message' => 'Conversation started',
        ]);

        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::Supporter,
            'type' => ActivityType::Message,
            'message' => 'Hello, how can I help you?',
        ]);

        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::User,
            'type' => ActivityType::Message,
            'message' => 'Hey, I have a problem..',
        ]);

        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::Supporter,
            'type' => ActivityType::Message,
            'message' => 'Did you try turning it off and on again?',
        ]);

        Activity::create([
            'ticket_id' => $ticket->id,
            'sender' => ActivitySender::User,
            'type' => ActivityType::Message,
            'message' => 'Thanks, that helped!',
        ]);
    }
}

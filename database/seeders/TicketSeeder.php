<?php

namespace Padmission\Tickets\Database\Seeders;

use Illuminate\Database\Seeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Activity;
use Padmission\Tickets\Models\Ticket;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test Ticket',
            'status_id' => 1,
            'priority_id' => 1,
            'assignee_id' => 1,
            'submitter_id' => 1,
            'submitter_email' => '',
            'turn' => Turn::User,
        ]);

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

<?php

use App\Models\User;
use Padmission\Tickets\Copilot\Services\AiFeedbackExportService;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;

beforeEach(function () {
    $userModel = class_exists(Padmission\Tickets\Tests\User::class)
        ? Padmission\Tickets\Tests\User::class
        : User::class;

    $this->user = $userModel::factory()->create();
    $this->actingAs($this->user);

    $this->aiStatus = TicketStatus::factory()->create([
        'display_name' => 'AI in progress',
        'seed_key' => 'ai_in_progress',
        'order' => 1,
        'panel' => 'test',
    ]);

    TicketPriority::factory()->create([
        'display_name' => 'Normal',
        'order' => 1,
        'panel' => 'test',
    ]);
});

it('exports flagged ai answers with the reason, prompt, answer blocks, and tool trace', function () {
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->user->id,
        'status_id' => $this->aiStatus->id,
        'subject' => 'Ask AI: What is URP?',
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'sender' => ActivitySender::User,
        'type' => ActivityType::Message,
        'content' => 'What is URP?',
        'created_at' => now()->subMinute(),
    ]);

    $flagged = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'sender' => ActivitySender::Ai,
        'type' => ActivityType::Message,
        'data' => [
            'status' => 'complete',
            'blocks' => [
                ['kind' => 'DefinitionCard', 'props' => ['term' => 'URP', 'definition' => 'Wrong.']],
            ],
            'trace_tools' => [
                ['name' => 'SearchDocumentation', 'status' => 'success'],
            ],
        ],
        'created_at' => now(),
    ]);

    $flagged->flagAiAnswerAsIncorrect(
        reason: 'URP is fixed when a tenancy action is made effective.',
        userId: $this->user->id,
        context: ['record' => ['type' => 'tenancy', 'id' => '4078']],
    );

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'sender' => ActivitySender::Ai,
        'type' => ActivityType::Message,
        'data' => [
            'status' => 'complete',
            'blocks' => [
                ['kind' => 'Lede', 'props' => ['text' => 'Correct answer.']],
            ],
        ],
    ]);

    $line = app(AiFeedbackExportService::class)->toJsonLines();
    $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

    expect($record['feedback']['reason'])->toBe('URP is fixed when a tenancy action is made effective.')
        ->and($record['ticket']['subject'])->toBe('Ask AI: What is URP?')
        ->and($record['user_question']['content'])->toBe('What is URP?')
        ->and($record['ai_activity']['id'])->toBe($flagged->id)
        ->and($record['ai_activity']['blocks'][0]['kind'])->toBe('DefinitionCard')
        ->and($record['ai_activity']['trace_tools'][0]['name'])->toBe('SearchDocumentation');
});

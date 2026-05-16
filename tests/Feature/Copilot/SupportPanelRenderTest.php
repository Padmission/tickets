<?php

use App\Models\User;
use Livewire\Livewire;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Filament\Livewire\Support\SupportPanel;
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

    TicketStatus::factory()->create([
        'display_name' => 'Open',
        'seed_key' => 'open',
        'order' => 2,
        'panel' => 'test',
    ]);

    TicketPriority::factory()->create([
        'display_name' => 'Normal',
        'order' => 1,
        'panel' => 'test',
    ]);
});

it('renders the support panel block vocabulary and escalation offer', function () {
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->user->id,
        'status_id' => $this->aiStatus->id,
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'sender' => ActivitySender::Ai,
        'type' => ActivityType::Message,
        'data' => [
            'status' => 'complete',
            'escalation_reason' => 'financial_reconciliation',
            'blocks' => [
                ['kind' => 'Lede', 'props' => ['text' => 'Here is what I found.']],
                ['kind' => 'DefinitionCard', 'props' => ['term' => 'RFTA', 'definition' => 'Request for tenancy approval.']],
                ['kind' => 'StepList', 'props' => ['steps' => [['title' => 'Review', 'body' => 'Open the record.']]]],
                ['kind' => 'Callout', 'props' => ['tone' => 'warn', 'body' => 'Check payment holds.']],
                ['kind' => 'KVBlock', 'props' => ['rows' => [['label' => 'Status', 'value' => ['kind' => 'StatusPill', 'label' => 'Pending']]]]],
                ['kind' => 'HeadlineBand', 'props' => ['label' => 'HAP', 'value' => ['kind' => 'MoneyValue', 'text' => '$1,250']]],
                ['kind' => 'AuditTrail', 'props' => ['entries' => [['summary' => 'HAP changed.', 'diffs' => [['field' => 'HAP', 'from' => '$1', 'to' => '$2']]]]]],
                ['kind' => 'SourceCitation', 'props' => ['title' => 'Payment tracking']],
            ],
        ],
    ]);

    Livewire::test(SupportPanel::class)
        ->set('activeTicketId', $ticket->id)
        ->assertSee('Here is what I found.')
        ->assertSee('Request for tenancy approval.')
        ->assertSee('Human support recommended')
        ->assertSee('Payment tracking');
});

it('lets a user flag an ai answer as incorrect with a reason', function () {
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->user->id,
        'status_id' => $this->aiStatus->id,
    ]);

    $activity = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'sender' => ActivitySender::Ai,
        'type' => ActivityType::Message,
        'data' => [
            'status' => 'complete',
            'blocks' => [
                ['kind' => 'DefinitionCard', 'props' => ['term' => 'URP', 'definition' => 'Incorrect definition.']],
            ],
        ],
    ]);

    Livewire::test(SupportPanel::class)
        ->set('activeTicketId', $ticket->id)
        ->assertSee('Flag as incorrect')
        ->call('startAiFeedback', $activity->id)
        ->assertSet('feedbackActivityId', $activity->id)
        ->set('feedbackReason', 'URP is a fixed tenancy action amount, not a month-to-month variable amount.')
        ->call('submitAiFeedback')
        ->assertSet('feedbackActivityId', null)
        ->assertSee('Flagged as incorrect');

    expect($activity->fresh()->data['feedback'])
        ->toMatchArray([
            'incorrect' => true,
            'reason' => 'URP is a fixed tenancy action amount, not a month-to-month variable amount.',
            'reported_by' => $this->user->id,
        ]);
});

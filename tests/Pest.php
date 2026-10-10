<?php

use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\TestCase;
use Tiptap\Editor;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

function tiptapDocument(string $html): array
{
    return (new Editor)
        ->setContent($html)
        ->getDocument();
}

/*
 * A ticket in another panel that the source panel escalated, as Escalate
 * leaves it: its source panel and the original-added note, which keep it an
 * escalation once its originals are removed.
 */
function escalationFrom(string $sourcePanel = 'test', array $attributes = [], string $state = 'open'): Ticket
{
    $escalation = Ticket::factory()->{$state}()->create(['panel' => 'test2', 'source_panel' => $sourcePanel, ...$attributes]);
    $escalation->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System);

    return $escalation;
}

function directQuestionFrom(string $sourcePanel = 'test', array $attributes = [], string $state = 'open'): Ticket
{
    $question = Ticket::factory()->{$state}()->create(['panel' => 'test2', 'source_panel' => $sourcePanel, ...$attributes]);
    $question->addTicketActivity(ActivityType::AskedDirectly, ActivitySender::System);

    return $question;
}

function listDirectQuestions(string $tab = 'all'): Testable
{
    return Livewire::test(ListTickets::class, ['activeTab' => $tab])->set('tableFilters.direct_questions.isActive', true);
}

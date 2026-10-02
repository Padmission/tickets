<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\OpenTicketForContactAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\StartTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Support\MessageHtml;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * Every message is HTML the chat draws as it is, so whatever path writes one
 * keeps only what the composer can write: an image's onerror or a script would
 * run for everyone who opens the conversation.
 */
beforeEach(function () {
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();
});

// A rich editor's state as a tampered request can send it: Filament draws this node's HTML as it is.
function rawHtmlDocument(string $html): array
{
    return ['type' => 'doc', 'content' => [['type' => 'rawHtmlMergeTag', 'html' => $html]]];
}

function latestMessageContent(): ?string
{
    return TicketActivity::query()->where('type', ActivityType::Message)->latest('id')->value('content');
}

describe('The sanitizer', function () {
    it('keeps what the composer writes', function (string $html) {
        expect(MessageHtml::sanitize($html))->toBe($html);
    })->with([
        'paragraphs and marks' => '<p><strong>Rent</strong> is <em>due</em></p><p>Line<br>break</p>',
        'lists' => '<ul><li><p>One</p></li></ul><ol><li><p>Two</p></li></ol>',
        'a link' => '<p><a target="_blank" rel="noopener noreferrer nofollow" href="https://example.com/a?b=1">the form</a></p>',
    ]);

    it('drops markup that runs script', function (string $html, string $kept) {
        $clean = MessageHtml::sanitize($html);

        expect($clean)->toContain($kept)
            ->not->toContain('onerror')
            ->not->toContain('<script')
            ->not->toContain('<img')
            ->not->toContain('<iframe')
            ->not->toContain('javascript:');
    })->with([
        'an image' => ['<p>Hi<img src=x onerror=alert(1)></p>', 'Hi'],
        'a script' => ['<p>Hi</p><script>alert(1)</script>', 'Hi'],
        'a frame' => ['<p>Hi</p><iframe src="https://evil.example"></iframe>', 'Hi'],
        'a javascript link' => ['<p><a href="javascript:alert(1)">Hi</a></p>', 'Hi'],
    ]);

    it('reads a value that decodes as JSON as HTML text, not as a document', function () {
        $clean = MessageHtml::sanitize(json_encode(['content' => '<img src=x onerror=alert(1)>']));

        expect($clean)->not->toContain('<img')->not->toContain('onerror')->toStartWith('<p>');
        expect(MessageHtml::sanitize('123'))->toBe('<p>123</p>');
    });

    it('returns nothing for markup with nothing left to show', function (string $html) {
        expect(MessageHtml::sanitize($html))->toBe('');
    })->with(['a lone script' => '<script>alert(1)</script>', 'blank' => '  ']);
});

describe('The chat API', function () {
    beforeEach(function () {
        $this->user = $this->login(User::factory()->create());
        $this->ticket = Ticket::factory()->open()->create(['submitter_id' => $this->user->id]);
    });

    it('stores a JSON-shaped message as text', function () {
        $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), [
            'content' => json_encode(['content' => '<img src=x onerror=alert(document.domain)>']),
        ])->assertOk();

        expect(latestMessageContent())->not->toContain('<img')->not->toContain('onerror');

        $listed = $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->ticket]))->json('messages');

        expect(collect($listed)->pluck('content')->implode(' '))->not->toContain('onerror');
    });

    it('refuses a message with nothing left once its script is dropped, rather than failing', function () {
        $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->ticket]), [
            'content' => '<script>alert(1)</script>',
        ])->assertUnprocessable()->assertJsonValidationErrors('content');

        expect(latestMessageContent())->toBeNull();
    });
});

describe('The ticket actions\' rich editors', function () {
    it('cleans the message Escalate writes to the escalation', function () {
        $this->login();
        TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);
        $original = Ticket::factory()->open()->create();

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->callAction(TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form'), [
                'subject' => 'Rent is wrong',
                'message' => rawHtmlDocument('<p>Please check</p><img src=x onerror=alert(document.domain)>'),
            ])
            ->assertHasNoFormErrors();

        $content = Ticket::query()->where('subject', 'Rent is wrong')->sole()->ticketActivities()->where('type', ActivityType::Message)->sole()->content;

        expect($content)->toContain('Please check')->not->toContain('<img')->not->toContain('onerror');
    });

    it('cleans the message Escalate tells the requester', function () {
        $this->login();
        TicketPlugin::get()->allowLinkedTicketsTo(panelIds: ['test']);
        $requester = User::factory()->create();
        $original = Ticket::factory()->open()->create(['submitter_id' => $requester->id]);

        Livewire::test(ViewTicket::class, ['record' => $original->id])
            ->mountAction(TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form'))
            ->fillForm([
                'subject' => 'Rent is wrong',
                'message' => tiptapDocument('Please check'),
                'notify_requester' => true,
                'requester_message' => rawHtmlDocument('<p>On it</p><img src=x onerror=alert(document.domain)>'),
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $told = $original->ticketActivities()->where('type', ActivityType::Message)->sole()->content;

        expect($told)->toContain('On it')->not->toContain('<img')->not->toContain('onerror');
    });

    it('cleans the message Start ticket writes', function (string $kind) {
        $supporter = $this->login(User::factory()->create());
        $requester = User::factory()->create();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);

        Livewire::test(ListTickets::class)
            ->callAction(TestAction::make(StartTicketAction::class), [
                'kind' => $kind,
                'requester_id' => $kind === StartTicketAction::ORGANIZATION ? $requester->id : null,
                'assign' => 'me',
                'subject' => 'Pay stubs',
                'message' => rawHtmlDocument('<p>Hello</p><img src=x onerror=alert(document.domain)>'),
            ])
            ->assertHasNoFormErrors();

        expect(latestMessageContent())->toContain('Hello')->not->toContain('<img')->not->toContain('onerror');
    })->with([StartTicketAction::ORGANIZATION, StartTicketAction::ESCALATION]);

    it('cleans the message Open for contact writes', function () {
        $staff = User::factory()->create();
        $contact = User::factory()->create();
        TicketPlugin::get('test')->allowLinkedTicketsTo(['test2']);
        TicketPlugin::get('test')->allSupportersQuery(fn () => User::query()->whereKey($contact->id));
        TicketPlugin::get('test2')->startsTickets()->allSupportersQuery(fn () => User::query()->whereKey($staff->id));
        Filament::setCurrentPanel('test2');
        $this->login($staff);

        Livewire::test(ListTickets::class)
            ->callAction(TestAction::make(OpenTicketForContactAction::class), [
                'contact_id' => $contact->id,
                'subject' => 'Load the schedule',
                'message' => rawHtmlDocument('<p>Hello</p><img src=x onerror=alert(document.domain)>'),
            ])
            ->assertHasNoFormErrors();

        expect(latestMessageContent())->toContain('Hello')->not->toContain('<img')->not->toContain('onerror');
    });
});

<?php

use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * The ticket emails are Markdown, so text a requester typed is written into
 * them as text: a link or image spelled out in Markdown must reach staff as
 * the characters typed, never as a link to follow or an image that loads.
 */
beforeEach(function () {
    (new TicketStatusSeeder)->run();
    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->requester = User::factory()->create(['name' => 'Aisha Brooks']);
    $this->supporter = User::factory()->create(['name' => 'Tess Support']);
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($this->supporter->id));

    $this->link = '[Reset your password](https://evil.example/reset)';
    $this->image = '![x](https://track.example/p.png)';
});

function renderedMail(TicketNotification $notification, User $to): string
{
    return (string) $notification->toMail($to)->render();
}

function expectNoMarkup(string $html): void
{
    expect($html)
        ->not->toContain('href="https://evil.example')
        ->not->toContain('src="https://track.example')
        ->toContain('Reset your password')
        ->toContain('https://evil.example/reset');
}

it('writes the subject and opening message of a new ticket as text', function () {
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'subject' => $this->link]);
    $ticket->ticketActivities()->create(['type' => ActivityType::Message, 'sender' => ActivitySender::User, 'user_id' => $this->requester->id, 'content' => "<p>{$this->link} {$this->image}</p>"]);

    expectNoMarkup(renderedMail(new TicketNotification($ticket, new TicketCreatedEvent($ticket, $this->requester)), $this->supporter));
});

it('writes the subject and latest reply of a closed ticket as text', function () {
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'subject' => $this->link]);
    $ticket->ticketActivities()->create(['type' => ActivityType::Message, 'sender' => ActivitySender::Supporter, 'user_id' => $this->supporter->id, 'content' => "<p>{$this->image}</p>"]);
    $ticket->close(closedById: $this->supporter->id);

    $html = renderedMail(new TicketNotification($ticket->refresh(), new TicketClosedEvent($ticket, $this->supporter)), $this->requester);

    expectNoMarkup($html);
});

it('writes a message in the recent activity as text, even one that tries to end the table', function () {
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id]);
    $ticket->ticketActivities()->create([
        'type' => ActivityType::Message,
        'sender' => ActivitySender::User,
        'user_id' => $this->requester->id,
        'content' => "<p>Hi\n\n{$this->link}\n\n{$this->image}</p>",
    ]);

    $html = renderedMail(new TicketNotification($ticket, new TicketActivityEvent($ticket, ActivityType::Message, null, $this->requester)), $this->supporter);

    expectNoMarkup($html);
});

it('reads ordinary punctuation as typed in both the HTML and the plain-text part', function () {
    $this->requester->update(['name' => "Ann O'Neil & Co"]);
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'subject' => "Rent [2026] *urgent* _now_ & O'Neil's"]);
    $ticket->ticketActivities()->create([
        'type' => ActivityType::Message,
        'sender' => ActivitySender::User,
        'user_id' => $this->requester->id,
        'content' => "<p>Rent (2026) is \$1,200 &amp; it's wrong.\n1. First\n# Second</p>",
    ]);

    foreach ([new TicketCreatedEvent($ticket, $this->requester), new TicketActivityEvent($ticket, ActivityType::Message, null, $this->requester)] as $event) {
        $mail = (new TicketNotification($ticket, $event))->toMail($this->supporter);
        $html = (string) $mail->render();
        $text = (string) app(Markdown::class)->renderText($mail->markdown, $mail->data());

        expect($text)->not->toContain('&#')->not->toContain('&amp;')->not->toContain('<br')
            ->and($html)->not->toContain('&amp;#')->not->toContain('&amp;amp;')
            ->and(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->not->toContain('&#');

        $event instanceof TicketCreatedEvent
            ? expect($text)->toContain("Rent [2026] *urgent* _now_ & O'Neil's")
            : expect($text)->toContain("Rent (2026) is \$1,200 & it's wrong.")->toContain('1. First')->toContain('# Second')->toContain("Ann O'Neil & Co");
    }
});

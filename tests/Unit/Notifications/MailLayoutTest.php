<?php

use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationRecipient;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Notifications\OtpNotification;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config(['mail.default' => 'array', 'app.name' => 'Padmission', 'app.url' => 'https://padmission.test']);
});

function sentTicketEmail(): Email
{
    return app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
}

it('leaves the bare app URL out of the plain text while the HTML keeps the host\'s header', function (Closure $notify, string $headline) {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create(['subject' => 'Rent is wrong']);

    $notify($user, $ticket);

    $email = sentTicketEmail();
    $text = trim((string) $email->getTextBody());

    expect($text)->not->toContain('https://padmission.test')
        ->toStartWith('# '.__($headline))
        ->toEndWith('© '.date('Y').' Padmission. All rights reserved.')
        ->and($email->getHtmlBody())->toContain('href="https://padmission.test"');
})->with([
    'a new ticket' => [function (User $user, Ticket $ticket) {
        $ticket->forceFill([
            'submitter_id' => $user->id,
            'assignee_id' => User::factory()->create()->id,
        ])->saveQuietly();

        $user->notify(new TicketNotification($ticket, new TicketCreatedEvent($ticket, $user)));
    }, 'padmission-tickets::notifications.ticket-created.headline'],
    'a reply' => [function (User $user, Ticket $ticket) {
        TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'sender' => ActivitySender::Supporter,
            'user_id' => User::factory()->create()->id,
            'content' => 'Which household?',
        ]);

        $user->notify(new TicketNotification($ticket, new TicketActivityEvent($ticket, ActivityType::Message)));
    }, 'padmission-tickets::notifications.ticket-activity.headline'],
    'a closed ticket' => [function (User $user, Ticket $ticket) {
        TicketPlugin::get()->notificationConfiguration(
            NotificationConfiguration::make()
                ->on(TicketClosedEvent::class, fn () => NotificationRecipient::User)
        );
        $user->notify(new TicketNotification($ticket, new TicketClosedEvent($ticket)));
    }, 'padmission-tickets::notifications.ticket-closed.headline'],
    'a sign-in code' => [fn (User $user) => $user->notify(new OtpNotification($user, '123456')), 'padmission-tickets::notifications.otp-verification.subject'],
]);

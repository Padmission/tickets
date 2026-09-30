<?php

namespace Padmission\Tickets\Seeding;

use Carbon\CarbonImmutable;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Models\TicketUserState;
use Padmission\Tickets\TicketPlugin;
use RuntimeException;

/*
 * Rows are written by hand, mirroring the live flows, rather than through
 * them: those flows notify, auto-assign and stamp everything now, and read
 * the signed-in user, none of which a seeder has or wants. A host seeder
 * that turns model events off would also lose every row an observer writes.
 */
class TicketScenarios
{
    public const string MARKER = 'seeded_scenario';

    /** @var list<Model> */
    protected array $requesters;

    /** @var list<Model> */
    protected array $supporters;

    /** @var list<Model> */
    protected array $colleagues;

    protected ?string $targetPanelId = null;

    /** @var list<Model> */
    protected array $targetSupporters = [];

    protected CarbonImmutable $at;

    /**
     * @param  iterable<Model>  $requesters  none of whom may be a supporter
     * @param  iterable<Model>  $supporters  the first owns escalations
     * @param  iterable<Model>  $colleagues  take over an escalation
     */
    public function __construct(
        protected string $panelId,
        protected Model|int|string|null $tenant,
        iterable $requesters,
        iterable $supporters,
        iterable $colleagues = [],
    ) {
        $this->requesters = array_values([...$requesters]);
        $this->supporters = array_values([...$supporters]);
        $this->colleagues = array_values([...$colleagues]);

        if ($this->requesters === [] || $this->supporters === []) {
            throw new InvalidArgumentException('Ticket scenarios need at least one requester and one supporter.');
        }

        $this->ensurePanel($panelId);
        $this->at = CarbonImmutable::now();
    }

    /**
     * @param  iterable<Model>  $requesters
     * @param  iterable<Model>  $supporters
     * @param  iterable<Model>  $colleagues
     */
    public static function make(string $panelId, Model|int|string|null $tenant, iterable $requesters, iterable $supporters, iterable $colleagues = []): static
    {
        return new static($panelId, $tenant, $requesters, $supporters, $colleagues); // @phpstan-ignore new.static
    }

    /**
     * @param  iterable<Model>  $supporters
     */
    public function escalatesTo(string $panelId, iterable $supporters): static
    {
        $this->ensurePanel($panelId);

        $this->targetPanelId = $panelId;
        $this->targetSupporters = array_values([...$supporters]);

        return $this;
    }

    /**
     * @return array<string, Ticket>
     */
    public function all(): array
    {
        $tickets = [
            'conversation' => $this->conversation(),
            'waitingOnRequester' => $this->waitingOnRequester(),
            'closed' => $this->closed(),
            'reopened' => $this->reopened(),
            'unassigned' => $this->unassigned(),
            'assignedToNonSupporter' => $this->assignedToNonSupporter(),
            'openedFor' => $this->openedFor(),
        ];

        if ($this->targetPanelId === null) {
            return $tickets;
        }

        $tickets['escalation'] = $this->escalation(originals: 3, closedOriginals: 1);
        $tickets['directQuestion'] = $this->directQuestion();

        if ($this->colleague() !== null) {
            $tickets['handedOver'] = $this->handedOver();
        }

        return $tickets;
    }

    public function conversation(?string $key = null): Ticket
    {
        return $this->seedOnce($key ?? 'conversation', function (): Ticket {
            $requester = $this->requester(0);
            $supporter = $this->supporter(0);

            $this->startAt(days: 4, hour: 9, minute: 12);

            $ticket = $this->openFromChat(
                $requester,
                'Monthly report export stops at 80%',
                '<p>When I export the monthly activity report to CSV, the progress bar stops at 80% and the file never downloads. It worked fine last month. I have tried Chrome and Firefox.</p>',
                $supporter,
            );

            $this->later(hours: 3);
            $this->message($ticket, $supporter, '<p>Thanks for the details. Could you tell me roughly how many rows the report has, and whether a shorter date range exports?</p>');

            $this->later(hours: 20);
            $this->message($ticket, $requester, '<p>About 14,000 rows for the full month. A single week exports without any trouble.</p>');

            $this->later(days: 1, hours: 3);
            $this->message($ticket, $supporter, '<p>Large exports are running into a time limit. As a workaround, please export the month in two halves while we raise the limit. I will let you know once it is done.</p>');

            $this->later(hours: 5);
            $this->message($ticket, $requester, '<p>Two halves worked, thank you. Could you let me know when the full month works again? Our finance team needs it in a single file.</p>');

            return $ticket;
        });
    }

    public function waitingOnRequester(?string $key = null): Ticket
    {
        return $this->seedOnce($key ?? 'waitingOnRequester', function (): Ticket {
            $requester = $this->requester(1);
            $supporter = $this->supporter(1);

            $this->startAt(days: 1, hour: 14, minute: 5);

            $ticket = $this->openFromChat(
                $requester,
                'Invoice shows our old billing address',
                '<p>Our latest invoice still has the address we moved away from in the spring. Can it be reissued with the new one?</p>',
                $supporter,
            );

            $this->later(hours: 2, minutes: 15);
            $this->message($ticket, $supporter, '<p>Happy to reissue it. Could you send the new billing address exactly as it should appear, and the invoice number?</p>');

            return $ticket;
        });
    }

    public function closed(?string $key = null): Ticket
    {
        return $this->seedOnce($key ?? 'closed', function (): Ticket {
            $requester = $this->requester(2);
            $supporter = $this->supporter(0);

            $this->startAt(days: 12, hour: 10, minute: 30);

            $ticket = $this->openFromChat(
                $requester,
                'Password reset email never arrives',
                '<p>I requested a password reset three times this morning and none of the emails have arrived. Nothing in spam either.</p>',
                $supporter,
            );

            $this->later(minutes: 50);
            $this->message($ticket, $supporter, '<p>Your mail server was rejecting our messages. I have asked it to try again, so the reset email should arrive within a few minutes.</p>');

            $this->later(minutes: 25);
            $this->message($ticket, $requester, '<p>Got it and I am back in. Thanks!</p>');

            $this->later(hours: 1);
            $this->close($ticket, $supporter);

            return $ticket;
        });
    }

    public function reopened(?string $key = null): Ticket
    {
        return $this->seedOnce($key ?? 'reopened', function (): Ticket {
            $requester = $this->requester(0);
            $supporter = $this->supporter(1);

            $this->startAt(days: 9, hour: 11, minute: 0);

            $ticket = $this->openFromChat(
                $requester,
                'Email notifications stopped after the update',
                '<p>Since last week\'s update none of us get email notifications about new assignments.</p>',
                $supporter,
            );

            $this->later(hours: 4);
            $this->message($ticket, $supporter, '<p>The update switched notification emails off for some accounts. I have switched them back on for your whole team, so they should start again right away.</p>');

            $this->later(days: 1);
            $this->close($ticket, $supporter);

            $this->later(days: 5, hours: 3);
            $this->reopenByReply($ticket, $requester, '<p>They came back for a few days but stopped again yesterday. Nobody on the team got the notifications for this morning\'s assignments.</p>');

            return $ticket;
        });
    }

    public function unassigned(?string $key = null): Ticket
    {
        return $this->seedOnce($key ?? 'unassigned', function (): Ticket {
            $this->startAt(hours: 3);

            return $this->openFromChat(
                $this->requester(1),
                'How do I add a teammate?',
                '<p>A new colleague starts on Monday. How do I give them access to our account, and can I limit what they see?</p>',
                null,
            );
        });
    }

    // A requester is never in the supporter pool, so one stands in for a person who left the team.
    public function assignedToNonSupporter(?Model $assignee = null, ?string $key = null): Ticket
    {
        return $this->seedOnce($key ?? 'assignedToNonSupporter', function () use ($assignee): Ticket {
            $this->startAt(days: 2, hour: 16, minute: 45);

            return $this->openFromChat(
                $this->requester(2),
                'Charged twice for this month',
                '<p>Our card was charged twice for this month\'s subscription. Could you refund one of the charges?</p>',
                $assignee ?? $this->requester(0),
            );
        });
    }

    public function openedFor(?string $key = null): Ticket
    {
        return $this->seedOnce($key ?? 'openedFor', function (): Ticket {
            $requester = $this->requester(1);
            $supporter = $this->supporter(0);

            $this->startAt(days: 3, hour: 13, minute: 20);

            $ticket = $this->newTicket([
                'panel' => $this->panelId,
                'source_panel' => $this->panelId,
                'subject' => 'Set up single sign-on for the team',
                'submitter_id' => $requester->getKey(),
                'assignee_id' => $supporter->getKey(),
                'turn' => Turn::Supporter,
            ]);

            $this->write($ticket, ActivityType::OpenedFor, ActivitySender::System, $supporter, data: ['requester' => $requester->getKey()]);
            $this->message($ticket, $supporter, '<p>As we discussed on the phone, I am opening this so we can track the single sign-on setup. I will send the configuration details here once they are ready.</p>');

            $this->later(hours: 2);
            $this->message($ticket, $requester, '<p>Thanks. Our identity provider is Okta, in case that matters.</p>');

            return $ticket;
        });
    }

    /*
     * Without $answered it waits on the other team; with it, their answer is
     * the last word and it waits on this one.
     */
    public function escalation(int $originals = 2, int $closedOriginals = 0, bool $answered = false, ?string $key = null): Ticket
    {
        if ($originals < 1 || $closedOriginals < 0 || $closedOriginals > $originals) {
            throw new InvalidArgumentException('An escalation needs at least one original, and cannot close more originals than it has.');
        }

        $key ??= "escalation:{$originals}:{$closedOriginals}".($answered ? ':answered' : '');

        return $this->withOriginals($this->seedOnce($key, function () use ($key, $originals, $closedOriginals, $answered): Ticket {
            $owner = $this->supporter(0);
            $team = $this->targetSupporter();
            $subjects = [
                'Data import stuck on "processing"',
                'Imported records are missing their notes',
                'Import finished but the totals look wrong',
                'Import has been running since yesterday',
                'Nightly import did not run',
            ];

            $this->startAt(days: 6, hour: 8, minute: 40);

            $linked = [];

            foreach (range(0, $originals - 1) as $index) {
                $linked[] = $this->withMarker("{$key}:original:{$index}", fn (): Ticket => $this->openFromChat(
                    $this->requester($index),
                    $subjects[$index % count($subjects)],
                    '<p>I started an import this morning and it still says "processing". Nothing new has appeared in our account.</p>',
                    $this->supporter($index),
                ));

                $this->later(minutes: 35);
            }

            $escalation = $this->escalate(
                $linked[0],
                $owner,
                'Imports stuck in processing for several customers',
                '<p>Several of our customers have imports that never leave "processing" since this morning. Could you check the import queue? The customers\' tickets are linked.</p>',
                '<p>Thanks for your patience. I have asked our platform team to look into the stuck import and will update you here.</p>',
            );

            foreach (array_slice($linked, 1) as $original) {
                $this->later(minutes: 20);
                $this->addOriginal($escalation, $original, $owner);
            }

            if ($team !== null) {
                $this->later(hours: 2);
                $this->message($escalation, $team, '<p>We are looking at it. Do you know roughly when the first stuck import started?</p>');

                $this->later(minutes: 30);
                $this->message($escalation, $owner, '<p>The earliest one we know of started at 7:10 this morning.</p>');
            }

            foreach (array_slice($linked, $originals - $closedOriginals) as $original) {
                $this->later(hours: 3);
                $this->message($original, $this->assigneeOf($original) ?? $owner, '<p>Your import has now finished. Please let us know if anything looks off.</p>');
                $this->later(hours: 1);
                $this->close($original, $this->assigneeOf($original) ?? $owner);
            }

            if ($answered && $team !== null) {
                $this->later(hours: 4);
                $this->message($escalation, $team, '<p>A worker stopped picking up imports at 7:05. We have restarted it and the queue is draining now, so the stuck imports should all finish within the hour.</p>');
            }

            return $escalation;
        }));
    }

    public function directQuestion(?string $key = null): Ticket
    {
        return $this->seedOnce($key ?? 'directQuestion', function (): Ticket {
            $asker = $this->supporter(0);

            $this->startAt(days: 1, hour: 10, minute: 5);

            $question = $this->newTicket([
                'panel' => $this->targetPanel(),
                'source_panel' => $this->panelId,
                'subject' => 'Can we raise our file upload limit?',
                'submitter_id' => $asker->getKey(),
                'assignee_id' => $this->targetSupporter()?->getKey(),
                'turn' => Turn::Supporter,
            ]);

            $this->write($question, ActivityType::AskedDirectly, ActivitySender::System, $asker);
            $this->message($question, $asker, '<p>Several of our users need to upload scans of around 40 MB. Is it possible to raise the upload limit for our account?</p>');

            return $question;
        });
    }

    public function handedOver(?string $key = null): Ticket
    {
        return $this->withOriginals($this->seedOnce($key ?? 'handedOver', function () use ($key): Ticket {
            $owner = $this->supporter(0);
            $colleague = $this->colleague() ?? throw new InvalidArgumentException('handedOver() needs a colleague or a second supporter to hand the escalation to.');
            $team = $this->targetSupporter();

            $this->startAt(days: 5, hour: 15, minute: 10);

            $original = $this->withMarker(($key ?? 'handedOver').':original:0', fn (): Ticket => $this->openFromChat(
                $this->requester(2),
                'Scheduled reports go out an hour late',
                '<p>Our scheduled reports have been arriving an hour after the time we set since the clocks changed.</p>',
                $owner,
            ));

            $this->later(hours: 1);

            $escalation = $this->escalate(
                $original,
                $owner,
                'Scheduled reports off by an hour since the clock change',
                '<p>A customer\'s scheduled reports have been sent an hour late since the clocks changed. Could the scheduler be ignoring daylight saving time?</p>',
            );

            if ($team !== null) {
                $this->later(hours: 5);
                $this->message($escalation, $team, '<p>We think so. Which time zone is the customer\'s account set to?</p>');
            }

            $this->later(hours: 16);
            $this->handOver($escalation, $colleague, by: $owner);

            $this->later(hours: 1);
            $this->message($escalation, $colleague, '<p>I have taken this over while my colleague is away. The account is set to Eastern Time.</p>');

            return $escalation;
        }));
    }

    /*
     * One transaction, so a failure leaves no half-built scenario for the
     * marker to find next time.
     *
     * @param  Closure(): Ticket  $build
     */
    protected function seedOnce(string $key, Closure $build): Ticket
    {
        $existing = $this->findSeeded($key);

        if ($existing !== null) {
            return $existing;
        }

        return $this->ticketModel()::withoutEvents(
            fn (): Ticket => (new ($this->ticketModel()))->getConnection()->transaction(fn (): Ticket => $this->withMarker($key, $build)),
        );
    }

    /**
     * @param  Closure(): Ticket  $build
     */
    protected function withMarker(string $key, Closure $build): Ticket
    {
        $ticket = $build();

        $ticket->forceFill(['data' => [...($ticket->data ?? []), self::MARKER => $key]])->saveQuietly(['timestamps' => false]);

        return $ticket;
    }

    protected function findSeeded(string $key): ?Ticket
    {
        return $this->ticketModel()::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('source_panel', $this->panelId)
            ->when($this->tenantColumn() !== null, fn ($query) => $query->where($this->tenantColumn(), $this->tenantKey()))
            ->where('data->'.self::MARKER, $key)
            ->first();
    }

    protected function withOriginals(Ticket $escalation): Ticket
    {
        return $escalation->setRelation('childTickets', $this->ticketModel()::query()
            ->withoutGlobalScopes()
            ->where('linked_ticket_id', $escalation->getKey())
            ->orderBy('id')
            ->get());
    }

    /*
     * The ticket starts waiting on the requester, as CreateTicketController
     * makes it, so their first message moves the turn. An assignee set on
     * creation, as by the assignment strategy, gets no history note.
     */
    protected function openFromChat(Model $requester, string $subject, string $message, ?Model $assignee): Ticket
    {
        $ticket = $this->newTicket([
            'panel' => $this->panelId,
            'source_panel' => $this->panelId,
            'subject' => $subject,
            'submitter_id' => $requester->getKey(),
            'assignee_id' => $assignee?->getKey(),
            'turn' => Turn::User,
        ]);

        $widget = TicketPlugin::find($this->panelId)?->getChatWidgetConfig();

        $this->write($ticket, ActivityType::Message, ActivitySender::System, $requester, $widget?->getIntroMessage());
        $this->message($ticket, $requester, $message);
        $this->write($ticket, ActivityType::Message, ActivitySender::System, $requester, $widget?->getAutoResponse());

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function newTicket(array $attributes): Ticket
    {
        $panelId = $attributes['panel'];

        $ticket = (new ($this->ticketModel()))->forceFill([
            ...($this->tenantColumn() === null ? [] : [$this->tenantColumn() => $this->tenantKey()]),
            'status_id' => $this->status($panelId, 'asc')->getKey(),
            'priority_id' => $this->lookup(TicketPriority::class, $panelId)?->getKey()
                ?? throw new RuntimeException("No ticket priorities for panel \"{$panelId}\". Run tickets:seed --only=priorities first."),
            'data' => [],
            ...$attributes,
            'created_at' => $this->at,
            'updated_at' => $this->at,
        ]);

        $ticket->save();

        return $ticket;
    }

    // $keepTurn is "Send as update", and the word to a requester that escalating leaves.
    protected function message(Ticket $ticket, Model $writer, string $content, bool $keepTurn = false): TicketActivity
    {
        $sender = $ticket->isSubmittedBy($writer) ? ActivitySender::User : ActivitySender::Supporter;
        $activity = $this->write($ticket, ActivityType::Message, $sender, $writer, $content);

        $next = $sender === ActivitySender::Supporter ? Turn::User : Turn::Supporter;

        if (! $keepTurn && $ticket->turn !== $next) {
            $this->write($ticket, ActivityType::TurnChanged, ActivitySender::System, $writer, data: ['from' => $ticket->turn->value, 'to' => $next->value]);
            $this->updateTicket($ticket, ['turn' => $next]);
        }

        return $activity;
    }

    protected function close(Ticket $ticket, Model $closer): void
    {
        $from = $ticket->status_id;
        $closed = $this->status($ticket->panel, 'desc');
        $dispositionId = $this->lookup(TicketDisposition::class, $ticket->panel)?->getKey();

        $this->updateTicket($ticket, [
            'status_id' => $closed->getKey(),
            'disposition_id' => $dispositionId,
            'closed_by' => $closer->getKey(),
            'closed_at' => $this->at,
        ]);

        if ($from !== $closed->getKey()) {
            $this->write($ticket, ActivityType::StatusChanged, ActivitySender::System, $closer, data: ['from' => $from, 'to' => $closed->getKey()]);
        }

        $this->write($ticket, ActivityType::Closed, ActivitySender::System, $closer, data: ['closed_by' => $closer->getKey(), 'disposition_id' => $dispositionId]);
    }

    // The observer notes the status change before the save and Reopened after it.
    protected function reopenByReply(Ticket $ticket, Model $writer, string $content): TicketActivity
    {
        $from = $ticket->status_id;
        $open = $this->status($ticket->panel, 'asc');

        $this->write($ticket, ActivityType::StatusChanged, ActivitySender::System, $writer, data: ['from' => $from, 'to' => $open->getKey()]);
        $this->updateTicket($ticket, ['status_id' => $open->getKey(), 'closed_at' => null, 'closed_by' => null, 'disposition_id' => null]);
        $this->write($ticket, ActivityType::Reopened, ActivitySender::System, $writer);

        return $this->message($ticket, $writer, $content);
    }

    protected function escalate(Ticket $original, Model $owner, string $subject, string $message, ?string $tellRequester = null): Ticket
    {
        $escalation = $this->newTicket([
            'panel' => $this->targetPanel(),
            'source_panel' => $this->panelId,
            'subject' => $subject,
            'submitter_id' => $owner->getKey(),
            'assignee_id' => $this->targetSupporter()?->getKey(),
            'turn' => Turn::Supporter,
        ]);

        $this->message($escalation, $owner, $message);
        $this->link($original, $escalation, $owner, started: true);

        if ($tellRequester !== null) {
            $this->message($original, $owner, $tellRequester, keepTurn: true);
        }

        return $escalation;
    }

    protected function addOriginal(Ticket $escalation, Ticket $original, Model $by): void
    {
        $this->link($original, $escalation, $by, started: false);
    }

    // The link is not news to the requester, so the original's updated_at stays.
    protected function link(Ticket $original, Ticket $escalation, Model $by, bool $started): void
    {
        $original->forceFill(['linked_ticket_id' => $escalation->getKey()])->saveQuietly(['timestamps' => false]);

        $this->write($original, $started ? ActivityType::Escalated : ActivityType::AddedToEscalation, ActivitySender::System, $by, data: ['escalation' => $escalation->getKey()]);
        $this->write($escalation, ActivityType::OriginalAdded, ActivitySender::System, $by, data: ['original' => $original->getKey()]);
        $escalation->forgetIsEscalation();
    }

    /*
     * TicketReassignment::assign(), through the observer. Who moved it decides
     * the note: taken, handed or reassigned by someone else.
     */
    protected function reassign(Ticket $ticket, ?Model $to, Model $by): void
    {
        $from = $ticket->assignee_id;

        if ((string) $from === (string) $to?->getKey()) {
            return;
        }

        $this->write($ticket, ActivityType::AssigneeChanged, ActivitySender::System, $by, data: ['from' => $from, 'to' => $to?->getKey()]);
        $this->updateTicket($ticket, ['assignee_id' => $to?->getKey()]);
    }

    // Written by the owner it reads as handed over; by the new owner, as taken over.
    protected function handOver(Ticket $escalation, Model $to, Model $by): void
    {
        $from = $escalation->submitter_id;

        $this->updateTicket($escalation, ['submitter_id' => $to->getKey()]);
        $escalation->unsetRelation('submitter');

        $this->write($escalation, ActivityType::HandedOver, ActivitySender::System, $by, data: ['from' => $from, 'to' => $to->getKey()]);
    }

    /**
     * The writer has read everything up to their own word, as the page leaves
     * them, or every seeded ticket would read as unread to everyone. The
     * second between rows keeps them in order when sorted by time.
     *
     * @param  array<string, mixed>|null  $data
     */
    protected function write(Ticket $ticket, ActivityType $type, ActivitySender $sender, ?Model $user, ?string $content = null, ?array $data = []): TicketActivity
    {
        $model = TicketPlugin::resolveModelClass(TicketActivity::class);

        $activity = (new $model)->forceFill([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $user?->getKey(),
            'type' => $type,
            'sender' => $sender,
            'content' => $content,
            'data' => $type === ActivityType::Message ? null : $data,
            'created_at' => $this->at,
            'updated_at' => $this->at,
        ]);

        $activity->save();

        if ($user !== null && $sender !== ActivitySender::System) {
            $this->markSeen($ticket, $user, $activity);
        }

        $this->later(seconds: 1);

        return $activity;
    }

    protected function markSeen(Ticket $ticket, Model $user, TicketActivity $activity): void
    {
        TicketPlugin::resolveModelClass(TicketUserState::class)::query()->updateOrCreate(
            ['ticket_id' => $ticket->getKey(), 'user_id' => $user->getKey()],
            ['last_seen_activity_id' => $activity->getKey()],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function updateTicket(Ticket $ticket, array $attributes): void
    {
        $ticket->forceFill([...$attributes, 'updated_at' => $this->at])->save();
    }

    protected function startAt(int $days = 0, ?int $hour = null, int $minute = 0, int $hours = 0): void
    {
        $at = CarbonImmutable::now()->subDays($days)->subHours($hours);

        $this->at = $hour === null ? $at : $at->setTime($hour, $minute);
    }

    protected function later(int $days = 0, int $hours = 0, int $minutes = 0, int $seconds = 0): void
    {
        $this->at = $this->at->addDays($days)->addHours($hours)->addMinutes($minutes)->addSeconds($seconds);

        if ($this->at->isFuture()) {
            $this->at = CarbonImmutable::now()->subSeconds(1);
        }
    }

    protected function status(string $panelId, string $direction): TicketStatus
    {
        /** @var TicketStatus */
        return $this->lookup(TicketStatus::class, $panelId, $direction)
            ?? throw new RuntimeException("No ticket statuses for panel \"{$panelId}\". Run tickets:seed --only=statuses first.");
    }

    /**
     * @param  class-string<Model>  $class
     */
    protected function lookup(string $class, string $panelId, string $direction = 'asc'): ?Model
    {
        $query = fn () => TicketPlugin::resolveModelClass($class)::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('panel', $panelId)
            ->orderBy('order', $direction);

        if ($this->tenantColumn() !== null) {
            $own = $query()->where($this->tenantColumn(), $this->tenantKey())->first();

            if ($own !== null) {
                return $own;
            }
        }

        return $query()->first();
    }

    protected function assigneeOf(Ticket $ticket): ?Model
    {
        return collect([...$this->supporters, ...$this->colleagues])
            ->first(fn (Model $user): bool => (string) $user->getKey() === (string) $ticket->assignee_id);
    }

    protected function requester(int $index): Model
    {
        return $this->requesters[$index % count($this->requesters)];
    }

    protected function supporter(int $index): Model
    {
        return $this->supporters[$index % count($this->supporters)];
    }

    protected function colleague(): ?Model
    {
        return $this->colleagues[0] ?? $this->supporters[1] ?? null;
    }

    protected function targetSupporter(): ?Model
    {
        $this->targetPanel();

        return $this->targetSupporters[0] ?? null;
    }

    protected function targetPanel(): string
    {
        return $this->targetPanelId ?? throw new InvalidArgumentException('Call escalatesTo() with the panel to escalate to first.');
    }

    protected function ensurePanel(string $panelId): void
    {
        if (! (Filament::getPanels()[$panelId] ?? null)?->hasPlugin(TicketPlugin::$id)) {
            throw new InvalidArgumentException("Panel \"{$panelId}\" does not have the ticket plugin registered.");
        }
    }

    /**
     * @return class-string<Ticket>
     */
    protected function ticketModel(): string
    {
        return TicketPlugin::resolveModelClass(Ticket::class);
    }

    protected function tenantColumn(): ?string
    {
        if (! config('padmission-tickets.tenancy.enabled')) {
            return null;
        }

        return Str::snake(class_basename(config('padmission-tickets.tenancy.tenancy_model'))).'_id';
    }

    protected function tenantKey(): int|string|null
    {
        return $this->tenant instanceof Model ? $this->tenant->getKey() : $this->tenant;
    }
}

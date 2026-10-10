<?php

namespace Padmission\Tickets\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketUserState;
use Padmission\Tickets\TicketPlugin;

/*
 * One SQL source, so the colour, the sort order and the "Needs You" count
 * cannot disagree.
 */
final class ConversationStateQuery
{
    public const WAITING_ON_CODES_RANKED_FIRST = ['you', 'you_requester', 'you_owner', 'unassigned', 'assignee_cannot_answer'];

    public const WAITING_ON_CODES_RANKED_SECOND = ['you_on_hold', 'colleague', 'colleague_on_hold', 'owner_colleague'];

    protected Ticket $ticket;

    protected Grammar $grammar;

    protected function __construct(protected ConversationViewer $viewer)
    {
        $this->ticket = new (TicketPlugin::resolveModelClass(Ticket::class));
        $this->grammar = $this->ticket->getConnection()->getQueryGrammar();
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function apply(Builder $query, ConversationViewer $viewer): Builder
    {
        $sql = new self($viewer);

        if ($query->getQuery()->columns === null) {
            $query->select($query->qualifyColumn('*'));
        }

        foreach ($sql->selects() as $alias => [$expression, $bindings]) {
            $query->selectRaw("{$expression} as {$sql->grammar->wrap($alias)}", $bindings);
        }

        return $query;
    }

    /**
     * Sorts by the selected rank rather than a second copy of its SQL.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function orderByRank(Builder $query, ConversationViewer $viewer, string $direction): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $alias = ' as '.$query->getQuery()->getGrammar()->wrap('conversation_rank');

        foreach ($query->getQuery()->columns ?? [] as $column) {
            if ($column instanceof Expression && str_ends_with((string) $column->getValue($query->getQuery()->getGrammar()), $alias)) {
                return $query->orderBy('conversation_rank', $direction);
            }
        }

        [$rank, $bindings] = self::rankExpression($viewer);

        return $query->orderByRaw("{$rank} {$direction}", $bindings);
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    public static function rankExpression(ConversationViewer $viewer): array
    {
        return (new self($viewer))->rank();
    }

    /**
     * @return array<string, array{0: string, 1: array<int, mixed>}>
     */
    protected function selects(): array
    {
        return [
            'conversation_waiting_on' => $this->waitingOn(),
            'conversation_marker' => $this->marker(),
            'conversation_rank' => $this->rank(),
            'conversation_is_new' => $this->isNew(),
            'conversation_owner_id' => $this->subquery($this->escalationQuery()->select('cs_e.submitter_id')),
            'conversation_escalation_open' => $this->sql('case when %s then 1 else 0 end', $this->escalationExists(fn (QueryBuilder $query) => $query->whereNull('cs_e.closed_at'))),
            'conversation_is_escalation' => $this->sql('case when %s then 1 else 0 end', $this->condition($this->ticket->newQueryWithoutScopes()->escalations())),
            'conversation_is_direct_question' => $this->sql('case when %s then 1 else 0 end', $this->condition($this->ticket->newQueryWithoutScopes()->directQuestions())),
        ];
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function waitingOn(): array
    {
        return $this->caseOverGroups(
            closed: $this->literal('closed'),
            value: fn (string $code): array => $this->literal($code),
        );
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function rank(): array
    {
        return $this->caseOverGroups(
            closed: $this->raw('3'),
            value: fn (string $code): array => $this->raw((string) $this->rankOf($code)),
            otherwise: $this->raw('2'),
            relayOwnedByViewer: $this->raw('0'),
        );
    }

    protected function rankOf(string $code): int
    {
        return match (true) {
            in_array($code, self::WAITING_ON_CODES_RANKED_FIRST, true) => 0,
            in_array($code, self::WAITING_ON_CODES_RANKED_SECOND, true) => 1,
            default => 2,
        };
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function marker(): array
    {
        if (! $this->viewer->isSupporter) {
            return $this->raw('null');
        }

        return $this->sql(
            'case when %s then case when %s and %s in (%s, %s, %s) then %s else %s end end',
            $this->inCurrentPanel(),
            $this->openInCurrentPanel(),
            $this->escalationState(),
            $this->literal('relay_mine'),
            $this->literal('relay_hold'),
            $this->literal('relay'),
            $this->literal('replied'),
            $this->subquery($this->escalationQuery()->selectRaw(sprintf(
                'case when %s is null then %s else %s end',
                $this->grammar->wrap('cs_e.closed_at'),
                $this->grammar->quoteString('escalated'),
                $this->grammar->quoteString('closed'),
            ))),
        );
    }

    /**
     * The two branches never both hold: one needs a row in the current
     * panel, the other a row in a panel it escalates to.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function isNew(): array
    {
        $lastSeen = $this->coalesced($this->newQuery()
            ->from((new (TicketPlugin::resolveModelClass(TicketUserState::class)))->getTable(), 'cs_s')
            ->select('cs_s.last_seen_activity_id')
            ->whereColumn('cs_s.ticket_id', $this->ticket->getQualifiedKeyName())
            ->where('cs_s.user_id', $this->viewer->userId));

        $requesterMessage = $this->latestMessage($this->ticket->getKeyName(), ActivitySender::User, fn (QueryBuilder $query) => $query
            ->where(fn (QueryBuilder $query) => $query
                ->whereNull('cs_a.user_id')
                ->orWhere('cs_a.user_id', '<>', $this->viewer->userId)));

        $branches = [
            $this->sql(
                'when %s and %s and (%s is null or %s <> %s) then %s',
                $this->inCurrentPanel(),
                $this->mine(),
                $this->column('submitter_id'),
                $this->column('submitter_id'),
                $this->binding($this->viewer->userId),
                $requesterMessage,
            ),
        ];

        if ($this->viewer->parentPanelIds !== []) {
            $branches[] = $this->sql(
                'when %s = %s and %s then %s',
                $this->column('submitter_id'),
                $this->binding($this->viewer->userId),
                $this->escalationFromCurrentPanel(),
                $this->latestMessage($this->ticket->getKeyName(), ActivitySender::Supporter),
            );
        }

        return $this->sql(
            'case when (case '.str_repeat('%s ', count($branches)).'else 0 end) > %s then 1 else 0 end',
            ...[...$branches, $lastSeen],
        );
    }

    /**
     * An original's branches are written twice, once knowing it is on hold
     * and once knowing it is not, so the costly relay and hold checks run
     * once per row instead of once per branch that needs them.
     *
     * @param  array{0: string, 1: array<int, mixed>}  $closed
     * @param  Closure(string): array{0: string, 1: array<int, mixed>}  $value
     * @param  array{0: string, 1: array<int, mixed>}|null  $otherwise
     * @param  array{0: string, 1: array<int, mixed>}|null  $relayOwnedByViewer
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function caseOverGroups(array $closed, Closure $value, ?array $otherwise = null, ?array $relayOwnedByViewer = null): array
    {
        $userTurn = $this->sql('%s = %s', $this->column('turn'), $this->literal(Turn::User->value));

        if (! $this->viewer->isSupporter) {
            return $this->sql(
                'case when %s is not null then %s when %s then %s else %s end',
                $this->column('closed_at'),
                $closed,
                $userTurn,
                $value('you_requester'),
                $value('support'),
            );
        }

        $original = fn (bool $onHold): array => $this->choose([
            ...($onHold ? [] : [
                [$this->sql('%s and %s', $userTurn, $this->submittedByViewer()), $value('you_requester')],
                [$userTurn, $value($this->viewer->receivesEscalations ? 'contact' : 'requester')],
            ]),
            [$this->mine(), $value($onHold ? 'you_on_hold' : 'you')],
            [$this->sql('%s is null', $this->column('assignee_id')), $value('unassigned')],
            [$this->sql('not (%s)', $this->assigneeInPool()), $value('assignee_cannot_answer')],
            [null, $value($onHold ? 'colleague_on_hold' : 'colleague')],
        ]);

        $parts = [
            [$this->sql('%s is not null', $this->column('closed_at')), $closed],
            [$this->inCurrentPanel(), $this->sql(
                'case %s '.($relayOwnedByViewer === null ? '' : 'when %s then %s ').'when %s then %s when %s then %s else %s end',
                $this->escalationState(),
                ...[
                    ...($relayOwnedByViewer === null ? [] : [$this->literal('relay_mine'), $relayOwnedByViewer]),
                    $this->literal('hold'),
                    $original(true),
                    $this->literal('relay_hold'),
                    $original(true),
                    $original(false),
                ],
            )],
        ];

        if ($this->viewer->parentPanelIds !== []) {
            $parts[] = [$this->escalationFromCurrentPanel(), $this->choose([
                [$this->sql('%s = %s', $this->column('turn'), $this->literal(Turn::Supporter->value)), $value('team')],
                [$this->submittedByViewer(), $value('you_owner')],
                [null, $value('owner_colleague')],
            ])];
        }

        if ($otherwise !== null) {
            $parts[] = [null, $otherwise];
        }

        return $this->choose($parts);
    }

    /**
     * A null condition is the fallback (else).
     *
     * @param  list<array{0: array{0: string, 1: array<int, mixed>}|null, 1: array{0: string, 1: array<int, mixed>}}>  $branches
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function choose(array $branches): array
    {
        $parts = array_map(fn (array $branch): array => $branch[0] === null
            ? $this->sql('else %s', $branch[1])
            : $this->sql('when %s then %s', $branch[0], $branch[1]), $branches);

        return $this->sql('case '.str_repeat('%s ', count($parts)).'end', ...$parts);
    }

    /**
     * On an original, read once from its escalation: "relay_mine" while a
     * reply from the team waits for the viewer to pass it on; "relay_hold"
     * while it waits for someone else and the requester already has the
     * organization's latest message, so the row is on hold for the viewer;
     * "relay" while it waits for someone else otherwise; "hold" while the row
     * is on hold; null otherwise.
     *
     * A reply is waiting while the team's latest message on the escalation is
     * newer than the organization's latest on this original and the owner's
     * latest on the escalation. An owner who asked the original themselves
     * reads the reply there, so nothing waits for them to pass on.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function escalationState(): array
    {
        $owner = $this->grammar->wrap('cs_e.submitter_id');
        $submitter = $this->grammar->wrap($this->ticket->qualifyColumn('submitter_id'));

        $hold = $this->sql(
            '%s = %s and %s is null and %s = %s',
            $this->column('turn'),
            $this->literal(Turn::Supporter->value),
            $this->raw($this->grammar->wrap('cs_e.closed_at')),
            $this->subquery($this->newQuery()
                ->from((new (TicketPlugin::resolveModelClass(TicketActivity::class)))->getTable(), 'cs_l')
                ->select('cs_l.sender')
                ->whereColumn('cs_l.ticket_id', $this->ticket->getQualifiedKeyName())
                ->where('cs_l.type', ActivityType::Message->value)
                ->whereIn('cs_l.sender', [ActivitySender::User->value, ActivitySender::Supporter->value])
                ->orderByDesc('cs_l.id')
                ->limit(1)),
            $this->literal(ActivitySender::Supporter->value),
        );

        $state = $this->sql(
            'case when (%s is null or %s is null or %s <> %s) and (%s is not null or %s is not null) and %s > %s(%s, %s) '
                .'then case when %s = %s then %s when %s then %s else %s end '
                .'when %s then %s end',
            $this->raw($owner),
            $this->raw($submitter),
            $this->raw($owner),
            $this->raw($submitter),
            $this->raw($owner),
            $this->raw($submitter),
            $this->latestMessage('linked_ticket_id', ActivitySender::Supporter),
            $this->raw($this->ticket->getConnection()->getDriverName() === 'sqlite' ? 'max' : 'greatest'),
            $this->latestMessage($this->ticket->getKeyName(), ActivitySender::Supporter),
            $this->latestMessage('linked_ticket_id', ActivitySender::User),
            $this->raw($owner),
            $this->binding($this->viewer->userId),
            $this->literal('relay_mine'),
            $hold,
            $this->literal('relay_hold'),
            $this->literal('relay'),
            $hold,
            $this->literal('hold'),
        );

        return $this->subquery($this->escalationQuery()->selectRaw($state[0], $state[1]));
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function escalationFromCurrentPanel(): array
    {
        return $this->condition($this->ticket->newQueryWithoutScopes()->escalationsFrom($this->viewer->panelId));
    }

    /**
     * A model scope's conditions, so this SQL and the model's own checks
     * agree.
     *
     * @param  Builder<Ticket>  $query
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function condition(Builder $query): array
    {
        $query = $query->toBase();

        return ['('.preg_replace('/^where /', '', $query->getGrammar()->compileWheres($query)).')', $query->getBindings()];
    }

    /**
     * A pool that keeps one account per person misses that person's other
     * accounts, which may belong to another tenant, so those are loaded through
     * the panel's relationship scopes and matched by email, whatever its case,
     * as ConversationViewer matches the viewer.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function assigneeInPool(): array
    {
        if ($this->viewer->supporterPool === []) {
            return $this->raw('(1 = 0)');
        }

        $userModel = TicketPlugin::resolveUserModelClass();

        if ($this->viewer->supporterMatchColumn === (new $userModel)->getKeyName()) {
            return $this->sql('%s in (%s)', $this->column('assignee_id'), $this->bindings($this->viewer->supporterPool));
        }

        $assignee = $userModel::query();
        $modifier = TicketPlugin::get($this->viewer->panelId)->getRelationshipScopeModifier();

        if ($modifier) {
            app()->call($modifier, ['relation' => $assignee, 'model' => 'assignee']);
        }

        $assignee
            ->selectRaw('1')
            ->whereColumn($assignee->getModel()->getQualifiedKeyName(), $this->ticket->qualifyColumn('assignee_id'))
            ->whereIn(
                DB::raw('lower('.$this->grammar->wrap($assignee->qualifyColumn($this->viewer->supporterMatchColumn)).')'),
                array_map(fn (int|string $value): string => mb_strtolower((string) $value), $this->viewer->supporterPool),
            );

        return $this->sql('exists %s', $this->subquery($assignee->toBase()));
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function mine(): array
    {
        if ($this->viewer->assigneeIds === []) {
            return $this->raw('(1 = 0)');
        }

        return $this->sql('%s in (%s)', $this->column('assignee_id'), $this->bindings($this->viewer->assigneeIds));
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function submittedByViewer(): array
    {
        if ($this->viewer->userId === null) {
            return $this->raw('(1 = 0)');
        }

        return $this->sql('%s = %s', $this->column('submitter_id'), $this->binding($this->viewer->userId));
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function inCurrentPanel(): array
    {
        return $this->sql('%s = %s', $this->column('panel'), $this->binding($this->viewer->panelId));
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function openInCurrentPanel(): array
    {
        return $this->sql('%s is null and %s', $this->column('closed_at'), $this->inCurrentPanel());
    }

    /*
     * A deleted escalation leaves its originals not escalated.
     */
    protected function escalationQuery(): QueryBuilder
    {
        return $this->newQuery()
            ->from($this->ticket->getTable(), 'cs_e')
            ->whereColumn('cs_e.'.$this->ticket->getKeyName(), $this->ticket->qualifyColumn('linked_ticket_id'))
            ->whereNull('cs_e.'.$this->ticket->getDeletedAtColumn());
    }

    /**
     * @param  (Closure(QueryBuilder): mixed)|null  $constraints
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function escalationExists(?Closure $constraints = null): array
    {
        return $this->sql('exists %s', $this->subquery($this->escalationQuery()->selectRaw('1')->when($constraints !== null, $constraints)));
    }

    /**
     * Ids, not times, because they increase across tickets and never tie.
     *
     * @param  (Closure(QueryBuilder): mixed)|null  $constraints
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function latestMessage(string $ticketColumn, ActivitySender $sender, ?Closure $constraints = null): array
    {
        return $this->coalesced($this->newQuery()
            ->from((new (TicketPlugin::resolveModelClass(TicketActivity::class)))->getTable(), 'cs_a')
            ->selectRaw('max('.$this->grammar->wrap('cs_a.id').')')
            ->whereColumn('cs_a.ticket_id', $this->ticket->qualifyColumn($ticketColumn))
            ->where('cs_a.type', ActivityType::Message->value)
            ->where('cs_a.sender', $sender->value)
            ->when($constraints !== null, $constraints));
    }

    protected function newQuery(): QueryBuilder
    {
        return $this->ticket->getConnection()->query();
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function coalesced(QueryBuilder $query): array
    {
        return $this->sql('coalesce(%s, 0)', $this->subquery($query));
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function subquery(QueryBuilder $query): array
    {
        return ['('.$query->toSql().')', $query->getBindings()];
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function column(string $column): array
    {
        return $this->raw($this->grammar->wrap($this->ticket->qualifyColumn($column)));
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function literal(string $value): array
    {
        return $this->raw($this->grammar->quoteString($value));
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function binding(mixed $value): array
    {
        return ['?', [$value]];
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function bindings(array $values): array
    {
        return [implode(', ', array_fill(0, count($values), '?')), array_values($values)];
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function raw(string $sql): array
    {
        return [$sql, []];
    }

    /**
     * Placeholders are filled left to right, so bindings stay in SQL order.
     *
     * @param  array{0: string, 1: array<int, mixed>}  ...$parts
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function sql(string $template, array ...$parts): array
    {
        return [
            sprintf($template, ...array_column($parts, 0)),
            array_merge(...array_column($parts, 1)),
        ];
    }
}

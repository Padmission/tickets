<?php

namespace Padmission\Tickets\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
    public const WAITING_ON_CODES_RANKED_FIRST = ['you', 'you_requester', 'you_owner', 'needs_assignment'];

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
            'conversation_is_escalation' => $this->sql('case when %s or %s then 1 else 0 end', $this->originalsExist(), $this->originalAddedExists()),
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
            first: $this->viewer->isSupporter && $this->viewer->userId !== null
                ? [[$this->sql('%s and %s', $this->openInCurrentPanel(), $this->relayPending(ownedByViewer: true)), $this->raw('0')]]
                : [],
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
            'case when %s and %s then case when %s then %s when %s then %s else %s end end',
            $this->openInCurrentPanel(),
            $this->escalationExists(),
            $this->relayPending(),
            $this->literal('replied'),
            $this->escalationExists(fn (QueryBuilder $query) => $query->whereNotNull('cs_e.closed_at')),
            $this->literal('closed'),
            $this->literal('escalated'),
        );
    }

    /**
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
                'when %s and %s and (%s is null or %s <> %s) and %s > %s then 1',
                $this->inCurrentPanel(),
                $this->mine(),
                $this->column('submitter_id'),
                $this->column('submitter_id'),
                $this->binding($this->viewer->userId),
                $requesterMessage,
                $lastSeen,
            ),
        ];

        if ($this->viewer->parentPanelIds !== []) {
            $branches[] = $this->sql(
                'when %s = %s and %s and %s > %s then 1',
                $this->column('submitter_id'),
                $this->binding($this->viewer->userId),
                $this->escalationFromCurrentPanel(),
                $this->latestMessage($this->ticket->getKeyName(), ActivitySender::Supporter),
                $lastSeen,
            );
        }

        return $this->sql('case '.str_repeat('%s ', count($branches)).'else 0 end', ...$branches);
    }

    /**
     * A null condition is the fallback (else).
     *
     * @return list<array{0: array{0: string, 1: array<int, mixed>}|null, 1: list<array{0: array{0: string, 1: array<int, mixed>}|null, 1: string}>}>
     */
    protected function groups(): array
    {
        $userTurn = $this->sql('%s = %s', $this->column('turn'), $this->literal(Turn::User->value));

        if (! $this->viewer->isSupporter) {
            return [[null, [[$userTurn, 'you_requester'], [null, 'support']]]];
        }

        $onHold = $this->onHold();

        $groups = [[$this->inCurrentPanel(), [
            [$this->sql('%s and %s', $userTurn, $this->submittedByViewer()), 'you_requester'],
            [$userTurn, $this->viewer->receivesEscalations ? 'contact' : 'requester'],
            [$this->sql('%s and %s', $this->mine(), $onHold), 'you_on_hold'],
            [$this->mine(), 'you'],
            [$this->sql('(%s is null or not (%s))', $this->column('assignee_id'), $this->assigneeInPool()), 'needs_assignment'],
            [$onHold, 'colleague_on_hold'],
            [null, 'colleague'],
        ]]];

        if ($this->viewer->parentPanelIds !== []) {
            $groups[] = [$this->escalationFromCurrentPanel(), [
                [$this->sql('%s = %s', $this->column('turn'), $this->literal(Turn::Supporter->value)), 'team'],
                [$this->submittedByViewer(), 'you_owner'],
                [null, 'owner_colleague'],
            ]];
        }

        return $groups;
    }

    /**
     * @param  array{0: string, 1: array<int, mixed>}  $closed
     * @param  Closure(string): array{0: string, 1: array<int, mixed>}  $value
     * @param  list<array{0: array{0: string, 1: array<int, mixed>}, 1: array{0: string, 1: array<int, mixed>}}>  $first
     * @param  array{0: string, 1: array<int, mixed>}|null  $otherwise
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function caseOverGroups(array $closed, Closure $value, array $first = [], ?array $otherwise = null): array
    {
        $parts = [$this->sql('when %s is not null then %s', $this->column('closed_at'), $closed)];

        foreach ($first as [$condition, $result]) {
            $parts[] = $this->sql('when %s then %s', $condition, $result);
        }

        $groups = $this->groups();

        foreach ($groups as [$condition, $branches]) {
            $inner = [];

            foreach ($branches as [$branchCondition, $code]) {
                $inner[] = $branchCondition === null
                    ? $this->sql('else %s', $value($code))
                    : $this->sql('when %s then %s', $branchCondition, $value($code));
            }

            $choice = $this->sql('case '.str_repeat('%s ', count($inner)).'end', ...$inner);

            $parts[] = $condition === null
                ? $this->sql('else %s', $choice)
                : $this->sql('when %s then %s', $condition, $choice);
        }

        if ($otherwise !== null && end($groups)[0] !== null) {
            $parts[] = $this->sql('else %s', $otherwise);
        }

        return $this->sql('case '.str_repeat('%s ', count($parts)).'end', ...$parts);
    }

    /**
     * An owner who asked the original themselves reads the reply there, so
     * nothing is pending for them to pass on.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function relayPending(bool $ownedByViewer = false): array
    {
        $team = $this->latestMessage('linked_ticket_id', ActivitySender::Supporter);

        return $this->sql(
            '(%s and %s > %s and %s > %s)',
            $this->escalationExists(fn (QueryBuilder $query) => $query
                ->where(fn (QueryBuilder $query) => $query
                    ->whereNull('cs_e.submitter_id')
                    ->orWhereNull($this->ticket->qualifyColumn('submitter_id'))
                    ->orWhereColumn('cs_e.submitter_id', '<>', $this->ticket->qualifyColumn('submitter_id')))
                ->where(fn (QueryBuilder $query) => $query
                    ->whereNotNull('cs_e.submitter_id')
                    ->orWhereNotNull($this->ticket->qualifyColumn('submitter_id')))
                ->when($ownedByViewer, fn (QueryBuilder $query) => $query->where('cs_e.submitter_id', $this->viewer->userId))),
            $team,
            $this->latestMessage($this->ticket->getKeyName(), ActivitySender::Supporter),
            $team,
            $this->latestMessage('linked_ticket_id', ActivitySender::User),
        );
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function onHold(): array
    {
        return $this->sql(
            '(%s = %s and %s and not %s and %s > %s)',
            $this->column('turn'),
            $this->literal(Turn::Supporter->value),
            $this->escalationExists(fn (QueryBuilder $query) => $query->whereNull('cs_e.closed_at')),
            $this->relayPending(),
            $this->latestMessage($this->ticket->getKeyName(), ActivitySender::Supporter),
            $this->latestMessage($this->ticket->getKeyName(), ActivitySender::User),
        );
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function escalationFromCurrentPanel(): array
    {
        return $this->sql(
            '(%s in (%s) and (%s or (%s = %s and (%s or %s))))',
            $this->column('panel'),
            $this->bindings($this->viewer->parentPanelIds),
            $this->originalsExist($this->viewer->panelId),
            $this->column('source_panel'),
            $this->binding($this->viewer->panelId),
            $this->originalsExist(),
            $this->originalAddedExists(),
        );
    }

    /**
     * Deleted originals still count, as in Ticket::isEscalation().
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function originalsExist(?string $panelId = null): array
    {
        return $this->sql('exists %s', $this->subquery($this->newQuery()
            ->from($this->ticket->getTable(), 'cs_c')
            ->selectRaw('1')
            ->whereColumn('cs_c.linked_ticket_id', $this->ticket->getQualifiedKeyName())
            ->when($panelId !== null, fn (QueryBuilder $query) => $query->where('cs_c.panel', $panelId))));
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function originalAddedExists(): array
    {
        return $this->sql('exists %s', $this->subquery($this->newQuery()
            ->from((new (TicketPlugin::resolveModelClass(TicketActivity::class)))->getTable(), 'cs_o')
            ->selectRaw('1')
            ->whereColumn('cs_o.ticket_id', $this->ticket->getQualifiedKeyName())
            ->where('cs_o.type', ActivityType::OriginalAdded->value)));
    }

    /**
     * A pool that keeps one account per person misses that person's other
     * accounts, which may belong to another tenant, so those are loaded through
     * the panel's relationship scopes and matched by email.
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
            ->whereIn($assignee->qualifyColumn($this->viewer->supporterMatchColumn), $this->viewer->supporterPool);

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

<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationRecipient;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketReopenedEvent;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseAsDuplicateAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReopenTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Services\TicketCloser;
use Padmission\Tickets\Services\TicketDuplicates;
use Padmission\Tickets\Tests\Fixtures\Models\CustomTicket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Queue::fake();
    (new TicketStatusSeeder)->run();
    $this->staff = $this->login();
    $this->requester = User::factory()->create();
    $this->original = Ticket::factory()->open()->state(['disposition_id' => null])->create(['subject' => 'The original problem']);
    $this->duplicate = Ticket::factory()->open()->state(['disposition_id' => null])->create(['subject' => 'The same problem', 'submitter_id' => $this->requester->id]);
});

function duplicateAction(): TestAction
{
    return TestAction::make(CloseAsDuplicateAction::class);
}

it('closes through the ticket page with its disposition and link, keeping both conversations', function () {
    $disposition = TicketDisposition::factory()->create(['display_name' => 'Duplicate']);
    $message = $this->duplicate->addTicketActivity(ActivityType::Message, content: 'Still broken');
    $attachment = TicketAttachment::factory()->create(['ticket_id' => $this->duplicate->id, 'activity_id' => $message->id]);
    $originalStatus = $this->original->status_id;

    Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])
        ->assertActionVisible(duplicateAction())
        ->callAction(duplicateAction(), ['original' => $this->original->id, 'disposition' => $disposition->id])
        ->assertHasNoActionErrors()
        ->assertNotified('Ticket closed as duplicate');

    expect($this->duplicate->refresh())
        ->duplicate_of_ticket_id->toBe($this->original->id)
        ->isClosed->toBeTrue()
        ->closed_by->toBe($this->staff->id)
        ->disposition_id->toBe($disposition->id)
        ->status_id->toBe(TicketStatus::getClosedStatus()->id)
        ->and($this->original->refresh()->status_id)->toBe($originalStatus)
        ->and($message->refresh()->ticket_id)->toBe($this->duplicate->id)
        ->and($attachment->refresh()->ticket_id)->toBe($this->duplicate->id)
        ->and($this->duplicate->ticketActivities()->where('type', ActivityType::Closed)->count())->toBe(1);
});

it('requires the normal disposition when one is available', function () {
    TicketDisposition::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])
        ->callAction(duplicateAction(), ['original' => $this->original->id])
        ->assertHasActionErrors(['disposition' => 'required']);

    expect($this->duplicate->refresh()->isClosed)->toBeFalse()
        ->and($this->duplicate->duplicate_of_ticket_id)->toBeNull();
});

it('allows no disposition when the ticket panel has none', function () {
    TicketDisposition::factory()->create(['panel' => 'test2']);
    resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id);

    expect($this->duplicate->refresh()->isClosed)->toBeTrue()
        ->and($this->duplicate->disposition_id)->toBeNull();
});

it('refuses a disposition from another panel or a deleted one', function (bool $deleted) {
    $disposition = TicketDisposition::factory()->create(['panel' => $deleted ? 'test' : 'test2']);
    if ($deleted) {
        $disposition->delete();
    }

    expect(fn () => resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id, $disposition->id))
        ->toThrow(ValidationException::class);
    expect($this->duplicate->refresh()->duplicate_of_ticket_id)->toBeNull();
})->with([false, true]);

it('uses the same authorization as close and hides the action on closed or foreign-panel tickets', function () {
    Gate::policy(Ticket::class, TicketPolicy::class);

    Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])->assertActionVisible(duplicateAction());

    $this->actingAs($this->requester);
    Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])->assertActionHidden(duplicateAction());
    expect(fn () => resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id))
        ->toThrow(HttpException::class);

    $this->actingAs($this->staff);
    $this->duplicate->close();
    Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])->assertActionHidden(duplicateAction());

    Filament::setCurrentPanel(Filament::getPanel('test2'));
    expect(resolve(TicketCloser::class)->canClose($this->original))->toBeFalse();
});

it('resolves a chain of duplicates to the root and records root references on both tickets', function () {
    $middle = Ticket::factory()->closed()->state(['disposition_id' => null])->create(['duplicate_of_ticket_id' => $this->original->id]);
    $selected = Ticket::factory()->closed()->state(['disposition_id' => null])->create(['duplicate_of_ticket_id' => $middle->id]);

    Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])
        ->callAction(duplicateAction(), ['original' => $selected->id])->assertHasNoActionErrors();

    expect($this->duplicate->refresh()->duplicate_of_ticket_id)->toBe($this->original->id)
        ->and($this->original->ticketActivities()->where('type', ActivityType::DuplicatedBy)->sole()->data)->toBe(['ticket' => $this->duplicate->id])
        ->and($selected->ticketActivities()->where('type', ActivityType::DuplicatedBy)->count())->toBe(0);
});

it('writes linked system notes on both tickets and renders them in the timeline', function () {
    resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id);
    $closed = $this->duplicate->ticketActivities()->where('type', ActivityType::ClosedAsDuplicate)->sole();
    $duplicated = $this->original->ticketActivities()->where('type', ActivityType::DuplicatedBy)->sole();

    expect($closed->sender)->toBe(ActivitySender::System)
        ->and($closed->data)->toBe(['ticket' => $this->original->id])
        ->and($closed->content)->toContain('Closed as duplicate of <a', '#'.$this->original->id, e(TicketResource::getUrl('view', ['record' => $this->original])))
        ->and($duplicated->sender)->toBe(ActivitySender::System)
        ->and($duplicated->content)->toContain('Duplicated by <a', '#'.$this->duplicate->id);

    foreach ([$this->duplicate, $this->original] as $ticket) {
        $messages = $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))->assertOk()->json('messages');
        expect(collect($messages)->firstWhere('content', $ticket->is($this->duplicate) ? $closed->content : $duplicated->content)['side'])->toBe('system');
    }
});

it('excludes itself in the picker and rejects a forged self selection', function () {
    Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])
        ->mountAction(duplicateAction())
        ->assertFormFieldExists('original', fn ($field): bool => array_key_exists($this->original->id, $field->getOptions()) && ! array_key_exists($this->duplicate->id, $field->getOptions()))
        ->callMountedAction(['original' => $this->duplicate->id])
        ->assertHasActionErrors(['original']);

    expect($this->duplicate->refresh()->isClosed)->toBeFalse();
});

it('searches the picker by subject or ticket number with or without a hash', function () {
    Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])
        ->mountAction(duplicateAction())
        ->assertFormFieldExists('original', function ($field): bool {
            foreach (['The original problem', (string) $this->original->id, '#'.$this->original->id] as $search) {
                if (! array_key_exists($this->original->id, $field->getSearchResults($search))) {
                    return false;
                }
            }

            return $field->getSearchResults('no matching subject') === [];
        });
});

it('rechecks a selected original that was escalated while the dialog was open', function () {
    $page = Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])->mountAction(duplicateAction());
    $this->original->forceFill(['linked_ticket_id' => escalationFrom(attributes: ['disposition_id' => null])->id])->saveQuietly();

    $page->callMountedAction(['original' => $this->original->id])->assertHasActionErrors(['original']);

    expect($this->duplicate->refresh()->isClosed)->toBeFalse()
        ->and($this->original->ticketActivities()->where('type', ActivityType::DuplicatedBy)->count())->toBe(0);
});

it('excludes other panels and rejects forged selections including deleted originals', function (bool $deleted) {
    $other = Ticket::factory()->open()->state(['disposition_id' => null])->create(['panel' => $deleted ? 'test' : 'test2']);
    if ($deleted) {
        $other->delete();
    }

    expect(resolve(TicketDuplicates::class)->candidates($this->duplicate)->pluck('id'))->not->toContain($other->id);
    expect(fn () => resolve(TicketDuplicates::class)->close($this->duplicate, $other->id))->toThrow(ValidationException::class);
    expect($this->duplicate->refresh()->isClosed)->toBeFalse();
})->with([false, true]);

it('refuses cycles, including a selected ticket whose root would be itself', function (bool $includesSource) {
    $other = Ticket::factory()->closed()->state(['disposition_id' => null])->create();
    $this->original->forceFill(['duplicate_of_ticket_id' => $includesSource ? $this->duplicate->id : $other->id])->saveQuietly();
    $other->forceFill(['duplicate_of_ticket_id' => $this->original->id])->saveQuietly();

    expect(fn () => resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id))->toThrow(ValidationException::class);
    expect($this->duplicate->refresh()->isClosed)->toBeFalse();
})->with([false, true]);

it('lists duplicates on the original with links and shows the original on the duplicate', function () {
    resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id);
    $second = Ticket::factory()->open()->state(['disposition_id' => null])->create(['subject' => '<script>Another duplicate</script>']);
    resolve(TicketDuplicates::class)->close($second, $this->original->id);

    expect($this->original->duplicates->modelKeys())->toBe([$this->duplicate->id, $second->id])
        ->and($this->duplicate->duplicateOriginal->is($this->original))->toBeTrue();

    Livewire::test(ViewTicket::class, ['record' => $this->original->id])
        ->assertSee('Duplicates')
        ->assertSeeHtml(e(TicketResource::getUrl('view', ['record' => $this->duplicate])))
        ->assertSeeHtml(e(TicketResource::getUrl('view', ['record' => $second])))
        ->assertSeeHtml(e($second->subject));

    Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])
        ->assertSee('Duplicate of')
        ->assertSeeHtml(e(TicketResource::getUrl('view', ['record' => $this->original])));
});

it('adds a small Duplicate badge only on duplicate rows', function () {
    resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id);

    $table = Livewire::test(ListTickets::class)->removeTableFilter('open');
    $column = $table->instance()->getTable()->getColumn('subject');
    $column->record($this->duplicate->refresh());
    expect((string) $column->getSuffix())->toContain('Duplicate', 'fi-badge', 'pad-ti-marker');

    $column->record($this->original);
    expect((string) $column->getSuffix())->not->toContain('Duplicate');
    $table->assertSee('Duplicate');
});

it('clears the link and records its former original when reopened by action, model, status or reply', function (string $path) {
    resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id);

    if ($path === 'reply') {
        $this->actingAs($this->requester);
    }

    match ($path) {
        'action' => Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])->callAction(ReopenTicketAction::class)->assertHasNoActionErrors(),
        'model' => $this->duplicate->reopen(),
        'status' => $this->duplicate->update(['status_id' => TicketStatus::getOpenStatusFor($this->duplicate)->id]),
        'reply' => $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->duplicate]), ['content' => 'It is still broken.', 'reopen' => true])->assertOk(),
    };

    expect($this->duplicate->refresh()->duplicate_of_ticket_id)->toBeNull()
        ->and($this->duplicate->isClosed)->toBeFalse()
        ->and($this->duplicate->ticketActivities()->where('type', ActivityType::DuplicateRemoved)->sole()->content)
        ->toContain('Duplicate link to', '#'.$this->original->id, 'cleared on reopening')
        ->and($this->original->duplicates()->count())->toBe(0);
})->with(['action', 'model', 'status', 'reply']);

it('never notifies the requester of duplicate closure or reopening even with Both configured', function () {
    Notification::fake();
    TicketPlugin::get()->notificationConfiguration(
        NotificationConfiguration::make()
            ->on(TicketClosedEvent::class, fn () => NotificationRecipient::Both)
            ->on(TicketReopenedEvent::class, fn () => NotificationRecipient::Both)
    );
    // The close event fires before the ClosedAsDuplicate history note is written.
    Event::listen(TicketClosedEvent::class, function (TicketClosedEvent $event) {
        expect(resolve(NotificationRecipientService::class)->getNotificationRecipients($event)->pluck('id'))
            ->not->toContain($this->requester->id);
    });

    resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id);
    $this->duplicate->reopen();
    $service = resolve(NotificationRecipientService::class);

    foreach ([new TicketClosedEvent($this->duplicate, $this->staff), new TicketReopenedEvent($this->duplicate, $this->staff)] as $event) {
        expect($service->getNotificationRecipients($event)->pluck('id'))->not->toContain($this->requester->id)
            ->and((new TicketNotification($this->duplicate, $event))->shouldSend($this->requester))->toBeFalse();
        (new NotificationJob($this->requester, $this->duplicate, $event))->handle();
    }

    foreach ([ActivityType::ClosedAsDuplicate, ActivityType::DuplicatedBy, ActivityType::DuplicateRemoved, ActivityType::Reopened, ActivityType::Closed] as $type) {
        $event = new TicketActivityEvent($this->duplicate, $type, actor: $this->staff);
        expect($service->getNotificationRecipients($event)->pluck('id'))->not->toContain($this->requester->id);
        (new NotificationJob($this->requester, $this->duplicate, $event))->handle();
    }

    // System notes must not turn into a delayed Ticket updated email either.
    expect((new TicketNotification($this->duplicate, new TicketActivityEvent($this->duplicate, ActivityType::StatusChanged, actor: $this->staff)))->shouldSend($this->requester))->toBeFalse();

    $originalRequester = $this->original->submitter;
    (new NotificationJob($originalRequester, $this->original, new TicketActivityEvent($this->original, ActivityType::DuplicatedBy, actor: $this->staff)))->handle();
    Notification::assertNothingSentTo($this->requester);
    Notification::assertNothingSentTo($originalRequester);
});

it('blocks either an escalation or its original at any point in the chosen chain', function (string $blocked) {
    $target = match ($blocked) {
        'source escalation', 'source original' => $this->duplicate,
        'root escalation' => Ticket::factory()->closed()->state(['disposition_id' => null])->create(),
        default => $this->original,
    };

    if (str_contains($blocked, 'original')) {
        $target->forceFill(['linked_ticket_id' => escalationFrom(attributes: ['disposition_id' => null])->id])->saveQuietly();
    } else {
        $target->addTicketActivity(ActivityType::AskedDirectly, ActivitySender::System);
    }
    if ($blocked === 'root escalation') {
        $this->original->forceFill(['duplicate_of_ticket_id' => $target->id])->saveQuietly();
    }

    expect(fn () => resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id))
        ->toThrow(ValidationException::class, 'Tickets involved in an escalation');
    expect($this->duplicate->refresh()->isClosed)->toBeFalse()
        ->and($this->duplicate->duplicate_of_ticket_id)->toBeNull();

    if (str_starts_with($blocked, 'source')) {
        Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])
            ->assertActionDisabled(duplicateAction())
            ->assertActionExists(duplicateAction(), fn (CloseAsDuplicateAction $action): bool => str_contains($action->getTooltip(), 'Add to an existing escalation'));
    } else {
        Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])
            ->callAction(duplicateAction(), ['original' => $this->original->id])
            ->assertHasActionErrors(['original']);
    }
})->with(['source escalation', 'source original', 'selected escalation', 'selected original', 'root escalation']);

it('uses the host ticket model for duplicate relationships', function () {
    config()->set('padmission-tickets.models', [Authenticatable::class => User::class, Ticket::class => CustomTicket::class]);
    $original = CustomTicket::factory()->open()->state(['disposition_id' => null])->create();
    $duplicate = CustomTicket::factory()->open()->state(['disposition_id' => null])->create();
    resolve(TicketDuplicates::class)->close($duplicate, $original->id);

    expect($duplicate->duplicateOriginal)->toBeInstanceOf(CustomTicket::class)
        ->and($original->duplicates->sole())->toBeInstanceOf(CustomTicket::class)
        ->and(CustomTicket::query()->with('duplicates')->find($original->id)->duplicates->sole()->id)->toBe($duplicate->id)
        ->and(CustomTicket::query()->with('duplicateOriginal')->find($duplicate->id)->duplicateOriginal->id)->toBe($original->id);
});

it('clears every incoming duplicate link through model deletion without changing closure or history', function (bool $force) {
    resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id);
    $trashedDuplicate = Ticket::factory()->open()->state(['disposition_id' => null])->create();
    resolve(TicketDuplicates::class)->close($trashedDuplicate, $this->original->id);
    $trashedDuplicate->delete();
    $otherPanel = Ticket::factory()->closed()->state(['disposition_id' => null])->create([
        'panel' => 'test2', 'duplicate_of_ticket_id' => $this->original->id,
    ]);
    $closedAt = $this->duplicate->closed_at;
    $history = $this->duplicate->ticketActivities()->pluck('data', 'id')->all();

    // Cleanup follows the deleted model, even when the viewing panel's scopes hide its duplicates.
    Filament::setCurrentPanel('test2');
    $force ? $this->original->forceDelete() : $this->original->delete();

    foreach ([$this->duplicate, $trashedDuplicate, $otherPanel] as $duplicate) {
        expect($duplicate->refresh()->duplicate_of_ticket_id)->toBeNull();
    }

    Filament::setCurrentPanel('test');
    expect($this->duplicate->isClosed)->toBeTrue()
        ->and($this->duplicate->closed_at)->toEqual($closedAt)
        ->and($this->duplicate->ticketActivities()->pluck('data', 'id')->all())->toBe($history);

    Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])->assertDontSee('Duplicate of');
    $list = Livewire::test(ListTickets::class)->removeTableFilter('open');
    $column = $list->instance()->getTable()->getColumn('subject')->record($this->duplicate);
    expect((string) $column->getSuffix())->not->toContain('Duplicate');
})->with(['soft delete' => false, 'force delete' => true]);

it('renders the historical duplicate note as plain text with the original gone', function () {
    resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id);
    $note = $this->duplicate->ticketActivities()->where('type', ActivityType::ClosedAsDuplicate)->sole();
    $noteData = $note->data;
    $this->original->delete();

    // A host may include its trash in the query; a deleted original still gets no history link.
    TicketPlugin::get()->customizeTicketQuery(fn (Builder $query): Builder => $query->withTrashed());

    $text = 'Closed as duplicate of #'.$this->original->id;
    expect($note->refresh()->content)->toBe($text)
        ->and($note->data)->toBe($noteData);
    $messages = $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->duplicate]))->assertOk()->json('messages');
    expect(collect($messages)->firstWhere('id', $note->id)['content'])->toBe($text);
});

it('does not relink duplicates or restore their badge when the original is restored', function () {
    resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id);
    $this->original->delete();
    $this->original->restore();

    expect($this->duplicate->refresh()->duplicate_of_ticket_id)->toBeNull()
        ->and($this->original->duplicates()->count())->toBe(0);
    Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])->assertDontSee('Duplicate of');
    $list = Livewire::test(ListTickets::class)->removeTableFilter('open');
    $column = $list->instance()->getTable()->getColumn('subject')->record($this->duplicate);
    expect((string) $column->getSuffix())->not->toContain('Duplicate');
});

it('uses a former duplicate as the new root after its original is soft-deleted', function () {
    resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id);
    $this->original->delete();
    $next = Ticket::factory()->open()->state(['disposition_id' => null])->create();

    Livewire::test(ViewTicket::class, ['record' => $next->id])
        ->callAction(duplicateAction(), ['original' => $this->duplicate->id])->assertHasNoActionErrors();

    expect($next->refresh()->duplicate_of_ticket_id)->toBe($this->duplicate->id);
});

it('never offers or resolves trashed originals even when the host query includes trash', function () {
    $this->original->delete();
    TicketPlugin::get()->customizeTicketQuery(fn (Builder $query): Builder => $query->withTrashed());

    Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])
        ->mountAction(duplicateAction())
        ->assertFormFieldExists('original', fn ($field): bool => ! array_key_exists($this->original->id, $field->getOptions())
            && ! array_key_exists($this->original->id, $field->getSearchResults('original')));

    expect(fn () => resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id))->toThrow(ValidationException::class);

    // A stale link from before this lifecycle fix must never resolve through a trashed root.
    $stale = Ticket::factory()->closed()->state(['disposition_id' => null])->create(['duplicate_of_ticket_id' => $this->original->id]);
    expect(fn () => resolve(TicketDuplicates::class)->close($this->duplicate, $stale->id))->toThrow(ValidationException::class);
    expect($this->duplicate->refresh()->isClosed)->toBeFalse()
        ->and($this->duplicate->duplicate_of_ticket_id)->toBeNull();
});

it('rolls back model deletion and duplicate cleanup together in the caller transaction', function () {
    resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id);

    expect(fn () => DB::transaction(function (): void {
        $this->original->delete();
        expect($this->duplicate->refresh()->duplicate_of_ticket_id)->toBeNull();

        throw new RuntimeException('Rollback the deletion');
    }))->toThrow(RuntimeException::class, 'Rollback the deletion');

    expect($this->original->refresh()->trashed())->toBeFalse()
        ->and($this->duplicate->refresh()->duplicate_of_ticket_id)->toBe($this->original->id);
});

describe('with tenants', function () {
    beforeEach(function () {
        foreach (['tickets', 'ticket_dispositions', 'ticket_statuses', 'ticket_priorities'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
        }
        TicketStatus::query()->update(['tenant_id' => 1]);
        config()->set('padmission-tickets.tenancy.enabled', true);
        $this->original->forceFill(['tenant_id' => 1])->saveQuietly();
        $this->duplicate->forceFill(['tenant_id' => 1])->saveQuietly();
    });

    it('offers only the same tenant in options and search and rejects a forged other-tenant selection', function () {
        $other = Ticket::factory()->open()->state(['disposition_id' => null])->create(['tenant_id' => 2, 'subject' => 'The original problem']);

        Livewire::test(ViewTicket::class, ['record' => $this->duplicate->id])
            ->mountAction(duplicateAction())
            ->assertFormFieldExists('original', function ($field) use ($other): bool {
                return array_key_exists($this->original->id, $field->getOptions())
                    && ! array_key_exists($other->id, $field->getOptions())
                    && ! array_key_exists($other->id, $field->getSearchResults('original'));
            })
            ->callMountedAction(['original' => $other->id])->assertHasActionErrors(['original']);

        expect($this->duplicate->refresh()->isClosed)->toBeFalse();
    });

    it('checks the root tenant too and leaves both histories untouched on refusal', function () {
        $root = Ticket::factory()->open()->state(['disposition_id' => null])->create(['tenant_id' => 2]);
        $this->original->forceFill(['duplicate_of_ticket_id' => $root->id])->saveQuietly();

        expect(fn () => resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id))->toThrow(ValidationException::class);
        expect($this->duplicate->refresh()->duplicate_of_ticket_id)->toBeNull()
            ->and($root->ticketActivities()->where('type', ActivityType::DuplicatedBy)->count())->toBe(0);
    });

    it('does not list another tenant duplicate even if its link is malformed', function () {
        resolve(TicketDuplicates::class)->close($this->duplicate, $this->original->id);
        Ticket::factory()->closed()->state(['disposition_id' => null])->create(['tenant_id' => 2, 'duplicate_of_ticket_id' => $this->original->id, 'subject' => 'Private other tenant subject']);

        Livewire::test(ViewTicket::class, ['record' => $this->original->id])
            ->assertSee('The same problem')->assertDontSee('Private other tenant subject');
        expect($this->original->duplicates->modelKeys())->toBe([$this->duplicate->id]);
    });

    it('scopes a ticket without a tenant to other tickets without a tenant', function () {
        $this->duplicate->forceFill(['tenant_id' => null])->saveQuietly();
        expect(resolve(TicketDuplicates::class)->candidates($this->duplicate)->pluck('id'))->not->toContain($this->original->id);
    });
});

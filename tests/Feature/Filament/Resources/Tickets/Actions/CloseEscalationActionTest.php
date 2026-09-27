<?php

use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test2')->supportTeamName('Padmission');

    $this->owner = User::factory()->create();
    $this->escalation = Ticket::factory()->open()->create([
        'panel' => 'test2',
        'source_panel' => 'test',
        'submitter_id' => $this->owner->id,
        'turn' => Turn::User,
        'disposition_id' => null,
    ]);
    $this->original = Ticket::factory()->open()->create(['linked_ticket_id' => $this->escalation->id]);
});

it('lets the owner close the escalation with a centred confirm and no disposition', function () {
    $this->login($this->owner);
    $this->freezeSecond();
    $originalBefore = $this->original->refresh()->only(['status_id', 'closed_at', 'linked_ticket_id', 'updated_at']);

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->assertActionVisible(CloseEscalationAction::class)
        ->assertActionHasLabel(CloseEscalationAction::class, 'Close escalation')
        ->assertActionExists(CloseEscalationAction::class, fn (CloseEscalationAction $action): bool => ! $action->isModalSlideOver())
        ->mountAction(CloseEscalationAction::class)
        ->assertMountedActionModalSee([
            'Close this escalation?',
            'Padmission is told it was closed. Nobody can reply to it after that, and it can\'t be reopened. Its original tickets stay open.',
            'Close escalation',
        ])
        ->assertMountedActionModalDontSee(__('padmission-tickets::tickets.actions.close.disposition.label'))
        ->callMountedAction()
        ->assertNotified('Escalation closed')
        ->assertRedirect(TicketResource::getUrl('view', ['record' => $this->escalation]));

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->assertActionHidden(CloseEscalationAction::class);

    $closedStatus = TicketStatus::getClosedStatusFor($this->escalation);

    expect($this->escalation->refresh())
        ->closed_at->toEqual(now())
        ->closed_by->toBe($this->owner->id)
        ->disposition_id->toBeNull()
        ->status_id->toBe($closedStatus->id)
        ->and($closedStatus->panel)->toBe('test2')
        ->and($this->escalation->ticketActivities()->where('type', ActivityType::Closed)->exists())->toBeTrue()
        ->and($this->original->refresh()->only(['status_id', 'closed_at', 'linked_ticket_id', 'updated_at']))->toEqual($originalBefore);
});

it('is offered only to the owner, on an open escalation from this panel', function () {
    $this->login(User::factory()->create());

    expect(CloseEscalationAction::isAvailableFor($this->escalation))->toBeFalse()
        ->and(CloseEscalationAction::isAvailableFor($this->original))->toBeFalse();

    $this->login($this->owner);

    expect(CloseEscalationAction::isAvailableFor($this->escalation))->toBeTrue();

    $this->escalation->close(closedById: $this->owner->id);

    expect(CloseEscalationAction::isAvailableFor($this->escalation->refresh()))->toBeFalse();

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->assertActionHidden(CloseEscalationAction::class);
});

it('is not offered where the escalation was sent', function () {
    Filament::setCurrentPanel('test2');
    $this->login($this->owner);

    expect(CloseEscalationAction::isAvailableFor($this->escalation))->toBeFalse();
});

it('writes the closed status of the escalation\'s own panel and tenant', function () {
    Schema::table('tickets', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    Schema::table('ticket_statuses', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    config()->set('padmission-tickets.tenancy.enabled', true);

    // Tenant 1's would win if the tenant were ignored.
    $closed = collect([1 => 100, 2 => 99])->map(fn (int $order, int $tenant): TicketStatus => TicketStatus::factory()->create(['panel' => 'test2', 'tenant_id' => $tenant, 'order' => $order]));
    $this->escalation->update(['tenant_id' => 2]);
    $this->login($this->owner);

    Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
        ->callAction(CloseEscalationAction::class)
        ->assertHasNoActionErrors();

    expect($this->escalation->refresh()->status_id)->toBe($closed[2]->id);
});

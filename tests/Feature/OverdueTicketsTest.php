<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Tests\Fixtures\Models\Tenant;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    $this->travelTo(now()->setDate(2026, 10, 5)->setTime(14, 0));
});

function requesterMessageAt(Ticket $ticket, string $at, array $attributes = []): TicketActivity
{
    return TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::User,
        'created_at' => $at,
        ...$attributes,
    ]);
}

it('uses the last requester message, the current turn and open state', function () {
    $old = Ticket::factory()->open()->create(['turn' => Turn::Supporter]);
    requesterMessageAt($old, '2026-10-02 13:59:59');
    // A staff update, note, or assignment never restarts the requester clock.
    requesterMessageAt($old, '2026-10-05 13:00:00', ['sender' => ActivitySender::Supporter]);
    requesterMessageAt($old, '2026-10-05 13:10:00', ['type' => ActivityType::InternalMessage]);
    requesterMessageAt($old, '2026-10-05 13:20:00', ['type' => ActivityType::TurnChanged]);
    requesterMessageAt($old, '2026-10-05 13:30:00', ['deleted_at' => now()]);

    $boundary = Ticket::factory()->open()->create(['turn' => Turn::Supporter]);
    requesterMessageAt($boundary, '2026-10-02 14:00:00');
    $newer = Ticket::factory()->open()->create(['turn' => Turn::Supporter]);
    requesterMessageAt($newer, '2026-10-01 10:00:00');
    requesterMessageAt($newer, '2026-10-05 12:00:00');
    $waiting = Ticket::factory()->open()->create(['turn' => Turn::User]);
    requesterMessageAt($waiting, '2026-10-01 10:00:00');
    $closed = Ticket::factory()->closed()->create(['turn' => Turn::Supporter]);
    requesterMessageAt($closed, '2026-10-01 10:00:00');
    Ticket::factory()->open()->create(['turn' => Turn::Supporter]);
    $deleted = Ticket::factory()->open()->create(['turn' => Turn::Supporter]);
    requesterMessageAt($deleted, '2026-10-01 10:00:00');
    $deleted->delete();

    expect(Ticket::query()->overdue()->pluck('id')->all())->toBe([$old->id]);
    $old->update(['turn' => Turn::User]);
    expect(Ticket::query()->overdue()->exists())->toBeFalse();
    $old->update(['turn' => Turn::Supporter]);
    expect(Ticket::query()->overdue()->pluck('id')->all())->toBe([$old->id]);
});

it('honors the configured threshold and does not consume weekend time', function () {
    $ticket = Ticket::factory()->open()->create(['turn' => Turn::Supporter]);
    requesterMessageAt($ticket, '2026-10-02 10:00:00');

    $this->travelTo(now()->setDate(2026, 10, 4)->setTime(23, 0));
    expect(Ticket::query()->overdue()->exists())->toBeFalse();
    $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0, 1));
    expect(Ticket::query()->overdue()->exists())->toBeTrue();
    config()->set('padmission-tickets.overdue.business_days', 2);
    expect(Ticket::query()->overdue()->exists())->toBeFalse();
    $this->travelTo(now()->setDate(2026, 10, 6)->setTime(10, 0, 1));
    expect(Ticket::query()->overdue()->exists())->toBeTrue();
});

it('resolves each organization timezone on a list spanning tenants and retains host ticket scopes', function () {
    Schema::create('tenants', function (Blueprint $table) {
        $table->id();
        $table->string('timezone')->nullable();
    });
    Schema::table('tickets', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    config()->set('padmission-tickets.tenancy', ['enabled' => true, 'tenancy_model' => Tenant::class]);
    Tenant::query()->insert([
        ['id' => 1, 'timezone' => 'America/New_York'],
        ['id' => 2, 'timezone' => 'Asia/Tokyo'],
        ['id' => 3, 'timezone' => null],
        ['id' => 4, 'timezone' => 'invalid'],
    ]);
    $tickets = collect([1, 2, 3, 4, null])->map(function ($tenantId) {
        $ticket = Ticket::factory()->open()->create(['tenant_id' => $tenantId, 'turn' => Turn::Supporter]);
        // Saturday in UTC/Tokyo, still Friday evening in New York.
        requesterMessageAt($ticket, '2026-10-03 02:00:00');

        return $ticket;
    });

    $this->travelTo(now()->setTime(14, 30));
    expect(Ticket::query()->overdue()->count())->toBe(0);
    $this->travelTo(now()->setTime(15, 0, 1));
    // Tokyo has passed 24 weekday hours. The other clocks have not.
    expect(Ticket::query()->overdue()->pluck('tenant_id')->all())->toBe([2]);
    $this->travelTo(now()->addDay()->startOfDay()->addSecond());
    // Missing and invalid timezones, and a tenant-less ticket, use UTC.
    expect(Ticket::query()->overdue()->pluck('id')->all())->toBe($tickets->except(0)->pluck('id')->all());

    TicketPlugin::get()->customizeTicketQuery(fn ($query) => $query->where('tenant_id', 1));
    expect(TicketPlugin::get()->getTicketQuery()->overdue()->exists())->toBeFalse();
});

it('falls back to the app timezone when no organization timezone can be resolved', function () {
    config()->set('app.timezone', 'America/New_York');
    $this->travelTo(now()->setTimezone('America/New_York')->setDate(2026, 10, 5)->setTime(10, 0, 1));
    $ticket = Ticket::factory()->open()->create(['turn' => Turn::Supporter]);
    requesterMessageAt($ticket, '2026-10-02 10:00:00');

    expect(Ticket::query()->overdue()->exists())->toBeTrue();
});

it('supports an explicit timezone override without depending on display timezone', function () {
    config()->set('padmission-tickets.overdue.timezone', 'Asia/Tokyo');
    TicketPlugin::get()->displayTimezone('America/New_York');
    $ticket = Ticket::factory()->open()->create(['turn' => Turn::Supporter]);
    requesterMessageAt($ticket, '2026-10-03 02:00:00');
    $this->travelTo(now()->setTime(15, 0, 1));

    expect(Ticket::query()->overdue()->exists())->toBeTrue();
});

it('falls back when the organization model has no timezone column', function () {
    Schema::create('tenants', fn (Blueprint $table) => $table->id());
    config()->set('padmission-tickets.tenancy', ['enabled' => true, 'tenancy_model' => Tenant::class]);
    $ticket = Ticket::factory()->open()->create(['turn' => Turn::Supporter]);
    requesterMessageAt($ticket, '2026-10-02 13:59:59');

    expect(Ticket::query()->overdue()->exists())->toBeTrue();
});

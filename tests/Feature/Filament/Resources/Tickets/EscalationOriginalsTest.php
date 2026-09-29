<?php

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\Fixtures\TestTicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * Panel test2 escalates to test, where its originals are listed beside the
 * escalation. The viewer answers the escalation in test.
 */
beforeEach(function () {
    (new TicketStatusSeeder)->run();
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $this->viewer = $this->login(User::factory()->create(['name' => 'Kevin McKee']));
    $this->handler = User::factory()->create(['name' => 'Maria Lopez']);
    $this->escalation = Ticket::factory()->open()->create();
});

function originalOf(Ticket $escalation, array $attributes = [], string $requester = 'Aisha Brooks'): Ticket
{
    return Ticket::factory()->open()->create([
        'panel' => 'test2',
        'linked_ticket_id' => $escalation->id,
        'submitter_id' => User::factory()->create(['name' => $requester])->id,
        ...$attributes,
    ]);
}

function box(Ticket $escalation): Testable
{
    return Livewire::test(ViewTicket::class, ['record' => $escalation->id])->call('closeLinked');
}

class NoEditTicketPolicy extends TestTicketPolicy
{
    public function update(User $user, Ticket $ticket): bool
    {
        return false;
    }
}

describe('Rows', function () {
    it('shows each original as a row: subject, status, number and requester, and who owes whom, opening it beside the escalation', function () {
        $original = originalOf($this->escalation, ['subject' => 'Rent calculation looks wrong', 'assignee_id' => $this->handler->id, 'turn' => Turn::Supporter]);

        $html = box($this->escalation)->html();
        $box = str($html)->after('class="pad-ti-originals"')->toString();

        expect($box)->toContain('Rent calculation looks wrong', e($original->status->display_name), "#{$original->id} · Aisha Brooks", 'Maria Lopez owes Aisha Brooks a reply');

        box($this->escalation)
            ->assertSeeHtml('wire:click="showLinked('.$original->id.')"')
            ->assertSeeHtml('pad-ti-originals__chevron')
            ->assertDontSeeHtml('ticket-card');
    });

    it('says who waits on whom', function (Closure $attributes, string $line) {
        originalOf($this->escalation, $attributes());

        box($this->escalation)->assertSee($line);
    })->with([
        'the handler owes the reply' => [fn () => ['assignee_id' => test()->handler->id, 'turn' => Turn::Supporter], 'Maria Lopez owes Aisha Brooks a reply'],
        'the handler waits on the requester' => [fn () => ['assignee_id' => test()->handler->id, 'turn' => Turn::User], 'Maria Lopez is waiting on Aisha Brooks'],
        'the viewer owes the reply' => [fn () => ['assignee_id' => test()->viewer->id, 'turn' => Turn::Supporter], 'You owe Aisha Brooks a reply'],
        'the viewer waits' => [fn () => ['assignee_id' => test()->viewer->id, 'turn' => Turn::User], 'You\'re waiting on Aisha Brooks'],
        'nobody is assigned to answer' => [fn () => ['assignee_id' => null, 'turn' => Turn::Supporter], 'No one is assigned to answer Aisha Brooks'],
        'nobody is assigned, waiting on the requester' => [fn () => ['assignee_id' => null, 'turn' => Turn::User], 'Waiting on Aisha Brooks'],
    ]);

    it('writes names with quotes, ampersands and angle brackets as text', function () {
        $this->handler->update(['name' => 'Tess & <Co>']);
        originalOf($this->escalation, ['assignee_id' => $this->handler->id, 'turn' => Turn::Supporter, 'subject' => 'Rent < 200 & "late"'], 'O\'Brien & <Sons>');

        box($this->escalation)
            ->assertSeeHtml('Tess &amp; &lt;Co&gt; owes O&#039;Brien &amp; &lt;Sons&gt; a reply')
            ->assertSeeHtml('Rent &lt; 200 &amp; &quot;late&quot;')
            ->assertDontSeeHtml('<Sons>');
    });

    it('calls a lone original Original ticket', function () {
        originalOf($this->escalation);

        box($this->escalation)->assertSeeHtml('<span>Original ticket</span>');
    });
});

describe('Folding', function () {
    it('shows three open originals, then Show N more for the rest, with Show fewer to fold them again', function () {
        $originals = collect(range(1, 5))->map(fn (int $n): Ticket => originalOf($this->escalation, [], "Requester {$n}"));

        $html = box($this->escalation)->assertSee(['Show 2 more', 'Show fewer'])->html();

        foreach ($originals as $index => $original) {
            $row = str($html)->after('wire:key="pad-ti-original-'.$original->id.'"')->before('>')->toString();

            expect(str_contains($row, 'x-show="all"'))->toBe($index >= 3);
        }
    });

    it('needs no Show more for three', function () {
        collect(range(1, 3))->each(fn (int $n): Ticket => originalOf($this->escalation, [], "Requester {$n}"));

        box($this->escalation)->assertDontSee('Show fewer');
    });

    it('fades closed originals and folds them into N closed, saying who closed them', function () {
        CarbonImmutable::setTestNow('2026-09-27 15:00:00');
        $open = originalOf($this->escalation);
        $closed = originalOf($this->escalation, [], 'Felix Moreno');
        $closed->close(closedById: $this->handler->id);
        CarbonImmutable::setTestNow();

        $html = box($this->escalation)->assertSee(['1 closed', 'Closed by Maria Lopez on Sep 27'])->html();
        $closedRow = str($html)->after('wire:key="pad-ti-original-'.$closed->id.'"')->before('>')->toString();
        $openRow = str($html)->after('wire:key="pad-ti-original-'.$open->id.'"')->before('>')->toString();

        expect($closedRow)->toContain('x-show="closed"')
            ->and($openRow)->not->toContain('x-show')
            ->and(str($html)->before('wire:key="pad-ti-original-'.$closed->id.'"')->afterLast('<li')->toString())->toContain('is-closed');
    });
});

describe('Linking and removing', function () {
    it('offers + and × to whoever may edit the open escalation', function () {
        $original = originalOf($this->escalation);

        box($this->escalation)
            ->assertActionVisible('linkOriginals')
            ->assertActionVisible(TestAction::make('removeOriginal')->arguments(['original' => $original->id]))
            ->assertSeeHtml('aria-label="Link another ticket"')
            ->assertSeeHtml('aria-label="Remove from escalation"');
    });

    it('offers neither to someone who may not edit it, or once it is closed', function (string $why) {
        $original = originalOf($this->escalation);

        match ($why) {
            'no edit rights' => Gate::policy(Ticket::class, NoEditTicketPolicy::class),
            'closed' => $this->escalation->close(),
        };

        box($this->escalation)
            ->assertActionHidden('linkOriginals')
            ->assertActionHidden(TestAction::make('removeOriginal')->arguments(['original' => $original->id]))
            ->assertDontSeeHtml('aria-label="Remove from escalation"');
    })->with(['no edit rights', 'closed']);

    it('takes an original out after asking, naming its requester, and keeps the others', function () {
        $removed = originalOf($this->escalation);
        $kept = originalOf($this->escalation, [], 'Felix Moreno');

        box($this->escalation)
            ->assertActionExists(TestAction::make('removeOriginal')->arguments(['original' => $removed->id]), fn (Action $action): bool => $action->isConfirmationRequired())
            ->mountAction(TestAction::make('removeOriginal')->arguments(['original' => $removed->id]))
            ->assertMountedActionModalSee('Take Aisha Brooks\'s ticket out of this escalation?')
            ->callMountedAction();

        expect($removed->refresh()->linked_ticket_id)->toBeNull()
            ->and($kept->refresh()->linked_ticket_id)->toBe($this->escalation->id);
    });

    it('links another original with +', function () {
        $existing = originalOf($this->escalation);
        $free = Ticket::factory()->open()->create(['panel' => 'test2', 'linked_ticket_id' => null]);

        box($this->escalation)->callAction('linkOriginals', ['originals' => [$existing->id, $free->id]]);

        expect($free->refresh()->linked_ticket_id)->toBe($this->escalation->id)
            ->and($existing->refresh()->linked_ticket_id)->toBe($this->escalation->id);
    });
});

it('lists the originals the same way on the side that escalated, each opening on its own page, and adds nothing it cannot do there', function () {
    TicketPlugin::get('test2')->allowLinkedTicketsTo([]);
    TicketPlugin::get('test')->allowLinkedTicketsTo(['test2']);
    Gate::policy(Ticket::class, NoEditTicketPolicy::class);
    $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $this->viewer->id]);
    $original = Ticket::factory()->open()->create([
        'linked_ticket_id' => $escalation->id,
        'submitter_id' => User::factory()->create(['name' => 'Aisha Brooks'])->id,
        'assignee_id' => $this->viewer->id,
        'turn' => Turn::Supporter,
    ]);

    box($escalation)
        ->assertSee(["#{$original->id} · Aisha Brooks", 'You owe Aisha Brooks a reply'])
        ->assertSeeHtml('href="'.e(ViewTicket::getUrl(['record' => $original, 'linked' => $escalation->id])).'"')
        ->assertActionHidden('linkOriginals')
        ->assertDontSeeHtml('aria-label="Remove from escalation"');
});

describe('The pane', function () {
    it('names a lone original by number, with its status and Read-only, and no counter', function () {
        $original = originalOf($this->escalation, ['assignee_id' => $this->handler->id, 'turn' => Turn::Supporter]);

        Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
            ->assertSet('linkedTicketId', $original->id)
            ->assertSee(['Original ticket #'.$original->id, 'Read-only'])
            ->assertSeeInOrder(['Requested by', 'Aisha Brooks', 'Assigned to', 'Maria Lopez', 'Waiting on', 'Maria Lopez'])
            ->assertDontSee(' of 1')
            ->assertDontSeeHtml('pad-ti-linked__switcher')
            ->assertDontSeeHtml('pad-ti-linked__arrows');
    });

    it('counts several originals, steps to the previous and next, and lists each to jump to', function () {
        $first = originalOf($this->escalation, [], 'Aisha Brooks');
        $second = originalOf($this->escalation, [], 'Felix Moreno');
        $third = originalOf($this->escalation, [], 'Priya Nair');

        $page = Livewire::test(ViewTicket::class, ['record' => $this->escalation->id])
            ->call('showLinked', $second->id)
            ->assertSee(['Original ticket', '2 of 3'])
            ->assertSeeHtml('wire:click="showLinked('.$first->id.')"')
            ->assertSeeHtml('wire:click="showLinked('.$third->id.')"');

        $html = $page->html();
        $menu = str($html)->after('class="pad-ti-linked__menu"')->before('</div>')->toString();

        expect(substr_count($menu, 'role="option"'))->toBe(3)
            ->and($menu)->toContain('aria-selected="true"')
            ->and(str($menu)->after('aria-selected="true"')->before('</button>')->toString())->toContain('Felix Moreno', '#'.$second->id)
            ->and(str($html)->after('pad-ti-linked__arrows')->before('</span>')->toString())
            ->toContain('showLinked('.$first->id.')', 'showLinked('.$third->id.')');

        $page->call('showLinked', $first->id)->assertSee('1 of 3');

        $arrows = str($page->html())->after('pad-ti-linked__arrows')->before('</span>')->toString();

        expect($arrows)->toContain('disabled')
            ->and($arrows)->not->toContain('showLinked('.$third->id.')')
            ->and($arrows)->toContain('showLinked('.$second->id.')');
    });
});

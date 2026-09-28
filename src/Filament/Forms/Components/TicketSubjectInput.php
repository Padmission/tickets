<?php

namespace Padmission\Tickets\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\TextInput;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Rules\PlainText;

/*
 * A ticket's subject is plain text: markup is refused wherever a subject is
 * written, by the package's own rule rather than the hosts', whose strip_tags
 * on save cut a subject such as "Rent < 200" off at its "<". A subject left
 * as it was is kept, so a ticket from before the rule can still be edited
 * or escalated.
 */
class TicketSubjectInput extends TextInput
{
    protected function setUp(): void
    {
        // The host's configuration of every TextInput has run by now.
        $this->rules = [];
        $this->dehydrateStateUsing = null;

        parent::setUp();

        $this->rule(fn (TicketSubjectInput $component): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            $record = $component->getRecord();

            if ($record instanceof Ticket && $value === $record->subject) {
                return;
            }

            (new PlainText)->validate($attribute, $value, $fail);
        });
    }
}

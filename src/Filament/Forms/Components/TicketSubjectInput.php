<?php

namespace Padmission\Tickets\Filament\Forms\Components;

use Filament\Forms\Components\TextInput;

/*
 * A ticket's subject is plain text, escaped wherever it is shown, and holds
 * whatever the requester wrote, such as a pasted error with "<" or
 * "onclick=". Hosts refuse or strip markup-like text in every text input,
 * which would refuse or change a subject the chat widget accepted as soon
 * as the ticket is edited or escalated, so this input keeps it as typed.
 */
class TicketSubjectInput extends TextInput
{
    protected function setUp(): void
    {
        // The host's configuration of every TextInput has run by now.
        $this->rules = [];
        $this->dehydrateStateUsing = null;

        parent::setUp();
    }
}

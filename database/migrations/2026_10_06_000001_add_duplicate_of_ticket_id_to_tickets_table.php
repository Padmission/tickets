<?php

use Illuminate\Database\Migrations\Migration;
use Padmission\Tickets\Support\TicketMigrationSchema;

return new class extends Migration
{
    public function up(): void
    {
        TicketMigrationSchema::ensureNullableForeignId('tickets', 'duplicate_of_ticket_id', 'tickets');
    }
};

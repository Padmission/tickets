<?php

namespace Padmission\Tickets\Exceptions;

use Exception;

class TicketDispositionNotFoundException extends Exception
{
    protected mixed $context;

    public function __construct(mixed $context = null)
    {
        parent::__construct('Ticket Disposition not found.');
        $this->context = $context;
    }

    public function getContext(): mixed
    {
        return $this->context;
    }
}

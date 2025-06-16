<?php

namespace Padmission\Tickets\Exceptions;

use Exception;

class TicketConfigurationInvalidSetting extends Exception
{
    protected mixed $context;

    public function __construct(string $event)
    {
        $this->context = "Unknown event '{$event}'";
        parent::__construct('Ticket Disposition not found.');
    }

    public function getContext(): mixed
    {
        return $this->context;
    }
}

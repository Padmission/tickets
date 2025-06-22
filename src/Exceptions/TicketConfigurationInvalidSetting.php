<?php

namespace Padmission\Tickets\Exceptions;

use Exception;

class TicketConfigurationInvalidSetting extends Exception
{
    protected mixed $context;

    public function __construct(string $event)
    {
        $this->context = "Unknown event '{$event}'";
        parent::__construct("Invalid or unknown event: '{$event}'.");
    }

    public function getContext(): mixed
    {
        return $this->context;
    }
}

<?php

namespace Padmission\Tickets\Exceptions;

use Exception;

class InvalidEventException extends Exception
{
    protected mixed $context;

    public function __construct(string $event)
    {
        $this->context = "Unknown event '{$event}'";
        parent::__construct('Invalid Event.');
    }

    public function getContext(): mixed
    {
        return $this->context;
    }
}

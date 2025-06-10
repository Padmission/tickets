<?php

namespace Padmission\Tickets\Exceptions;

use Exception;

class TicketDispositionNotFoundException extends Exception
{
    /**
     * Contextual data about the error (e.g., attempted disposition ID or input).
     *
     * @var mixed
     */
    protected $context;

    /**
     * @param  mixed  $context
     */
    public function __construct($context = null)
    {
        parent::__construct('Ticket Disposition not found.');
        $this->context = $context;
    }

    /**
     * Get the context for this exception.
     *
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }
}

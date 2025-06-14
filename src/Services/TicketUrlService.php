<?php

namespace Padmission\Tickets\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Padmission\Tickets\Models\Contracts\TicketInterface;

class TicketUrlService
{
    /**
     * Get the action URL for a ticket
     */
    public function getActionUrl(TicketInterface $ticket): string
    {
        $data = (array) $ticket->data;
        $basis = $data['url'] ?? url('/');

        return $this->addHash($basis, 'ticket-'.$ticket->id);
    }

    /**
     * Add hash fragment to URL with validation
     */
    protected function addHash(string $url, string $hash): string
    {
        try {
            validator(['url' => $url], ['url' => 'required|url'])->validate();
        } catch (ValidationException) {
            // Fallback to app URL if ticket URL is invalid
            $url = config('app.url');
        }

        $baseUrl = Str::before($url, '#');
        $cleanHash = Str::start(ltrim($hash, '#'), '#');

        return $baseUrl.$cleanHash;
    }
}

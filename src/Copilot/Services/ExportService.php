<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Services;

use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Padmission\Tickets\Enums\ActivitySender;

class ExportService
{
    /**
     * Export a conversation to Markdown.
     */
    public function toMarkdown(string $conversationId, Model $user, string $panelId, ?Model $tenant = null): ?string
    {
        $conversation = CopilotConversation::query()
            ->forPanel($panelId)
            ->forParticipant($user)
            ->forTenant($tenant)
            ->with('ticket.ticketActivities')
            ->find($conversationId);

        if (! $conversation) {
            return null;
        }

        $lines = [
            "# {$conversation->title}",
            '',
            "**Date:** {$conversation->created_at->format('Y-m-d H:i')}",
            "**Panel:** {$conversation->panel_id}",
            '',
            '---',
            '',
        ];

        foreach ($conversation->ticket?->ticketActivities ?? [] as $message) {
            $role = $message->sender === ActivitySender::Ai ? '**Copilot:**' : '**You:**';

            $lines[] = $role;
            $lines[] = '';
            $lines[] = $message->content ?: json_encode($message->data, JSON_PRETTY_PRINT);
            $lines[] = '';
        }

        $totalTokens = $conversation->total_tokens;
        $lines[] = '---';
        $lines[] = '';
        $lines[] = "*Total tokens used: {$totalTokens}*";

        return implode("\n", $lines);
    }
}

<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Concerns;

use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Add to User (or authenticatable) models to link them with copilot conversations.
 */
trait HasCopilotChat
{
    public function copilotConversations(): MorphMany
    {
        return $this->morphMany(CopilotConversation::class, 'participant');
    }
}

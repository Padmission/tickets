<?php

namespace Padmission\Tickets\Models\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

interface TicketInterface
{
    // Relations
    public function disposition(): BelongsTo;
    public function status(): BelongsTo;
    public function priority(): BelongsTo;
    public function submitter(): BelongsTo;
    public function assignee(): BelongsTo;
    public function ticketActivities(): HasMany;
    public function latestMessage(): HasOne;
    public function latestActivity(): HasOne;
    public function ticketNotifications(): HasMany;
    // Attributes/Business Logic
    public function close(TicketInterface|int|null $disposition = null, ?int $closedBy = null): void;
}

<?php

namespace Padmission\Tickets\Models\Contracts;

use Carbon\Carbon;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @property ?Authenticatable $assignee
 * @property ?int $assignee_id
 * @property ?bool $isClosed
 * @property ?string $panel
 * @property ?int $priority_id
 * @property ?int $status_id
 * @property ?Carbon $closed_at
 * @property ?int $closed_by
 * @property ?Authenticatable $submitter
 * @property int|null $id
 * @property string|null $subject
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method int|string getKey()
 * @method bool isDirty(array|string|null $attributes = null)
 * @method mixed getOriginal(string|null $key = null, mixed $default = null)
 * @method bool wasChanged(array|string|null $attributes = null)
 * @method void notify(\Illuminate\Notifications\Notification $notification)
 */
interface TicketInterface
{
    // Magic methods for static analysis
    public function __set($key, $value);
    public function __get($key);
    public function __isset($key);

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

    // Business logic
    public function close(TicketInterface|int|null $disposition = null, ?int $closedBy = null): void;
}

<?php

namespace Padmission\Tickets\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Padmission\Tickets\Database\Factories\TicketFactory;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\TicketPlugin;

#[UseFactory(TicketFactory::class)]
class Ticket extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'turn' => Turn::class,
        'closed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $ticket) {
            $panel = $ticket->panel;

            $plugin = TicketPlugin::get($panel);
            $assignmentStrategy = $plugin->getAssignmentStrategy();

            if ($assignmentStrategy === null) {
                return;
            }

            $assignmentStrategy->assign($ticket);
        });

        static::created(function (self $ticket) {
            $panel = $ticket->panel;

            $plugin = TicketPlugin::get($panel);
            $notificationStrategy = $plugin->getNotificationStrategy();

            if ($notificationStrategy === null) {
                return;
            }

            $notificationStrategy->notify($ticket);
        });
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Status::class)
        );
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Priority::class)
        );
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Authenticatable::class),
            'assignee_id'
        );
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketPlugin::resolveModelClass(Activity::class), 'ticket_id');
    }

    public function latestActivity(): HasOne
    {
        return $this
            ->hasOne(TicketPlugin::resolveModelClass(Activity::class), 'ticket_id')
            ->latestOfMany();
    }

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    #[Scope]
    protected function closed(Builder $query): void
    {
        $query->whereNotNull('closed_at');
    }

    protected function isClosed(): Attribute
    {
        return Attribute::get(fn () => $this->closed_at !== null);
    }
}

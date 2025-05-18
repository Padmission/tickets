<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
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
        return $this->belongsTo(config('padmission-tickets.models.status'));
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(config('padmission-tickets.models.priority'));
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(config('padmission-tickets.models.user'), 'assignee_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(config('padmission-tickets.models.activity'), 'ticket_id');
    }

    public function latestActivity(): HasOne
    {
        return $this
            ->hasOne(config('padmission-tickets.models.activity'), 'ticket_id')
            ->latestOfMany();
    }
}

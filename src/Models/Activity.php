<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\ActivityFactory;
use Padmission\Tickets\Enums\ActivityType;

#[UseFactory(ActivityFactory::class)]
class Activity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ticket_activities';

    protected $casts = [
        'data' => 'array',
        'type' => ActivityType::class,
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(config('padmission-tickets.models.ticket'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('padmission-tickets.models.user'));
    }
}

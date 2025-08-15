<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Padmission\Tickets\Database\Factories\TicketAttachmentFactory;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Observers\TicketAttachmentObserver;
use Padmission\Tickets\TicketPlugin;

#[ObservedBy(TicketAttachmentObserver::class)]
#[UseFactory(TicketAttachmentFactory::class)]
class TicketAttachment extends Model
{
    use HasFactory;

    protected $table = 'ticket_attachments';

    protected $guarded = ['id'];

    protected $casts = [
        'data' => 'array',
        'attachments' => 'collection',
        'type' => ActivityType::class,
        'sender' => ActivitySender::class,
        'turn' => Turn::class,
        'created_at' => 'immutable_datetime',
    ];

    /**
     * @return BelongsTo<Ticket,$this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(Ticket::class)
        );
    }

    /**
     * @return BelongsTo<TicketActivity,$this>
     */
    public function ticketActivity(): BelongsTo
    {
        return $this->belongsTo(
            TicketPlugin::resolveModelClass(TicketActivity::class),
        );
    }

    // Methods

    public function isImage(): bool
    {
        return str($this->mime_type)->startsWith('image/');
    }

    public function canBePreviewed(): bool
    {
        return str($this->mime_type)->startsWith(['image/', 'video/']);
    }
}

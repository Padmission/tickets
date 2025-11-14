<?php

namespace Padmission\Tickets\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Database\Factories\TicketAttachmentFactory;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Concerns\HasPanelAwareRelationships;
use Padmission\Tickets\Models\Observers\TicketAttachmentObserver;
use Padmission\Tickets\TicketPlugin;

#[ObservedBy(TicketAttachmentObserver::class)]
class TicketAttachment extends Model
{
    use HasFactory;
    use HasPanelAwareRelationships;

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
     * @return Relations\PanelAwareBelongsTo<Ticket,$this>
     */
    public function ticket(): Relations\PanelAwareBelongsTo
    {
        return $this->panelAwareBelongsTo(
            TicketPlugin::resolveModelClass(Ticket::class),
            'ticket'
        );
    }

    /**
     * @return Relations\PanelAwareBelongsTo<TicketActivity,$this>
     */
    public function ticketActivity(): Relations\PanelAwareBelongsTo
    {
        return $this->panelAwareBelongsTo(
            TicketPlugin::resolveModelClass(TicketActivity::class),
            'ticketActivity'
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

    protected static function newFactory(): TicketAttachmentFactory
    {
        return TicketAttachmentFactory::new();
    }
}

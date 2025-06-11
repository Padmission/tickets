<?php

namespace Padmission\Tickets\Models;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Padmission\Tickets\Database\Factories\TicketNotificationFactory;

#[UseFactory(TicketNotificationFactory::class)]
class TicketNotification extends Model
{
    use HasFactory;

    protected $table = 'ticket_notifications';

    protected $guarded = [];

    public function ticket(): BelongsTo {
        /**
         * TODO: Make it resolve the class.
         */
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo {
        /**
         * TODO: Make it resolve the class.
         */
        return $this->belongsTo(User::class);
    }
}

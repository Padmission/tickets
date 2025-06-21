<?php

namespace Padmission\Tickets\Tests;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Padmission\Tickets\Models\Concerns\HasAssignedTickets;
use Padmission\Tickets\Models\Contracts\HasTicketDisplayName;

#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements FilamentUser, HasTicketDisplayName
{
    use HasAssignedTickets;
    use HasFactory;
    use Notifiable;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getNameForTickets(): string
    {
        return $this->name ?? $this->email ?? "User {$this->id}";
    }
}

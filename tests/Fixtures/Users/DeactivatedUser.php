<?php

namespace Padmission\Tickets\Tests\Fixtures\Users;

use Filament\Panel;
use Padmission\Tickets\Tests\User;

class DeactivatedUser extends User
{
    protected $table = 'users';

    public function canAccessPanel(Panel $panel): bool
    {
        return false;
    }
}

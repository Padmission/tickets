<?php

namespace Padmission\Tickets\Tests\Fixtures\Users;

use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Padmission\Tickets\Tests\User;

class MultiFactorUser extends User implements HasEmailAuthentication
{
    protected $table = 'users';

    public function hasEmailAuthentication(): bool
    {
        return true;
    }

    public function toggleEmailAuthentication(bool $condition): void {}
}

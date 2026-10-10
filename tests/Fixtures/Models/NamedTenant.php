<?php

namespace Padmission\Tickets\Tests\Fixtures\Models;

use Filament\Models\Contracts\HasName;

class NamedTenant extends Tenant implements HasName
{
    public function getFilamentName(): string
    {
        return 'Organization: '.$this->getAttribute('name');
    }
}

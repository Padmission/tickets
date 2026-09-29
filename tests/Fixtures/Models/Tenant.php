<?php

namespace Padmission\Tickets\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $table = 'tenants';

    public $timestamps = false;

    protected $guarded = [];
}

<?php

namespace App\Providers;

use App\Console\DbOpenCommand;
use App\Console\RemoveAssetsCommand;
use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            DbOpenCommand::class,
            RemoveAssetsCommand::class,
        ]);
    }

    public function boot(): void
    {
    }
}

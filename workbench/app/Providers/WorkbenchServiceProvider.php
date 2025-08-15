<?php

namespace App\Providers;

use App\Console\RemoveAssetsCommand;
use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([RemoveAssetsCommand::class]);
    }

    public function boot(): void
    {
        //
    }
}

<?php

namespace App\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Orchestra\Workbench\Workbench;

class RemoveAssetsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'remove-assets';

    /**
     * @var string
     */
    protected $description = 'Remove packages Filament assets during dev';

    public function handle()
    {
        $jsPath = Workbench::laravelPath().'/public/js/padmission-tickets';
        $cssPath = Workbench::laravelPath().'/public/css/padmission-tickets';

        File::deleteDirectory($jsPath);
        File::deleteDirectory($cssPath);

        $this->info('Removed assets');
    }
}

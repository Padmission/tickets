<?php

namespace Padmission\Tickets\Filament\Forms\Fields;

use Filament\Forms\Components\Select;
use Filament\Support\Colors\Color;

class ColorSelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->allowHtml()
            ->native(false)
            ->options(fn () => collect(Color::all())
                ->mapWithKeys(function ($value, $key) {
                    $key = ucfirst($key);

                    return [
                        $key => <<<HTML
                            <div class="flex gap-2 items-center">
                                <div class="h-4 w-4 mr-2 rounded-full" style="background-color: rgb($value[600])"></div>
                                $key
                            </div>
                        HTML
                    ];
                }));
    }
}

<?php

use Filament\Support\Colors\Color;
use Padmission\Tickets\Filament\Forms\Components\ColorSelect;

it('only shows gray from grayish shades', function () {
    $options = array_keys(ColorSelect::make('test')->getOptions());

    expect($options)
        ->toContain('Gray')
        ->not->toContain('Slate', 'Zinc', 'Neutral', 'Stone');
});

it('shows color name and color in options', function () {
    $options = ColorSelect::make('test')->getOptions();

    expect($options['Red'])
        ->toContain('Red')
        ->toContain('<div class="h-4 w-4 mr-2 rounded-full" style="background-color: rgb('.Color::Red[600].')');
});

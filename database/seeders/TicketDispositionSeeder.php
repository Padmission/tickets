<?php

namespace Padmission\Tickets\Database\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Filament\Colors\Color;

class TicketDispositionSeeder extends Seeder
{

    public function getDefaults() : array {

        return [
            trans('padmission-tickets::dispositions.resolved'),
            trans('padmission-tickets::dispositions.abandoned'),
            trans('padmission-tickets::dispositions.unresolvable'),
            trans('padmission-tickets::dispositions.withdrawn'),
            trans('padmission-tickets::dispositions.testing_training')
        ];

    }
    public function run(): void
    {

        $defaults = $this->getDefaults();

        $colors = collect(\Filament\Support\Colors\Color::all())
            ->keys()
            ->reject(fn ($value) => in_array($value, [
                'slate', 'zinc', 'neutral', 'stone',
            ]))
            ->map(function($color) {
                return ucfirst($color);
            });

        foreach (Filament::getPanels() as $panel) {
            Filament::setCurrentPanel($panel);

            if (TicketDisposition::where('panel', $panel->getId())->exists()) {
                continue;
            }

            collect(array_chunk($defaults, 10))->each(function(array $chunk) use ($panel, $colors) {
                collect($chunk)->diff(TicketDisposition::where('panel', $panel->getId())
                    ->whereIn('display_name', $chunk)
                    ->get()
                    ->pluck('display_name'))
                    ->each(function($name) use ($panel, $colors) {
                        TicketDisposition::create([
                            'display_name' => $name,
                            'color' => $colors->random(),
                            'panel' => $panel->getId(),
                        ]);
                    });
            });
        }
    }


}

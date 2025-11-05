<?php

use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\TicketPlugin;

it('applies custom query to plugin getTicketQuery', function () {
    $this->login();

    $this->modifyPlugin(function ($plugin) {
        $plugin->customizeTicketQuery(function ($query) {
            return $query->where('subject', 'like', 'VIP%');
        });
    });

    $query = TicketPlugin::get()->getTicketQuery();

    expect($query->toSql())->toContain('where "subject" like ?');
});

it('applies custom query to TicketResource', function () {
    $this->login();

    $this->modifyPlugin(function ($plugin) {
        $plugin->customizeTicketQuery(function ($query) {
            return $query->where('priority_id', 1);
        });
    });

    $query = TicketResource::getEloquentQuery();

    expect($query->toSql())->toContain('where "priority_id" = ?');
});

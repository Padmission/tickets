<?php

return [
    'burndown' => [
        'heading' => 'Burndown Chart',
        'open_at_end_of_day' => 'Open at End of Day',
        'closed_that_day' => 'Closed that day',
    ],

    'open_tickets' => [
        'label' => 'Open Tickets',
        'description' => 'Tickets not yet closed',
    ],

    'open_support_tickets' => [
        'label' => 'Tickets Waiting on Support',
        'description' => 'Open tickets where support owes the next reply',
    ],

    'needs_you' => [
        'label' => 'Needs You',
        'description' => 'Open tickets waiting on your reply, with nobody assigned, or with a reply to pass on from the team you escalated to',
        'description_to' => 'Open tickets waiting on your reply, with nobody assigned, or with a reply from :team to pass on',
        'description_received' => 'Open tickets waiting on your reply or with nobody assigned',
    ],

    'escalations_waiting' => [
        'label' => 'Waiting on the Other Team',
        'label_to' => 'Waiting on :team',
        'description' => 'Open escalations where the team you escalated to owes the next reply',
        'description_to' => 'Open escalations where :team owes the next reply',
    ],

    'close_time' => [
        'label' => 'Average Close Time',
        'description' => ':count tickets closed',
    ],
];

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
        'description_escalations' => 'Escalations not yet closed',
    ],

    'open_support_tickets' => [
        'label' => 'Tickets Waiting on Support',
        'description' => 'Open tickets where support owes the next reply',
    ],

    'needs_you' => [
        'label' => 'Needs You',
        // One line on every card row. The count is replies owed (including a reply from the team escalated to, to pass on) and tickets needing an assignee.
        'description' => 'Needs a reply or an assignee',
    ],

    'escalations_waiting' => [
        'label' => 'Waiting on the Other Team',
        'label_to' => 'Waiting on :team',
        'description' => 'Escalations the other team owes a reply on',
        'description_to' => 'Escalations :team owes a reply on',
    ],

    'overdue' => [
        'label' => 'Overdue',
        'description' => 'Waiting on support past the reply threshold',
    ],

    'close_time' => [
        'label' => 'Average Close Time',
        'description' => '{0} :count tickets closed|{1} :count ticket closed|[2,*] :count tickets closed',
    ],
];

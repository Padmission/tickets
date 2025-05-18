<?php

return [
    'enums' => [
        'turn' => [
            'user' => 'User',
            'supporter' => 'Supporter',
        ],
    ],

    'level' => [
        'default' => 'Default',
        'escalated' => 'Escalated',
    ],

    'resources' => [
        'navigation_group' => 'Tickets',

        'tickets' => [
            'model_label' => 'Ticket',
            'plural_model_label' => 'Tickets',

            'display_name' => 'Display Name',
            'turn' => 'Turn',
            'status' => 'Status',
            'priority' => 'Priority',
            'subject' => 'Subject',
            'assignee' => 'Assignee',
            'submitter' => 'Submitter',
            'last_activity' => 'Last Activity',
        ],

        'statuses' => [
            'model_label' => 'Status',
            'plural_model_label' => 'Statuses',

            'display_name' => 'Display Name',
            'color' => 'Color',
        ],

        'priorities' => [
            'model_label' => 'Priority',
            'plural_model_label' => 'Priorities',

            'display_name' => 'Display Name',
            'color' => 'Color',
        ],
    ],
];

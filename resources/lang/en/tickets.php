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

    'actions' => [
        'close' => [
            'label' => 'Close',
            'modal_heading' => 'Close Ticket',
        ],
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

    'widgets' => [
        'tickets_waiting_on_support' => 'Tickets Waiting on Support',
        'tickets_with_open_status' => 'Tickets with open status',
        'ticket_performance_metrics' => 'Ticket Performance Metrics',
        'statistics_about_ticket_resolution_times' => 'Statistics about ticket resolution times',
        'last_1_day' => 'Last 1 day',
        'last_7_days' => 'Last 7 days',
        'last_30_days' => 'Last 30 days',
        'last_90_days' => 'Last 90 days',
        'last_365_days' => 'Last 365 days',
        'all_time' => 'All Time',
        'average_close_time' => 'Average Close Time',
        'count_tickets_closed' => ':count tickets closed',
        'fastest_resolution' => 'Fastest Resolution',
        'best_case_scenario' => 'Best case scenario',
        'slowest_resolution' => 'Slowest Resolution',
        'worst_case_scenario' => 'Worst case scenario',
        'open_vs_closed_tickets_by_day' => 'Open vs Closed Tickets by Day',
        'open_at_end_of_day' => 'Open at End of Day',
        'closed_that_day' => 'Closed that day',
        'time_range' => 'Time Range',
    ],
];

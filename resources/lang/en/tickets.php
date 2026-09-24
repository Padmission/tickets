<?php

return [
    'side_you' => 'You',

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
            'disposition' => [
                'label' => 'Disposition',
            ],
        ],

        'create_linked_ticket' => [
            'label' => 'Escalate Ticket',
            'modal_heading' => 'Escalate Ticket',
            'modal_description' => 'This opens a separate ticket for the other support team. This ticket stays open with you, and you keep working with the person who submitted it. Write the instructions the other team needs.',

            'form' => [
                'panel' => 'Support team',
                'subject' => 'Subject',
                'message' => 'Instructions',
            ],

            'notifications' => [
                'success' => [
                    'title' => 'Ticket escalated',
                    'body' => 'A separate ticket was opened for the other support team. This ticket is unchanged.',
                    'action_label' => 'Show escalated ticket',
                ],

                'not_configured' => [
                    'title' => 'Ticket not escalated',
                    'body' => 'Tickets cannot be created for the :panel support team yet because it has no ticket statuses or priorities for your organization.',
                ],
            ],
        ],
    ],

    'copilot' => [
        'title' => 'Support tickets',
        'subtitle' => 'Create and follow up on support requests.',
        'new_ticket' => 'New Ticket',
        'empty_title' => 'No tickets found',
        'empty_body' => 'Create a ticket when you need help from support.',
        'create_title' => 'New support ticket',
        'create_subtitle' => 'Send the details support needs to start helping.',
        'subject' => 'Subject',
        'message' => 'Message',
        'cancel' => 'Cancel',
        'create' => 'Create ticket',
        'resolve' => 'Resolve',
        'reply' => 'Reply',
        'send_reply' => 'Send reply',
        'unread' => 'Unread',
        'status' => 'Status',
        'priority' => 'Priority',
        'assignee' => 'Assignee',
        'disposition' => 'Disposition',
        'unassigned' => 'Unassigned',
        'no_disposition' => 'No disposition',
        'save_metadata' => 'Save metadata',
        'no_messages' => 'No messages yet',
        'closed_ticket_reply_error' => 'This ticket is already resolved.',
        'filters' => [
            'open' => 'Open',
            'closed' => 'Resolved',
            'all' => 'All',
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
            'source_panel' => 'Source Panel',
            'panel' => 'Panel',
            'last_message' => 'Last Message',
            'closed_at' => 'Closed At',
            'disposition' => 'Disposition',
            'linked_tickets' => 'Escalation',
            'parent_ticket' => 'Escalated ticket',
            'child_tickets' => 'Original tickets',
            'assign_to_supporter' => 'Assign to Supporter',
            'assigned_successfully' => 'Tickets assigned successfully',
            'invalid_assignee' => 'Invalid assignee selected',
            'unauthorized_assignment' => 'Some tickets were skipped because you are not authorized to manage them',

            'tabs' => [
                'all' => 'All Tickets',
                'my' => 'My Tickets',
                'linked' => 'Escalated Tickets',
                'my_linked' => 'My Escalated Tickets',
            ],
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

        'dispositions' => [
            'model_label' => 'Disposition',
            'plural_model_label' => 'Dispositions',
            'display_name' => 'Display Name',
            'color' => 'Color',
        ],
    ],
];

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
            'label' => 'Create Linked Ticket',

            'form' => [
                'panel' => 'Panel',
                'subject' => 'Subject',
                'message' => 'Message',

            ],

            'notifications' => [
                'success' => [
                    'title' => 'Ticket created',
                    'body' => 'A new ticket has been created and was linked to the current ticket.',
                    'action_label' => 'Show Ticket',
                ],

                'not_configured' => [
                    'title' => 'Linked ticket not created',
                    'body' => 'Tickets cannot be created in the :panel panel yet because it has no ticket statuses or priorities for your organization.',
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
            'linked_tickets' => 'Linked Tickets',
            'parent_ticket' => 'Parent Ticket',
            'child_tickets' => 'Child Tickets',
            'assign_to_supporter' => 'Assign to Supporter',
            'assigned_successfully' => 'Tickets assigned successfully',
            'invalid_assignee' => 'Invalid assignee selected',
            'unauthorized_assignment' => 'Some tickets were skipped because you are not authorized to manage them',

            'tabs' => [
                'all' => 'All Tickets',
                'my' => 'My Tickets',
                'linked' => 'All Linked Tickets',
                'my_linked' => 'My Linked Tickets',
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

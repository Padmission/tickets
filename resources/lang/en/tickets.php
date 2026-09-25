<?php

return [
    'side_you' => 'You',

    'enums' => [
        'turn' => [
            'user' => 'User',
            'supporter' => 'Supporter',
        ],

        'turn_description' => [
            'user' => 'Waiting for the person who submitted it to reply.',
            'supporter' => 'Support owes the next reply.',
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

        'reassign' => [
            'label' => 'Reassign',
            'label_unassigned' => 'Assign',
            'modal_heading' => 'Reassign ticket',
            'modal_description' => 'Hand this ticket to a teammate. It moves to their "My Tickets" list. The conversation and the person who submitted it stay the same.',
            'modal_description_escalation' => 'To hand it to another support team instead, use Escalate.',
            'assignee_helper' => 'Only people who can answer tickets here are listed.',
            'assign_to_me' => 'Assign to me',
            'submit' => 'Reassign',
            'success' => 'Ticket reassigned',
        ],

        'view_original_conversation' => [
            'label' => 'Original conversation',
            'modal_heading' => 'Original conversation',
            'modal_description' => 'Read-only. This is the conversation on the ticket this one was escalated from. Reply on this ticket; its requester passes answers back.',
            'requested_by' => 'Requested by',
            'handled_by' => 'Handled by',
            'internal_note' => 'Internal note',
            'attachments' => ':count attachment|:count attachments',
            'empty' => 'No messages yet.',
            'close' => 'Close',
        ],

        'create_linked_ticket' => [
            'label' => 'Escalate Ticket',
            'label_to' => 'Escalate to :team',
            'tooltip' => 'For problems your team cannot resolve. Opens a separate ticket for the other support team.',
            'tooltip_to' => 'For problems your team cannot resolve. Opens a separate ticket for :team.',
            'modal_heading' => 'Escalate Ticket',
            'modal_heading_to' => 'Escalate to :team',
            'modal_description' => 'Use this when your team cannot resolve the ticket. It opens a separate ticket for the other support team, with you as its requester. This ticket stays open with your team, and you keep working with the person who submitted it.',
            'submit' => 'Escalate',
            'modal_description_to' => 'Use this when your team cannot resolve the ticket. It opens a separate ticket for :team, with you as its requester. This ticket stays open with your team, and you keep working with the person who submitted it.',

            'form' => [
                'panel' => 'Support team',
                'subject' => 'Subject',
                'message' => 'Instructions',
                'message_helper' => 'Say what you need, what you have already tried, and how to reproduce the problem. Replies come to you, not to the person who submitted this ticket.',
            ],

            'notifications' => [
                'success' => [
                    'title' => 'Ticket escalated',
                    'body' => 'A separate ticket was opened for the other support team. This ticket is unchanged.',
                    'body_to' => 'A separate ticket was opened for :team. This ticket is unchanged.',
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
            'turn' => 'Waiting on',
            'status' => 'Status',
            'priority' => 'Priority',
            'subject' => 'Subject',
            'assignee' => 'Assignee',
            'unassigned' => 'Unassigned',
            'assigned_elsewhere' => 'Another support team',
            'submitter' => 'Submitter',
            'source_panel' => 'Source Panel',
            'panel' => 'Panel',
            'last_message' => 'Last Message',
            'no_messages' => 'No messages yet',
            'closed_at' => 'Closed At',
            'disposition' => 'Disposition',
            'linked_tickets' => 'Escalation',
            'linked_tickets_description' => [
                'not_escalated' => 'Not escalated. If your team cannot resolve this ticket, escalate it to open a separate ticket for :team.',
                'escalated' => 'Escalated to :team. They reply to you on the escalated ticket. This ticket stays with your team and the person who submitted it.',
                'escalated_to_you' => 'Your team escalated the ticket below to :team. Talk to them here; keep the person who asked updated on the original ticket.',
                'escalated_from' => 'Escalated from the ticket below. The requester here is the person who escalated it, and they pass answers back to whoever submitted the original. Use "Original conversation" to read it.',
            ],
            'other_support_team' => 'the other support team',
            'parent_ticket' => 'Escalated ticket',
            'parent_ticket_placeholder' => 'Not escalated',
            'child_tickets' => 'Original tickets',
            'child_tickets_placeholder' => 'No original tickets',
            'link_existing_ticket' => 'Link existing',
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

            'tab_descriptions' => [
                'all' => 'Every ticket your team handles. Tickets waiting on support come first.',
                'all_submitter' => 'Tickets you submitted.',
                'my' => 'Tickets assigned to you. Use Reassign to hand one to a teammate.',
                'linked' => 'Tickets your team escalated to :team. Each is a separate ticket; the original stays under All Tickets.',
                'my_linked' => 'Tickets you escalated to :team.',
            ],

            'hints' => [
                'status' => 'Where the ticket is in your team\'s process. Use Close when it is resolved.',
                'priority' => 'How urgent the ticket is, for sorting your team\'s work.',
                'disposition' => 'Why the ticket was closed.',
                'submitter' => 'The person who opened this ticket. Replies in the conversation go to them.',
                'assignee' => 'The person responsible for this ticket. Use Reassign to hand it to a teammate.',
                'turn' => 'Who owes the next reply. A reply from support hands it to the person who submitted the ticket, and their reply hands it back.',
            ],

            'filters' => [
                'open_only' => 'Open tickets only',
            ],

            'empty' => [
                'all' => [
                    'heading' => 'No tickets',
                    'description' => 'Closed tickets are hidden while "Open tickets only" is on.',
                ],
                'my' => [
                    'heading' => 'Nothing assigned to you',
                    'description' => 'Tickets appear here when they are assigned to you.',
                ],
                'linked' => [
                    'heading' => 'No escalated tickets',
                    'description' => 'When your team escalates a ticket, the separate ticket it opens appears here.',
                ],
                'my_linked' => [
                    'heading' => 'You have not escalated any tickets',
                    'description' => 'Tickets you escalate appear here.',
                ],
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

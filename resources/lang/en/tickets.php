<?php

return [
    'side_you' => 'You',

    'enums' => [
        'turn' => [
            'user' => 'Requester',
            'supporter' => 'Support',
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
            'modal_heading' => 'Close this ticket?',
            'modal_description' => 'The requester is told it was closed. Nobody can reply to it after that, and it can\'t be reopened.',
            'submit' => 'Close ticket',
            'disposition' => [
                'label' => 'Disposition',
            ],
        ],

        'reassign' => [
            'label' => 'Reassign',
            'label_unassigned' => 'Assign',
            'inline_label' => 'Change',
            'modal_heading' => 'Reassign ticket',
            'modal_description' => 'Hand this ticket to a teammate.',
            'currently_assigned' => 'It is assigned to :name now.',
            'modal_description_escalation' => 'To get outside help, escalate it instead.',
            'modal_description_escalation_to' => 'To send it to :team, escalate it instead.',
            'new_assignee' => 'Assign to',
            'nobody_to_assign' => 'Nobody else can be assigned this ticket.',
            'who_can_be_assigned' => 'Only people who answer tickets here can be assigned. Ask an administrator to add a teammate.',
            'assign_to_me' => 'Assign to me',
            'submit' => 'Reassign',
            'success' => 'Ticket reassigned',
        ],

        'view_original_conversation' => [
            'label' => 'Original conversation',
            'modal_heading' => 'Original conversation',
            'modal_description' => 'Read-only. Reply on this ticket.',
            'choose' => 'Original ticket',
            'requested_by' => 'Requested by',
            'assigned_to' => 'Assigned to',
            'internal_note' => 'Internal note',
            'attachments' => ':count attachment|:count attachments',
            'empty' => 'No messages yet.',
            'close' => 'Close',
        ],

        'add_to_escalation' => [
            'label' => 'Add to an existing escalation',
            'help' => 'Use this when your team already escalated the same problem.',
            'help_to' => 'Use this when your team already escalated the same problem to :team.',
            'modal_description' => 'Pick one of your team\'s open escalations.',
            'modal_description_to' => 'Pick one of your team\'s open escalations to :team.',
            'submit' => 'Add to escalation',
            'success' => 'Added to the escalation',
            'success_to' => 'Added to the :team escalation',
            'escalated_at' => 'Escalated',
            'attached' => 'Tickets attached',
        ],

        'remove_from_escalation' => [
            'label' => 'Remove from escalation',
            'modal_description' => 'Take this ticket out of its escalation? The escalation stays open.',
            'submit' => 'Remove',
            'success' => 'Removed from the escalation',
            'success_to' => 'Removed from the :team escalation',
        ],

        'create_linked_ticket' => [
            'label' => 'Escalate Ticket',
            'label_to' => 'Escalate to :team',
            'tooltip' => 'Open a separate ticket for outside help.',
            'tooltip_to' => 'Open a separate ticket for :team.',
            'modal_heading' => 'Escalate Ticket',
            'modal_heading_to' => 'Escalate to :team',
            'modal_description' => 'Opens a separate ticket for outside help. This ticket stays with your team.',
            'submit' => 'Escalate',
            'modal_description_to' => 'Opens a separate ticket for :team. This ticket stays with your team.',

            'form' => [
                'panel' => 'Escalate to',
                'subject' => 'Subject',
                'message' => 'Instructions',
                'message_helper' => 'What you need, what you tried, and how to reproduce it.',
            ],

            'notifications' => [
                'success' => [
                    'title' => 'Ticket escalated',
                    'body' => 'A separate ticket was opened for the team you escalated to. This ticket is unchanged.',
                    'body_to' => 'A separate ticket was opened for :team. This ticket is unchanged.',
                    'action_label' => 'Show escalated ticket',
                ],

                'not_configured' => [
                    'title' => 'Ticket not escalated',
                    'body' => 'Tickets cannot be escalated to :panel yet because it has no ticket statuses or priorities set up for your organization.',
                ],
            ],
        ],
    ],

    'linked_view' => [
        'show' => 'Show linked ticket',
        'hide' => 'Hide linked ticket',
        'read_only' => 'Read-only',
        'switch' => 'Original tickets',
        'reply_on_original' => 'Reply on :name\'s original ticket',
        'reply_on_this_original' => 'Reply on the original ticket',
        'reply_on_escalation_with' => 'Reply on the escalation with :organization',
        'reply_on_escalation' => 'Reply on this escalation',
        'original_heading' => ':name\'s original ticket',
        'original_heading_unnamed' => 'Original ticket',
        'escalation_heading' => 'Escalation',
        'escalation_heading_to' => 'Escalation to :team',
    ],

    'ticket_number' => 'Ticket #:id',

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
        'resolve_confirm' => [
            'heading' => 'Resolve this ticket?',
            'description' => 'This closes the ticket and lets support know. Nobody can reply to it after that, and it can\'t be reopened.',
            'submit' => 'Resolve ticket',
        ],
        'reply' => 'Reply',
        'send_reply' => 'Send reply',
        'unread' => 'Unread',
        'status' => 'Status',
        'priority' => 'Priority',
        'assignee' => 'Assigned to',
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
            'ticket_number' => 'Ticket #',
            'subject' => 'Subject',
            'assignee' => 'Assigned to',
            'unassigned' => 'Unassigned',
            'assigned_elsewhere' => 'Assigned outside your team',
            'submitter' => 'Requested by',
            'escalated_by' => 'Escalated by',
            'source_panel' => 'Source Panel',
            'panel' => 'Panel',
            'last_message' => 'Last Message',
            'no_messages' => 'No messages yet',
            'closed_at' => 'Closed At',
            'disposition' => 'Disposition',
            'linked_tickets' => 'Escalation',
            'linked_tickets_help' => 'An escalation is a separate ticket for the team you escalate to. The original stays with the team that received it, and the two teams talk on the escalated ticket.',
            'linked_tickets_help_to' => 'An escalation is a separate ticket for :team. The original stays with the team that received it, and the two teams talk on the escalated ticket.',
            'linked_tickets_description' => [
                'escalated_to_you' => 'Your team escalated this. Talk to the team handling it here.',
                'escalated_to_you_to' => 'Your team escalated this to :team. Talk to them here.',
                'escalated_from' => 'You\'re talking with the team that escalated this. Use Original conversation to read what was asked.',
                'escalated_from_beside' => 'You\'re talking with the team that escalated this. Choose Show linked ticket to read what was asked beside the chat.',
            ],
            'membership' => '{0} Escalated.|{1} Escalated, with 1 other ticket.|[2,*] Escalated, with :count other tickets.',
            'membership_to' => '{0} Escalated to :team.|{1} Escalated to :team, with 1 other ticket.|[2,*] Escalated to :team, with :count other tickets.',
            'membership_replies' => 'Replies appear on the escalation ticket.',
            'membership_replies_to' => ':team\'s replies appear on the escalation ticket.',
            'view_escalation' => 'View escalation',
            'parent_ticket' => 'Escalated ticket',
            'parent_ticket_placeholder' => 'Not escalated',
            'child_tickets' => 'Original tickets',
            'child_tickets_placeholder' => 'No original tickets',
            'link_existing_ticket' => 'Link existing',
            'requested_by_line' => 'Requested by :name',
            'link_refused' => [
                'title' => 'Ticket not linked',
                'already_escalated' => 'This ticket is already part of another escalation. Remove it from that escalation before adding it here.',
                'not_linkable' => 'That ticket can no longer be linked here. It may have been closed, or linked elsewhere in the meantime.',
            ],
            'assign_to_supporter' => 'Assign to',
            'assigned_successfully' => 'Tickets assigned successfully',
            'invalid_assignee' => 'Invalid assignee selected',
            'unauthorized_assignment' => 'Some tickets were skipped because you are not authorized to manage them',

            'tabs' => [
                'all' => 'All Tickets',
                'my' => 'My Tickets',
                'linked' => 'Escalated Tickets',
                'my_linked' => 'My Escalated Tickets',
            ],

            'badges' => [
                'my' => 'Open tickets assigned to you',
                'tab' => 'Open tickets in this tab',
            ],

            'tab_descriptions' => [
                'all' => 'Every ticket your team handles. Tickets waiting on support come first.',
                'all_submitter' => 'Tickets you submitted.',
                'my' => 'Tickets assigned to you. Use Reassign to hand one to a teammate.',
                'linked' => 'Tickets your team escalated. Each is a separate ticket; the original stays under All Tickets.',
                'linked_to' => 'Tickets your team escalated to :team. Each is a separate ticket; the original stays under All Tickets.',
                'my_linked' => 'Tickets you escalated.',
                'my_linked_to' => 'Tickets you escalated to :team.',
            ],

            'field_help' => [
                'label' => 'What do these mean?',
                'heading' => 'Ticket details',
            ],

            'hints' => [
                'status' => 'Where the ticket is in your team\'s process.',
                'priority' => 'How urgent the ticket is.',
                'disposition' => 'Why the ticket was closed.',
                'submitter' => 'The person who asked for help. Your replies go to them.',
                'escalated_by' => 'The person who escalated this. Your replies go to them.',
                'assignee' => 'The person responsible for this ticket.',
                'turn' => 'Who owes the next reply. It switches each time someone replies.',
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

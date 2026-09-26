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
            'modal_heading' => 'Close Ticket',
            'disposition' => [
                'label' => 'Disposition',
            ],
        ],

        'reassign' => [
            'label' => 'Reassign',
            'label_unassigned' => 'Assign',
            'modal_heading' => 'Reassign ticket',
            'modal_description' => 'Hand this ticket to someone else on your team. It moves to their "My Tickets" list. The conversation and the person who submitted it stay the same.',
            'currently_assigned' => 'It is assigned to :name now.',
            'modal_description_escalation' => 'To get help from outside your team, use Escalate Ticket instead.',
            'modal_description_escalation_to' => 'To send it to :team, use Escalate to :team instead.',
            'new_assignee' => 'Assign to',
            'nobody_to_assign' => 'Nobody else can be assigned this ticket.',
            'who_can_be_assigned' => 'Only people who answer tickets here can be assigned tickets. Ask an administrator to give a teammate that access.',
            'assign_to_me' => 'Assign to me',
            'submit' => 'Reassign',
            'success' => 'Ticket reassigned',
        ],

        'view_original_conversation' => [
            'label' => 'Original conversation',
            'modal_heading' => 'Original conversation',
            'modal_description' => 'A read-only copy of the conversation on the ticket this one was escalated from. Reply on this ticket instead; the person who escalated it passes your answer on.',
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
            'modal_description' => 'Pick the escalation this ticket belongs with. Only your team\'s open escalations are listed.',
            'modal_description_to' => 'Pick the escalation to :team this ticket belongs with. Only your team\'s open escalations are listed.',
            'submit' => 'Add to escalation',
            'success' => 'Added to escalation #:id',
            'escalated_at' => 'Escalated',
            'attached' => 'Tickets attached',
        ],

        'remove_from_escalation' => [
            'label' => 'Remove from escalation',
            'modal_description' => 'This ticket will no longer be part of escalation #:id. The escalation stays open for any other tickets in it, and this ticket stays with your team.',
            'submit' => 'Remove',
            'success' => 'Removed from escalation #:id',
        ],

        'create_linked_ticket' => [
            'label' => 'Escalate Ticket',
            'label_to' => 'Escalate to :team',
            'tooltip' => 'For problems your team cannot resolve. Opens a separate ticket for the team you escalate to.',
            'tooltip_to' => 'For problems your team cannot resolve. Opens a separate ticket for :team.',
            'modal_heading' => 'Escalate Ticket',
            'modal_heading_to' => 'Escalate to :team',
            'modal_description' => 'Use this when your team cannot resolve the ticket. It opens a separate ticket for the team you escalate to, with you as its requester. This ticket stays open with your team, and you keep working with the person who submitted it.',
            'submit' => 'Escalate',
            'modal_description_to' => 'Use this when your team cannot resolve the ticket. It opens a separate ticket for :team, with you as its requester. This ticket stays open with your team, and you keep working with the person who submitted it.',

            'form' => [
                'panel' => 'Escalate to',
                'subject' => 'Subject',
                'message' => 'Instructions',
                'message_helper' => 'Say what you need, what you have already tried, and how to reproduce the problem. Replies come to you, not to the person who submitted this ticket.',
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
            'linked_tickets_description' => [
                'not_escalated' => 'Not escalated. If your team cannot resolve this ticket, use Escalate Ticket to get help from outside your team.',
                'not_escalated_to' => 'Not escalated. If your team cannot resolve this ticket, use Escalate to :team.',
                'escalated' => 'Escalated. The team it went to replies to you on the escalated ticket below. This ticket stays with your team and the person who submitted it.',
                'escalated_to' => 'Escalated to :team. They reply to you on the escalated ticket below. This ticket stays with your team and the person who submitted it.',
                'escalated_to_you' => 'Your team escalated the ticket below. Talk to the team handling it here, and keep the person who asked updated on the original ticket.',
                'escalated_to_you_to' => 'Your team escalated the ticket below to :team. Talk to :team here, and keep the person who asked updated on the original ticket.',
                'escalated_from' => 'Escalated from the original tickets below. You are talking with the person who escalated them, and they pass your answers on to whoever asked on each one. Use "Original conversation" to read them.',
            ],
            'parent_ticket' => 'Escalated ticket',
            'parent_ticket_placeholder' => 'Not escalated',
            'child_tickets' => 'Original tickets',
            'child_tickets_placeholder' => 'No original tickets',
            'link_existing_ticket' => 'Link existing',
            'part_of_escalation' => '{0} Part of escalation :link.|{1} Part of escalation :link, with 1 other ticket.|[2,*] Part of escalation :link, with :count other tickets.',
            'requested_by_line' => 'Requested by :name',
            'link_refused' => [
                'title' => 'Ticket not linked',
                'already_escalated' => 'This ticket is already part of escalation #:id. Remove it from that escalation before adding it to another.',
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
                'linked' => 'Open tickets your team escalated',
                'my_linked' => 'Open tickets you escalated',
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
                'status' => 'Where the ticket is in your team\'s process. Use Close when it is resolved.',
                'priority' => 'How urgent the ticket is, for sorting your team\'s work.',
                'disposition' => 'Why the ticket was closed.',
                'submitter' => 'The person who asked for help. Replies in the conversation go to them.',
                'escalated_by' => 'The person who escalated this ticket from their team. Replies in the conversation go to them, and they pass answers on to whoever asked originally.',
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

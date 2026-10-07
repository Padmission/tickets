<?php

return [
    'side_you' => 'You',

    'duplicates' => [
        'badge' => 'Duplicate',
        'heading' => 'Duplicates',
        'original' => 'Duplicate of',
    ],

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
        'close_as_duplicate' => [
            'label' => 'Close as duplicate',
            'modal_heading' => 'Close this ticket as a duplicate?',
            'modal_description' => 'This ticket closes with a link to the original. Its messages and attachments stay here. The requester is not notified.',
            'submit' => 'Close as duplicate',
            'original' => 'Original ticket',
            'original_help' => 'Search by subject or ticket number. If you choose a duplicate, its original is used.',
            'invalid_original' => 'Choose another ticket from the same organization and panel with a valid original.',
            'invalid_disposition' => 'Choose a disposition offered for this ticket.',
            'escalation' => 'Tickets involved in an escalation cannot be closed as duplicates. Use Add to an existing escalation instead.',
            'success' => 'Ticket closed as duplicate',
        ],
        'open_ticket_for_contact' => [
            'modal_description' => 'For someone at an organization who answers its tickets and asked you directly, such as by phone or email. It\'s listed as their question, and you handle it.',
            'organization' => 'Organization',
            'contact' => 'Person',
            'nobody' => 'Nobody at this organization answers tickets yet.',
            'regular_users' => 'For a regular user\'s problem, their organization\'s support team opens the ticket.',
            'submit' => 'Open ticket',
            'invalid_contact' => 'Choose someone who answers tickets at that organization.',
            'not_configured' => 'Ticket statuses or priorities aren\'t set up for that organization yet.',
            'created' => [
                'title' => 'Ticket opened',
                'body' => ':name gets an email saying you opened it for them. It\'s assigned to you.',
            ],
        ],

        'start_ticket' => [
            'label' => 'New ticket',
            'kind' => [
                'label' => 'Who the ticket is for',
                'organization' => 'For someone in your organization',
                'organization_description' => 'A user who phoned or emailed you.',
                'escalation' => 'A question for the team you escalate to',
                'escalation_to' => 'A question for :team',
                'escalation_description' => 'Your own question. It\'s listed under Escalations.',
            ],
            'requester' => 'Requested by',
            'requester_helper' => ':name gets the new-ticket email, saying you opened it for them.',
            'assign' => 'Assigned to',
            'assign_me' => 'Me',
            'assign_colleague' => 'A colleague',
            'assign_automatic' => 'Automatic',
            'colleague' => 'Colleague',
            'panel' => 'Send to',
            'subject' => 'Subject',
            'message' => 'Message',
            'message_to' => 'Message to :name',
            'message_to_team' => 'Message to the team you escalate to',
            'message_to_team_to' => 'Message to :team',
            'message_helper' => ':name gets this as the first message, in the new-ticket email.',
            'message_helper_team' => 'What you need, what you tried, and how to reproduce it.',
            'attachments' => 'Attachments',
            'escalate_instead' => 'About a ticket someone already opened? Escalate that ticket instead, so the two stay connected.',
            'submit' => 'Create ticket',
            'submit_team' => 'Send to the team you escalate to',
            'submit_team_to' => 'Send to :team',
            'invalid_requester' => 'Choose someone from your organization to open the ticket for.',
            'invalid_assignee' => 'Choose a colleague who answers tickets here.',
            'created' => [
                'title' => 'Ticket created',
                'body' => ':name gets an email saying you opened this ticket for them.',
                'assigned' => 'It\'s assigned to :name.',
                'assigned_you' => 'It\'s assigned to you.',
                'unassigned' => 'It isn\'t assigned to anyone yet.',
            ],
            'sent' => [
                'title' => 'Sent to the team you escalate to',
                'title_to' => 'Sent to :team',
                'body' => 'Their replies come to you here and under Escalations.',
                'body_to' => ':team\'s replies come to you here and under Escalations.',
            ],
        ],

        'close' => [
            'label' => 'Close',
            'modal_heading' => 'Close this ticket?',
            'modal_heading_escalation' => 'Close escalation?',
            'modal_description' => 'Anyone who replies to it later is asked whether to reopen it.',

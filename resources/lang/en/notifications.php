<?php

return [
    'general' => [
        'recent-activity' => 'Recent Activity',
        'more-activities' => 'There are more messages on this ticket that are not shown in this email. View the ticket to see all messages.',
        'action' => 'View Ticket',
        'sender-you' => 'You',
        'sender-support' => 'Support',
    ],

    'ticket-created' => [
        'subject' => 'New ticket #:ticket_id – :subject',
        'headline' => 'New Ticket',
        'intro' => 'A new ticket has been created.',
        'outro' => 'Please review the ticket details and respond as needed.',
    ],

    'ticket-activity' => [
        'subject' => 'Ticket updated #:ticket_id – :subject',
        'headline' => 'Recent Ticket Activity',
        'intro' => 'Your ticket has had some activity since you were last online. Please visit the website to view the full conversation.',
        'outro' => '',
    ],

    'ticket-assigned' => [
        'subject' => 'Ticket assigned to you #:ticket_id – :subject was assigned to you',
        'headline' => 'Ticket Assigned',
        'intro' => 'A ticket has been assigned to you for handling.',
        'outro' => 'Please review the ticket and provide your assistance.',
    ],

    'ticket-closed' => [
        'subject' => 'Ticket closed #:ticket_id – :subject',
        'headline' => 'Ticket Closed',
        'intro' => 'Your ticket has been closed.',
        'outro' => 'If you need further assistance, please create a new ticket.',
    ],

    'otp-verification' => [
        'subject' => 'Verify Your Email Address',
        'message' => 'Please verify your email address by entering the following verification code. This code will expire in 10 minutes.',
        'expires-hint' => 'This code will expire in :minutes minutes',
    ],
];

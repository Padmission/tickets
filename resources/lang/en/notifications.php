<?php

return [
    'ticket-history' => [
        'subject' => 'Your ticket has been updated',
        'message' => 'A new ticket has been created with the subject: :subject',
        'more-activities' => 'There are more activities on this ticket that are not shown in this email. View the ticket to see all activities.',
        'action' => 'View Ticket',
        'activities-header' => 'Recent Ticket Activity',
        'intro' => 'There has been new activity on your ticket.',
        'outro' => 'To view the entire ticket, visit the link above.',
    ],

    'ticket-created' => [
        'subject' => 'New ticket created: :subject (#:ticket_id)',
        'intro' => 'A new ticket has been created and assigned to you.',
        'outro' => 'Please review the ticket details and respond as needed.',
    ],

    'ticket-assigned' => [
        'subject' => 'Ticket assigned to you: :subject (#:ticket_id)',
        'intro' => 'A ticket has been assigned to you for handling.',
        'outro' => 'Please review the ticket and provide your assistance.',
    ],

    'ticket-closed' => [
        'subject' => 'Ticket closed: :subject (#:ticket_id)',
        'intro' => 'Your ticket has been closed.',
        'outro' => 'If you need further assistance, please create a new ticket.',
    ],

    'ticket-activity' => [
        'subject' => 'New activity on ticket: :subject (#:ticket_id)',
        'intro' => 'There has been new activity on your ticket.',
        'outro' => 'Please check the ticket for the latest updates.',
    ],

    'rate-limit' => [
        'title' => 'Too Many Notifications',
        'message' => 'You have reached the maximum number of notifications per hour. Please try again later.',
    ],

    'otp-verification' => [
        'subject' => 'Verify Your Email Address',
        'message' => 'Please verify your email address by entering the following verification code. This code will expire in 10 minutes.',
    ],
];

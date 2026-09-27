<?php

return [
    'general' => [
        'recent-activity' => 'Recent Activity',
        'more-activities' => 'There are more messages on this ticket that are not shown in this email. View the ticket to see all messages.',
        'action' => 'View Ticket',
        'sender-you' => 'You',
        'sender-support' => 'Support',
        'ticket_label' => 'Ticket:',
        'assigned_to_label' => 'Assigned to:',
        'closed_as_label' => 'Closed as:',
        'action_escalation' => 'Open the escalation',
        'action_original' => 'Open :name\'s ticket',
        'action_original_unnamed' => 'Open the original ticket',
        'action_escalations' => 'View your team\'s escalations',
    ],

    'ticket-created' => [
        'subject' => 'New ticket #:ticket_id – :subject',
        'headline' => 'New Ticket',
        'intro' => 'A new ticket has been created.',
        'intro_requester' => 'We\'ve received your support request and will follow up as soon as we can.',
        'unassigned' => 'A support specialist will be assigned shortly.',
        'outro' => 'Please review the ticket details and respond as needed.',
    ],

    'ticket-activity' => [
        'subject' => 'Ticket updated #:ticket_id – :subject',
        'headline' => 'Recent Ticket Activity',
        'intro' => 'Your ticket has had some activity since you were last online. Please visit the website to view the full conversation.',
        'outro' => '',
        'subject_escalation_to' => ':team replied on your escalation #:ticket_id – :subject',
        'subject_escalation' => 'The team you escalated to replied on your escalation #:ticket_id – :subject',
        'headline_escalation' => 'Reply on your escalation',
        'intro_escalation_to' => ':team replied on your escalation about :originals. Answer :team on the escalation, or pass the answer on to the requester on their own ticket.',
        'intro_escalation' => 'The team you escalated to replied on your escalation about :originals. Answer them on the escalation, or pass the answer on to the requester on their own ticket.',
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
        'latest_reply' => 'Latest reply from support:',
        'latest_reply_to' => 'Latest reply from :team:',
        'subject_escalation_to' => ':team closed your escalation #:ticket_id – :subject',
        'subject_escalation' => 'The team you escalated to closed your escalation #:ticket_id – :subject',
        'headline_escalation' => 'Escalation closed',
        'intro_escalation_open_to' => '{1} :team closed your escalation about :originals. :name\'s ticket is still open. Update them and close it when they\'re done.|[2,*] :team closed your escalation about :originals. :count of its tickets are still open. Update the requesters and close each one when they\'re done.',
        'intro_escalation_open' => '{1} The team you escalated to closed your escalation about :originals. :name\'s ticket is still open. Update them and close it when they\'re done.|[2,*] The team you escalated to closed your escalation about :originals. :count of its tickets are still open. Update the requesters and close each one when they\'re done.',
        'intro_escalation_open_unnamed_to' => ':team closed your escalation about :originals. Its ticket is still open. Update the requester and close it when they\'re done.',
        'intro_escalation_open_unnamed' => 'The team you escalated to closed your escalation about :originals. Its ticket is still open. Update the requester and close it when they\'re done.',
        'intro_escalation_to' => ':team closed your escalation about :originals.',
        'intro_escalation' => 'The team you escalated to closed your escalation about :originals.',
    ],

    'ticket-handedover' => [
        'subject' => 'Escalation handed to you #:ticket_id – :subject',
        'headline' => 'Escalation handed to you',
        'intro_to' => ':actor handed you the escalation to :team about :originals. :team\'s replies now come to you.',
        'intro' => ':actor handed you the escalation about :originals. Replies from the team you escalated to now come to you.',
        'intro_unnamed_to' => 'The escalation to :team about :originals was handed to you. :team\'s replies now come to you.',
        'intro_unnamed' => 'The escalation about :originals was handed to you. Replies from the team you escalated to now come to you.',
        'subject_taken' => 'Escalation taken over #:ticket_id – :subject',
        'headline_taken' => 'Escalation taken over',
        'intro_taken_to' => ':actor took over the escalation to :team about :originals. :team\'s replies now go to them.',
        'intro_taken' => ':actor took over the escalation about :originals. Replies from the team you escalated to now go to them.',
        'intro_taken_unnamed_to' => 'A colleague took over the escalation to :team about :originals. :team\'s replies now go to them.',
        'intro_taken_unnamed' => 'A colleague took over the escalation about :originals. Replies from the team you escalated to now go to them.',
        'outro' => '',
    ],

    'otp-verification' => [
        'subject' => 'Verify Your Email Address',
        'message' => 'Please verify your email address by entering the following verification code. This code will expire in 10 minutes.',
        'expires-hint' => 'This code will expire in :minutes minutes',
    ],
];

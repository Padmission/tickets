<?php

return [
    'open_modal' => 'Open support chat',

    'defaults' => [
        'intro_message' => 'Our support team of real people is here to help. Please give us as much detail as possible and we will respond as soon as someone is available.',
        'auto_response' => 'Thanks for your message! We will respond soon.',
    ],
    'close_modal' => 'Close modal',

    'errors' => [
        'unknown' => 'Unknown error',
        'too_many_requests' => 'Too many requests. Retry in :seconds seconds.',
    ],

    'chat' => [
        'error' => 'Sorry, something went wrong. Please try again.',
        'max_file_size' => 'The max file size is :size.',
        'droparea' => 'Drop to add files',
        'send' => 'Send',
        'placeholder' => 'Start typing …',
        'placeholder_reply_to' => 'Reply to :name…',
        'closed_empty' => 'No messages. This ticket was closed :time, so replies are off.',
        'new_messages' => 'New messages',
        'add_attachments' => 'Add attachments',
        'bold' => 'Bold',
        'link' => 'Link',
        'unordered_list' => 'Unordered List',
        'ordered_list' => 'Ordered List',
        'command_key' => 'Command-Key',
        'enter_key' => 'Enter-Key',
        'lock_turn' => 'We still owe a reply',
        'lock_turn_help' => 'Tick this if your team still needs to follow up. Otherwise the ticket waits on the requester after you send.',
        'closed_on' => 'This ticket was closed on :date.',
        'default_subject' => 'Support request',
        'reopen_dialog' => [
            'heading_same_problem' => 'This ticket is closed. Is this the same problem?',
            'body_same_problem' => 'Reopen it to send your message, or start a new ticket with it that links back to this one.',
            'heading_too_old' => 'This ticket closed more than :days days ago.',
            'body_too_old' => 'Your message starts a new ticket that links back to this one.',
            'heading_staff' => 'This ticket is closed.',
            'body_staff' => 'Sending your message reopens it.',
            'reopen' => 'Reopen and send',
            'new_ticket' => 'Start a new ticket',
            'cancel' => 'Cancel',
        ],
        'send_keep_waiting' => 'Send as update',
        'send_keep_waiting_help' => 'Sends your reply as an update and keeps Waiting on with your team, since you still owe the answer.',

        'screenshot' => [
            'capture' => 'Capture screenshot',
            'permission_denied' => 'Permission denied',
            'failed' => 'Failed to capture screenshot',
        ],
    ],

    'list' => [
        'heading' => 'Support Center',
        'subheading' => 'How can we help you?',
        'create_ticket' => 'Open New Ticket',
        'go_to_docs' => 'Open Documentation',
        'tickets_heading' => 'Your Tickets',
        'no_tickets' => 'No tickets yet',
        'no_open_tickets' => 'No open tickets',
        'show_closed' => 'Show closed',
        'no_messages' => 'No messages yet',
        'needs_attention' => 'Needs attention',
    ],

    'view' => [
        'back' => 'Back to ticket list',
        'new_chat' => 'New Chat',
    ],

    'otp_request' => [
        'heading' => 'Please verify your email address to open a ticket.',
        'description' => 'If your email is registered in our system, we will send a verification code to confirm your identity.',
        'email_label' => 'Email',
        'submit_button' => 'Submit',

        'errors' => [
            'rate_limited' => 'Please retry in :seconds seconds.',
        ],
    ],

    'otp_verify' => [
        'heading' => 'Please enter the code we sent you.',
        'description' => 'Please enter the verification code we sent to your email.',
        'label' => 'Your code',
        'submit_button' => 'Submit',

        'errors' => [
            'expired' => 'Your verification code expired.',
            'invalid_otp' => 'Invalid OTP',
        ],
    ],
];

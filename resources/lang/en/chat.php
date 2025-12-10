<?php

return [
    'open_modal' => 'Open support chat',
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
        'new_messages' => 'New messages',
        'add_attachments' => 'Add attachments',
        'bold' => 'Bold',
        'link' => 'Link',
        'unordered_list' => 'Unordered List',
        'ordered_list' => 'Ordered List',
        'command_key' => 'Command-Key',
        'enter_key' => 'Enter-Key',
        'lock_turn' => 'Lock turn to supporter',

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

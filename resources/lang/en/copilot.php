<?php

return [
    'escalation_offers' => [
        'missing_payment' => 'This looks like a missing payment question. A Padmission specialist can pull the full disbursement log and confirm what happened.',
        'permission_denied' => 'This may need a permission review. A Padmission specialist can confirm access and next steps.',
        'stale_state' => 'This record may have changed recently. A Padmission specialist can review the latest activity before you act.',
        'pii_sensitive' => 'This question may involve sensitive personal information. A Padmission specialist can continue in the ticket thread.',
        'financial_reconciliation' => 'This looks like a payment reconciliation question. A Padmission specialist can pull the full disbursement log.',
        'audit_history' => 'This looks like an audit history question. A Padmission specialist can review the full change trail.',
        'low_confidence' => 'I am not confident enough to close this out. A Padmission specialist can review it with you.',
        'repeated_unresolved' => 'This still seems unresolved. A Padmission specialist can pick up the thread from here.',
    ],
];

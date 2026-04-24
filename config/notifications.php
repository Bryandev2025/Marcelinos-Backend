<?php

return [
    'refund_guest_eligible_enabled' => filter_var(env('REFUND_GUEST_ELIGIBLE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'refund_guest_completed_enabled' => filter_var(env('REFUND_GUEST_COMPLETED_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'refund_staff_alert_enabled' => filter_var(env('REFUND_STAFF_ALERT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'refund_staff_recipients' => array_values(array_filter(array_map(
        static fn (string $email): string => trim($email),
        explode(',', (string) env('REFUND_STAFF_RECIPIENTS', ''))
    ))),
];

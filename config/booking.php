<?php

return [

    'pending_verification_url_ttl_hours' => (int) env('BOOKING_VERIFY_EMAIL_TTL_HOURS', 72),

    'pending_verification_prune_hours' => (int) env('BOOKING_PENDING_VERIFICATION_PRUNE_HOURS', 48),

];

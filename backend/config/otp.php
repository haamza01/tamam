<?php

return [
    'driver' => env('OTP_DRIVER', 'log'),
    'length' => 6,
    'expiry_seconds' => 300,
    'max_attempts' => 5,
    'resend_cooldown_seconds' => 60,
    'purposes' => [
        'phone_verify',
        'password_reset',
    ],
];

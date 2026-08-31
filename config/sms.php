<?php

return [
    'default' => env('SMS_DEFAULT_DRIVER', 'log'),
    'fallback' => env('SMS_FALLBACK_DRIVER', ''),
    'local_number_regex' => env('SMS_NUMBER_REGEX', '/^(?:\+8801|8801|01)[135-9](?:\d{8})$/'),
    'drivers' => [
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'number' => env('TWILIO_NUMBER'),
        ],
        'alphabd' => [
            'sender_id' => env('ALPHABD_SENDER_ID'),
            'api_key' => env('ALPHABD_API_KEY'),
            'api_url' => env('ALPHABD_API_URL', 'https://api.sms.net.bd/sendsms'),
        ],
        'bulksmsdhaka' => [
            'caller_id' => env('BULKSMSDHAKA_CALLER_ID'),
            'api_key' => env('BULKSMSDHAKA_API_KEY'),
            'api_url' => env('BULKSMSDHAKA_API_URL', 'https://bulksmsdhaka.net/api/sendtext'),
        ],
        'sslwireless' => [
            'sid' => env('SSLWIRELESS_SID'),
            'api_token' => env('SSLWIRELESS_API_TOKEN'),
            'secret_key' => env('SSLWIRELESS_SECRET_KEY'),
            'api_url' => env('SSLWIRELESS_API_URL', 'https://smsplus.sslwireless.com/api/v3'),
        ],
    ],
];

<?php

return [
    'default' => env('SMS_DEFAULT_DRIVER', 'log'),
    'fallback' => env('SMS_FALLBACK_DRIVER', ''),
    'local_number_regex' => env('SMS_NUMBER_REGEX', '/^(?:\+8801|8801|01)[135-9](?:\d{8})$/'),
    'drivers' => [
        'onnorokom' => [
            'userName' => env('SMS_USER'),
            'userPassword' => env('SMS_PASSWORD'),
            'maskName' => '',
        ],
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
    ],
];

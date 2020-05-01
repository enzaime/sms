<?php

return [
    'default' => env('SMS_DEFAULT_DRIVER', 'onnorokom'),
    'fallback' => env('SMS_FALLBACK_DRIVER', 'twilio'),
    'local_number_regex' => env('SMS_NUMBER_REGEX', '/^(?:\+8801|8801|01)[135-9](?:\d{8})$/'),
    'drivers' => [
        'onnorokom' =>[
            'userName' => env('SMS_USER'),
            'userPassword' => env('SMS_PASSWORD'),
            'maskName' => ''
        ],
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'number' => env('TWILIO_NUMBER')
        ]
    ]
];
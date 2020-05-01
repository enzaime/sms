### Integration

Add the following repository to project's `composer.json`.

    "repositories": [
        {
            "type": "vcs",
            "url": "https://gitlab.com/enzaime/sms.git"
        },
        ....
    ],

Now, run `composer require enzaime/sms` command from your project terminal.

### Environment Variable

Modify the `.env` file to set the credentials;

    SMS_USER=onnorokom-user
    SMS_PASSWORD=onnorokom-pass

    TWILIO_SID=
    TWILIO_AUTH_TOKEN=
    TWILIO_NUMBER=

> Recipient number must be international format(+8801xxxxxxxxx) for twilio driver. 

### Example

Using Facade

    EnzSms::send('01xxxxxxxxx', 'Testing');

Using `SmsService` Class

    $sms = new \Enzaime\Sms\SmsService();
    $sms->send('01xxxxxxxxx', 'Testing');

Specify driver

    EnzSms::driver('twilio')->send('+8801xxxxxxxxx', 'Testing');

If you do not specify the driver then SMS will be sent to **BD** numbers through `onnorokom` and to **foreign** numbers through `twilio`.

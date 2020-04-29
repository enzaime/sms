### Integration

Add the following repository to project's `composer.json`.

    "repositories": [
        {
            "type": "vcs",
            "url": "https://gitlab.com/enzaime/sms.git"
        },
        ....
    ],

After adding the following code  run `composer require enzaime/sms` command from your project terminal.

### Environment Variable

Modify the `.env` file to set the credentials;

    SMS_USER=
    SMS_PASSWORD= 

### Example

Using Facade

    EnzSms::oneToOne('0171065xxxx', 'Testing');

Using `SmsService` Class

    $sms = new \Enzaime\Sms\SmsService();
    $sms->oneToOne('0171065xxxx', 'Testing');

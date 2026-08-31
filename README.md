# SMS Package for Laravel

Easily integrate multiple SMS gateways into your Laravel application with a unified API.

---

## Supported Gateways

- [Twilio](https://www.twilio.com)
- [AlphaBd](https://alpha.net.bd/SMS/)
- [BulkSmsDhaka](https://bulksmsdhaka.net)
- [SSL Wireless (ISMS Plus)](https://ismsplus.sslwireless.com/api-documentation)
- **Log** (for local/staging environments)

---

## Installation

1. **Add the repository to your `composer.json`:**

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://gitlab.com/enzaime/sms.git"
    }
]
```

2. **Require the package:**

```bash
composer require enzaime/sms
```

---

## Requirements & Notes

> **Twilio Driver:**
> - Requires the [Twilio PHP SDK](https://github.com/twilio/twilio-php) (`twilio/sdk`).
> - This package includes it, but if you use Twilio elsewhere, ensure it is installed:
>
>   ```bash
>   composer require twilio/sdk
>   ```

---

## Configuration

Add the following to your `.env` file:

```env
SMS_DEFAULT_DRIVER=twilio|alpha_bd|bulk_sms_dhaka|ssl_wireless|log

TWILIO_SID=your-twilio-sid
TWILIO_AUTH_TOKEN=your-twilio-auth-token
TWILIO_NUMBER=your-twilio-number

BULKSMSDHAKA_API_KEY=your-bulksmsdhaka-api-key
BULKSMSDHAKA_CALLER_ID=your-bulksmsdhaka-caller-id

SSLWIRELESS_API_TOKEN=your-isms-plus-api-token
SSLWIRELESS_SID=your-approved-sender-id
# Only needed for the secure OTP route
SSLWIRELESS_SECRET_KEY=your-isms-plus-secret-key
# Optional; defaults to https://smsplus.sslwireless.com/api/v3
SSLWIRELESS_API_URL=https://smsplus.sslwireless.com/api/v3
```

> **Twilio:** Recipient number must be in international format (e.g., `+8801xxxxxxxxx`).

---

## SSL Wireless (ISMS Plus)

```php
EnzSms::driver('ssl_wireless')->send('01xxxxxxxxx', 'Testing');
EnzSms::driver('ssl_wireless')->send(['01xxxxxxxxx', '01yyyyyyyyy'], 'Testing');
```

Things worth knowing before you point production at it:

- **The sending IP must be whitelisted** in the ISMS Plus portal, or every
  request comes back `4003 IP Blacklisted` — as an HTTP 200.
- **`send()` returns the number of recipients the gateway accepted**, which is
  not always the number it was handed: the gateway reports a verdict per
  recipient in `smsinfo`, and every refusal is logged with its reason.
- **Numbers are normalised to MSISDN form** (`01712345678` and
  `+8801712345678` both become `8801712345678`), and a number repeated within
  one call is sent once — the gateway refuses a duplicate rather than
  delivering it twice.
- **A list is split at 100 recipients per request**, the gateway's cap, and
  goes to the bulk endpoint; a single number goes to the single-send endpoint.
- Neither the API token nor the message body ever reaches a log line.

### Secure OTP route

One-time codes have their own endpoint, and it is worth using: the body is
encrypted, the request is signed, and the gateway rate-limits it per recipient
(code 4033) in a way the ordinary route does not.

```php
EnzSms::driver('ssl_wireless')->sendOtp('01xxxxxxxxx', 'Your code is 123456');
```

- Requires `SSLWIRELESS_SECRET_KEY` (the **Secret Key** from the portal, which
  is not the API key). Without it `sendOtp()` refuses and logs — it will not
  quietly fall back to the plain route and put the code on the wire in clear
  text.
- The body is AES-256-CBC encrypted under a key derived from the Secret Key,
  with a fresh IV per message, and the request carries an HMAC-SHA256
  `X-Signature`. A `4035` in the log means the signature did not match.
- The route takes one recipient at a time; a list becomes one request each.
- Drivers offering this expose `Enzaime\Sms\Contracts\OtpContract`, so a
  caller can check before reaching for it:

```php
$driver = EnzSms::getDriver('ssl_wireless');

if ($driver instanceof \Enzaime\Sms\Contracts\OtpContract) {
    $driver->sendOtp($number, $code);
}
```

---

## Log Driver (Local & Staging)

The **Log** driver is ideal for development and staging. Instead of sending SMS, it logs messages to your Laravel log files.

**To use:**

- Set in `.env`:
  ```env
  SMS_DEFAULT_DRIVER=log
  ```
- Or specify in code:
  ```php
  EnzSms::driver('log')->send('01xxxxxxxxx', 'This will be logged, not sent');
  ```

Check `storage/logs/laravel.log` for logged SMS messages.

---

## Usage Examples

### Using the Facade

```php
EnzSms::send('01xxxxxxxxx', 'Testing');
```

### Using the SmsService Class

```php
$sms = new \Enzaime\Sms\SmsService();
$sms->send('01xxxxxxxxx', 'Testing');
```

### Specify a Driver

```php
EnzSms::driver('twilio')->send('+8801xxxxxxxxx', 'Testing');
```

> **Note:**
> - If no driver is specified, Bangladeshi numbers use the default driver (`bulk_sms_dhaka` or `alpha_bd`), foreign numbers use `twilio`.

---

## API Reference

### EnzSms Facade Methods

- `driver(string $name = '')` — Set the driver for sending SMS. Returns the SmsService instance for chaining.
- `send(string|array $numberOrList, string $text, string $type = '')` — Send SMS to one or multiple numbers.
- `isLocal(string $number)` — Check if a number is local (Bangladeshi).
- `getDriver(string $driver = '')` — Get the current driver instance.
- `getFallbackDriver()` — Get the fallback driver instance.

---

## Laravel Notifications Integration

You can use the SMS channel in your Laravel notifications:

```php
use Illuminate\Notifications\Notification;

class InvoicePaid extends Notification
{
    public function via($notifiable)
    {
        return ['sms'];
    }

    public function toSms($notifiable)
    {
        return 'Your invoice has been paid!';
    }
}
```

- Register the channel in your `NotificationServiceProvider` if needed.
- Your notifiable model should have a `mobile` or `contact_no` property, or a `routeNotificationForSms` method.

---

## Custom Driver Usage

To use a custom driver:

```php
EnzSms::driver('custom_driver')->send('01xxxxxxxxx', 'Custom driver test');
```

---

## License

MIT

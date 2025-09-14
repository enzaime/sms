# SMS Package for Laravel

Easily integrate multiple SMS gateways into your Laravel application with a unified API.

---

## Supported Gateways

- [Twilio](https://www.twilio.com)
- [Onnorokom](https://onnorokomsms.com/)
- [AlphaBd](https://alpha.net.bd/SMS/)
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

> **Onnorokom Driver:**
> - Requires the **PHP SOAP extension**. Enable it in your PHP installation:
>
>   **Ubuntu/Debian:**
>   ```bash
>   sudo apt-get install php-soap
>   ```
>   **RedHat/CentOS:**
>   ```bash
>   sudo yum install php-soap
>   ```
>   **Mac (Homebrew):**
>   ```bash
>   brew install php
>   # (SOAP is included by default in most Homebrew PHP builds)
>   ```
>   **php.ini (if needed):**
>   ```ini
>   extension=soap
>   ```
>   After installation, restart your web server or PHP-FPM as needed.

---

## Configuration

Add the following to your `.env` file:

```env
SMS_DEFAULT_DRIVER=twilio|alpha_bd|onnorokom|log
SMS_USER=onnorokom-user
SMS_PASSWORD=onnorokom-pass

TWILIO_SID=your-twilio-sid
TWILIO_AUTH_TOKEN=your-twilio-auth-token
TWILIO_NUMBER=your-twilio-number
```

> **Twilio:** Recipient number must be in international format (e.g., `+8801xxxxxxxxx`).

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
> - If no driver is specified, Bangladeshi numbers use `onnorokom` or `alpha_bd`, foreign numbers use `twilio`.

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

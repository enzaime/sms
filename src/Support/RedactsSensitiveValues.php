<?php

namespace Enzaime\Sms\Support;

/**
 * Strip secrets out of text on its way to a log.
 *
 * These drivers pass the API key *and* the message body to the gateway — as
 * query parameters for the GET-based ones, as JSON for SSL Wireless — and an
 * HTTP client puts the whole request URL into its exception message. So the
 * most ordinary failure there is — a timeout, a DNS miss — hands a log line
 * the credential and, when the message is a one-time code, the code too.
 *
 * The same applies to a response body, since a gateway may echo the request
 * back; SSL Wireless returns the message it accepted in `sms_body`. Anything
 * derived from the wire is treated as tainted and passed through here first.
 */
trait RedactsSensitiveValues
{
    /**
     * Parameter names whose values must never be logged. Covers every
     * driver's spelling: `apikey` for Bulk SMS Dhaka, `api_key` for Alpha,
     * `api_token` for SSL Wireless, and `message`/`msg`/`sms`/`sms_body`/
     * `text` for the body that may carry a one-time code.
     */
    protected static $sensitiveParameters = [
        'apikey',
        'api_key',
        'api_token',
        'message',
        'msg',
        'sms',
        'sms_body',
        'text',
        'token',
        'secret',
        'secret_key',
        'password',
    ];

    /**
     * Replace the value of every sensitive parameter with a placeholder,
     * leaving the rest of the text — the part that says what went wrong —
     * intact and useful. Both wire shapes are covered: `name=value` in a URL
     * and `"name": "value"` in a JSON body.
     */
    protected function redact(string $text): string
    {
        $names = implode('|', array_map('preg_quote', static::$sensitiveParameters));

        $text = (string) preg_replace(
            '/\b('.$names.')=([^&\s"\'<>]*)/i',
            '$1=[redacted]',
            $text
        );

        return (string) preg_replace(
            '/("(?:'.$names.')"\s*:\s*)"(?:\\\\.|[^"\\\\])*"/i',
            '$1"[redacted]"',
            $text
        );
    }
}

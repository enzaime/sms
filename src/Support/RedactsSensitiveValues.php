<?php

namespace Enzaime\Sms\Support;

/**
 * Strip secrets out of text on its way to a log.
 *
 * These drivers pass the API key *and* the message body as query parameters,
 * and an HTTP client puts the whole request URL into its exception message. So
 * the most ordinary failure there is — a timeout, a DNS miss — hands a log
 * line the credential and, when the message is a one-time code, the code too.
 *
 * The same applies to a response body, since a gateway may echo the request
 * back. Anything derived from the wire is treated as tainted and passed
 * through here first.
 */
trait RedactsSensitiveValues
{
    /**
     * Parameter names whose values must never be logged. Covers both drivers'
     * spellings: `apikey` for Bulk SMS Dhaka, `api_key` for Alpha, `message`
     * and `msg` for the body that may carry a one-time code.
     */
    protected static $sensitiveParameters = [
        'apikey',
        'api_key',
        'message',
        'msg',
        'token',
        'secret',
        'password',
    ];

    /**
     * Replace the value of every sensitive query parameter with a placeholder,
     * leaving the rest of the text — the part that says what went wrong —
     * intact and useful.
     */
    protected function redact(string $text): string
    {
        $names = implode('|', array_map('preg_quote', static::$sensitiveParameters));

        return (string) preg_replace(
            '/\b('.$names.')=([^&\s"\'<>]*)/i',
            '$1=[redacted]',
            $text
        );
    }
}

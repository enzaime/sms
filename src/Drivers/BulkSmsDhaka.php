<?php

namespace Enzaime\Sms\Drivers;

use Enzaime\Sms\Contracts\SmsContract;
use Enzaime\Sms\Support\RedactsSensitiveValues;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bulk SMS Dhaka driver integration.
 *
 * @see https://bulksmsdhaka.net The admin dashboard.
 */
class BulkSmsDhaka implements SmsContract
{
    use RedactsSensitiveValues;

    /**
     * Codes the gateway uses for "we have it": accepted, and queued.
     */
    public const SUCCESS_CODES = [1000, 1001];

    /**
     * The gateway's documented codes, so a log line says what went wrong
     * instead of leaving the reader to go and look it up.
     */
    public const CODE_MEANINGS = [
        1002 => 'request pending',
        1003 => 'request failed',
        1005 => 'spam detected',
        1006 => 'SMS content validation failed',
        1008 => 'IP not whitelisted',
        1009 => 'account not verified',
        1010 => 'account disabled',
        1011 => 'sender ID not found for this API key',
        1012 => 'masking SMS must be sent in Bengali',
        1013 => 'balance validity not available',
        1014 => 'internal server error at the gateway',
        1015 => 'authorization failed',
        1016 => 'message id invalid, or already queried',
        1017 => 'message id not provided',
        1018 => 'API key not provided',
        2001 => 'balance insufficient',
    ];

    /**
     * Send SMS
     */
    public function send(string|array $numberOrList, string $text): int
    {
        $numbers = is_array($numberOrList) ? $numberOrList : [$numberOrList];

        $successCount = 0;

        foreach ($numbers as $number) {
            $successCount += $this->sendToOne($number, $text) ? 1 : 0;
        }

        return $successCount;
    }

    /**
     * Send SMS to a single recipient number.
     */
    protected function sendToOne(string $number, string $text): bool
    {
        try {
            $response = Http::get($this->getEndPoint(), [
                'apikey' => $this->getApiKey(),
                'callerID' => $this->getCallerId(),
                'number' => $number,
                'message' => $text,
            ]);
        } catch (Exception $ex) {
            $this->reportFailure($number, ['exception' => $ex->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            // The gateway explains itself in the body — "IP not whitelisted",
            // "insufficient balance" — and that explanation is the whole
            // difference between a five-minute fix and an afternoon. Callers
            // only ever see the count, so if it is not recorded here it is lost.
            $this->reportFailure($number, [
                'status' => $response->status(),
                'response' => Str::limit($response->body(), 500),
            ]);

            return false;
        }

        /*
         * A 200 is not an accepted message. The gateway reports the real
         * outcome as a code in the body and reserves the HTTP status for
         * transport, so "ip Not whitelisted" and "Balance Insufficient" both
         * arrive as perfectly successful responses. Trusting the status alone
         * counted those as delivered.
         */
        $code = $this->responseCode($response->json());

        if ($code !== null && ! in_array($code, self::SUCCESS_CODES, true)) {
            $this->reportFailure($number, [
                'status' => $response->status(),
                'code' => $code,
                'meaning' => self::CODE_MEANINGS[$code] ?? 'unknown code',
                'response' => Str::limit($response->body(), 500),
            ]);

            return false;
        }

        return true;
    }

    /**
     * The outcome code from a response body, when it carries one.
     *
     * @param  mixed  $body
     */
    protected function responseCode($body): ?int
    {
        if (! is_array($body) || ! isset($body['code']) || ! is_numeric($body['code'])) {
            return null;
        }

        return (int) $body['code'];
    }

    /**
     * Log a refused send.
     *
     * Never includes the message text or the API key: this driver carries
     * one-time codes and authenticates by query parameter, so both would
     * otherwise ride into the log inside an exception message or an echoed
     * response body. Everything taken off the wire is redacted first.
     *
     * @param  array<string, mixed>  $context
     */
    protected function reportFailure(string $number, array $context): void
    {
        foreach (['exception', 'response'] as $tainted) {
            if (isset($context[$tainted])) {
                $context[$tainted] = $this->redact((string) $context[$tainted]);
            }
        }

        Log::error('[SMS][BulkSmsDhaka] send failed', $context + ['number' => $number]);
    }

    /**
     * Return bulksmsdhaka config
     *
     * @return array
     */
    protected function config()
    {
        return config('sms.drivers.bulksmsdhaka');
    }

    protected function getEndPoint(): string
    {
        return $this->config()['api_url'];
    }

    protected function getApiKey(): string
    {
        return $this->config()['api_key'];
    }

    protected function getCallerId(): string
    {
        return $this->config()['caller_id'] ?? '';
    }
}

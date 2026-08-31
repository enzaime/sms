<?php

namespace Enzaime\Sms\Drivers;

use Enzaime\Sms\Contracts\OtpContract;
use Enzaime\Sms\Contracts\SmsContract;
use Enzaime\Sms\Support\RedactsSensitiveValues;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SSL Wireless (ISMS Plus) driver integration.
 *
 * Unlike the other Bangladeshi gateways here, this one takes JSON over POST
 * and answers with a per-recipient verdict: a request can be accepted at the
 * transport level, report `SUCCESS` at the top level, and still refuse
 * individual numbers inside `smsinfo`. The accepted count is therefore
 * counted from those entries, not from the HTTP status.
 *
 * One-time codes have their own hardened route — see {@see sendOtp()} — which
 * the plain send path deliberately does not use.
 *
 * @see https://ismsplus.sslwireless.com/api-documentation The API documentation.
 */
class SslWireless implements OtpContract, SmsContract
{
    use RedactsSensitiveValues;

    /**
     * The gateway refuses a bulk request outright (code 4030) above this many
     * recipients, so a longer list is split across several requests.
     */
    public const MAX_RECIPIENTS_PER_REQUEST = 100;

    /**
     * Length of the client-side message id. The gateway caps it at 20
     * alphanumeric characters and rejects a repeat (code 4023), so it is
     * generated fresh per request rather than derived from the message.
     */
    public const CSMS_ID_LENGTH = 20;

    /**
     * The only top-level code that means "we have the message".
     */
    public const SUCCESS_STATUS_CODE = 200;

    /**
     * The per-recipient verdict that means accepted; everything else in
     * `sms_status` (INVALID, DUPLICATE, BLOCKED) is a refusal.
     */
    public const ACCEPTED_SMS_STATUS = 'SUCCESS';

    /**
     * The secure OTP route encrypts the message body before it goes on the
     * wire. PKCS#7 is OpenSSL's default padding for this cipher.
     */
    public const OTP_CIPHER = 'aes-256-cbc';

    /**
     * The initialisation vector is a fixed 16 raw bytes, and rides in front of
     * the ciphertext rather than being agreed in advance.
     */
    public const OTP_IV_LENGTH = 16;

    /**
     * The parameters the OTP signature is computed over, in the order the
     * gateway canonicalises them (alphabetical). The API token is
     * deliberately absent: it authenticates the request, the signature
     * attests to the message.
     */
    public const SIGNED_PARAMETERS = ['csms_id', 'msisdn', 'sid', 'sms'];

    /**
     * The gateway's documented codes, so a log line says what went wrong
     * instead of leaving the reader to go and look it up.
     */
    public const CODE_MEANINGS = [
        4001 => 'unauthorized: authentication failed',
        4002 => 'sender ID not permitted to send SMS',
        4003 => 'IP not whitelisted',
        4004 => 'invalid request format',
        4005 => 'endpoint not found',
        4020 => 'invalid CSMS ID',
        4022 => 'required parameter missing',
        4023 => 'duplicate CSMS ID',
        4024 => 'duplicate MSISDN',
        4025 => 'invalid MSISDN',
        4026 => 'blocked MSISDN',
        4027 => 'message length exceeded',
        4028 => 'invalid message data',
        4029 => 'too many requests',
        4030 => 'recipient limit exceeded for one request',
        4031 => 'TPS limit exceeded',
        4032 => 'invalid SMS body',
        4033 => 'too many OTP requests to one recipient',
        4034 => 'unable to decrypt SMS',
        4035 => 'client signature mismatch',
        5000 => 'internal server error at the gateway',
    ];

    /**
     * Send SMS.
     *
     * Returns the number of recipients the gateway accepted, which is not
     * necessarily the number it was handed.
     */
    public function send(string|array $numberOrList, string $text): int
    {
        $numbers = $this->prepareRecipients(is_array($numberOrList) ? $numberOrList : [$numberOrList]);

        if ($numbers === []) {
            return 0;
        }

        if (! is_array($numberOrList)) {
            return $this->sendToOne($numbers[0], $text);
        }

        $accepted = 0;

        foreach (array_chunk($numbers, self::MAX_RECIPIENTS_PER_REQUEST) as $chunk) {
            $accepted += $this->sendToMany($chunk, $text);
        }

        return $accepted;
    }

    /**
     * Send a one-time code over the gateway's secure route.
     *
     * This is not the same wire as {@see send()}. The message body is
     * encrypted with a key derived from the account's Secret Key, and the
     * request carries an HMAC signature over the parameters that matter, so
     * neither the code nor its recipient can be read or altered in transit.
     * The gateway also rate-limits this route per recipient (code 4033),
     * which is the point of using it for codes and not for notices.
     *
     * The route takes one recipient at a time; a list becomes one request
     * each.
     */
    public function sendOtp(string|array $numberOrList, string $text): int
    {
        $numbers = $this->prepareRecipients(is_array($numberOrList) ? $numberOrList : [$numberOrList]);

        if ($numbers === []) {
            return 0;
        }

        if ($this->getSecretKey() === '') {
            // Without the Secret Key the body cannot be encrypted and the
            // request cannot be signed. Falling back to the plain route would
            // put the code on the wire in clear text, so refuse instead.
            $this->reportFailure($numbers, [
                'error_message' => 'no secret key configured for the secure OTP route',
            ]);

            return 0;
        }

        $accepted = 0;

        foreach ($numbers as $msisdn) {
            $accepted += $this->sendOtpToOne($msisdn, $text);
        }

        return $accepted;
    }

    /**
     * Encrypt, sign and post one one-time code.
     */
    protected function sendOtpToOne(string $msisdn, string $text): int
    {
        $encrypted = $this->encryptOtpText($text);

        if ($encrypted === null) {
            $this->reportFailure([$msisdn], ['error_message' => 'could not encrypt the OTP body']);

            return 0;
        }

        $payload = $this->credentials() + [
            'msisdn' => $msisdn,
            'sms' => $encrypted,
            'csms_id' => $this->generateCsmsId(),
        ];

        return $this->dispatch('secure/otp-sms', $payload, [$msisdn], [
            'X-Signature' => $this->signOtpPayload($payload),
        ]);
    }

    /**
     * Encrypt an OTP body for the secure route.
     *
     * The wire format is a base64 envelope wrapping the raw IV followed by
     * the *already base64-encoded* ciphertext — the double encoding is the
     * gateway's, not an accident. A fresh IV per message is what stops the
     * same code to the same number producing the same bytes twice.
     */
    protected function encryptOtpText(string $text): ?string
    {
        $iv = random_bytes(self::OTP_IV_LENGTH);

        $cipher = openssl_encrypt($text, self::OTP_CIPHER, $this->otpEncryptionKey(), 0, $iv);

        if ($cipher === false) {
            return null;
        }

        return base64_encode($iv.$cipher);
    }

    /**
     * The AES key, derived from the account's Secret Key.
     *
     * The documentation's prose asks for the first 32 characters of the
     * SHA-256 hex digest while its own sample passes the whole 64-character
     * digest; in PHP those agree, because OpenSSL truncates an over-long key
     * to the cipher's 32 bytes. The explicit form is used so the derivation
     * does not depend on that truncation.
     */
    protected function otpEncryptionKey(): string
    {
        return substr(hash('sha256', $this->getSecretKey()), 0, 32);
    }

    /**
     * The `X-Signature` for an OTP request: a lowercase HMAC-SHA256 hex
     * digest over the canonical query string, keyed with the raw Secret Key.
     *
     * The key here is the Secret Key itself, *not* the SHA-256 derivation
     * used for the cipher — the two are different keys from the same secret.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function signOtpPayload(array $payload): string
    {
        $signed = array_intersect_key($payload, array_flip(self::SIGNED_PARAMETERS));

        ksort($signed);

        $canonical = [];

        foreach ($signed as $name => $value) {
            // RFC 3986 encoding, which is what the gateway's own samples
            // produce. The signed values are digits, alphanumerics and
            // base64, so the characters where PHP and JavaScript disagree
            // cannot occur here.
            $canonical[] = $name.'='.rawurlencode((string) $value);
        }

        return hash_hmac('sha256', implode('&', $canonical), $this->getSecretKey());
    }

    /**
     * Send one message to a single recipient.
     */
    protected function sendToOne(string $msisdn, string $text): int
    {
        return $this->dispatch('send-sms', $this->credentials() + [
            'msisdn' => $msisdn,
            'sms' => $text,
            'csms_id' => $this->generateCsmsId(),
        ], [$msisdn]);
    }

    /**
     * Send one message to a batch of recipients.
     *
     * @param  array<int, string>  $msisdns
     */
    protected function sendToMany(array $msisdns, string $text): int
    {
        return $this->dispatch('send-sms/bulk', $this->credentials() + [
            'msisdn' => array_values($msisdns),
            'sms' => $text,
            'batch_csms_id' => $this->generateCsmsId(),
        ], $msisdns);
    }

    /**
     * Post one request and account for what came back.
     *
     * The payload arrives complete: the OTP route signs the exact bytes it
     * posts, so nothing may be added to a request after it has been signed.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $recipients
     * @param  array<string, string>  $headers
     */
    protected function dispatch(string $path, array $payload, array $recipients, array $headers = []): int
    {
        try {
            $response = Http::withHeaders($headers)
                ->acceptJson()
                ->asJson()
                ->post($this->getEndPoint($path), $payload);
        } catch (Exception $ex) {
            $this->reportFailure($recipients, ['exception' => $ex->getMessage()]);

            return 0;
        }

        if (! $response->successful()) {
            $this->reportFailure($recipients, [
                'status' => $response->status(),
                'response' => Str::limit($response->body(), 500),
            ]);

            return 0;
        }

        return $this->countAccepted($response->json(), $recipients, $response->status());
    }

    /**
     * Count the recipients the gateway actually took.
     *
     * A 200 is not an accepted message: the gateway reserves the HTTP status
     * for transport and reports the real outcome in the body, per recipient.
     * "Invalid MSISDN" and "Blocked MSISDN" both arrive inside a perfectly
     * successful response, so trusting the status alone counts refusals as
     * deliveries.
     *
     * @param  mixed  $body
     * @param  array<int, string>  $recipients
     */
    protected function countAccepted($body, array $recipients, int $httpStatus): int
    {
        if (! is_array($body)) {
            // Nothing readable came back; the transport verdict is all there is.
            return count($recipients);
        }

        $info = $body['smsinfo'] ?? null;

        if (is_array($info) && $info !== []) {
            return $this->countAcceptedEntries($info, $body, $httpStatus);
        }

        if ($this->isAccepted($body)) {
            return count($recipients);
        }

        $this->reportFailure($recipients, $this->outcomeContext($body) + ['status' => $httpStatus]);

        return 0;
    }

    /**
     * Count the accepted entries in `smsinfo` and log every refused one.
     *
     * @param  array<int, mixed>  $info
     * @param  array<string, mixed>  $body
     */
    protected function countAcceptedEntries(array $info, array $body, int $httpStatus): int
    {
        $accepted = 0;
        $refused = [];

        foreach ($info as $entry) {
            $status = is_array($entry) ? (string) ($entry['sms_status'] ?? '') : '';

            if (strtoupper($status) === self::ACCEPTED_SMS_STATUS) {
                $accepted++;

                continue;
            }

            $refused[] = [
                'number' => is_array($entry) ? (string) ($entry['msisdn'] ?? '') : '',
                'sms_status' => $status ?: 'unknown',
                'status_message' => $this->redact(is_array($entry) ? (string) ($entry['status_message'] ?? '') : ''),
            ];
        }

        foreach ($refused as $refusal) {
            $this->reportFailure(
                [$refusal['number']],
                $this->outcomeContext($body) + [
                    'status' => $httpStatus,
                    'sms_status' => $refusal['sms_status'],
                    'status_message' => $refusal['status_message'],
                ]
            );
        }

        return $accepted;
    }

    /**
     * Whether a body without per-recipient detail says the request was taken.
     *
     * A body that carries neither key is not readable as a refusal, so the
     * HTTP status stays the verdict.
     *
     * @param  array<string, mixed>  $body
     */
    protected function isAccepted(array $body): bool
    {
        if (isset($body['status_code']) && is_numeric($body['status_code'])) {
            return (int) $body['status_code'] === self::SUCCESS_STATUS_CODE;
        }

        if (isset($body['status'])) {
            return strtoupper((string) $body['status']) === self::ACCEPTED_SMS_STATUS;
        }

        return true;
    }

    /**
     * The top-level verdict, spelled out for a log line.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function outcomeContext(array $body): array
    {
        $context = [];

        if (isset($body['status_code']) && is_numeric($body['status_code'])) {
            $code = (int) $body['status_code'];
            $context['code'] = $code;
            $context['meaning'] = self::CODE_MEANINGS[$code] ?? 'unknown code';
        }

        if (! empty($body['error_message'])) {
            $context['error_message'] = $this->redact((string) $body['error_message']);
        }

        return $context;
    }

    /**
     * Normalise, drop empties and de-duplicate a recipient list.
     *
     * A repeated number inside one request is refused as a duplicate — for a
     * single send with code 4024, for a batch by failing the message data —
     * so the repeat is dropped here rather than spent on a refusal.
     *
     * @param  array<int, string>  $numbers
     * @return array<int, string>
     */
    protected function prepareRecipients(array $numbers): array
    {
        $normalized = array_map(fn (string $number): string => $this->normalizeMsisdn($number), $numbers);

        return array_values(array_unique(array_filter($normalized, fn (string $number): bool => $number !== '')));
    }

    /**
     * Put a number into the MSISDN form the gateway expects: digits only,
     * carrying the country code and no leading `+` or `00`.
     */
    protected function normalizeMsisdn(string $number): string
    {
        $digits = (string) preg_replace('/\D+/', '', $number);

        if ($digits === '' || str_starts_with($digits, '880')) {
            return $digits;
        }

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return '88'.$digits;
        }

        return $digits;
    }

    /**
     * A fresh client-side message id. The gateway rejects a repeat, so this
     * must vary per request even when the same text goes to the same number.
     */
    protected function generateCsmsId(): string
    {
        return Str::random(self::CSMS_ID_LENGTH);
    }

    /**
     * Log a refused send.
     *
     * Never includes the message text or the API token: this driver carries
     * one-time codes, and the gateway echoes both the request and the message
     * body back in its own response. Everything taken off the wire is
     * redacted first.
     *
     * @param  array<int, string>  $numbers
     * @param  array<string, mixed>  $context
     */
    protected function reportFailure(array $numbers, array $context): void
    {
        foreach (['exception', 'response'] as $tainted) {
            if (isset($context[$tainted])) {
                $context[$tainted] = $this->redact((string) $context[$tainted]);
            }
        }

        Log::error('[SMS][SslWireless] send failed', $context + ['number' => implode(',', $numbers)]);
    }

    /**
     * Return sslwireless config
     *
     * @return array
     */
    protected function config()
    {
        return config('sms.drivers.sslwireless');
    }

    protected function getEndPoint(string $path): string
    {
        return rtrim($this->config()['api_url'], '/').'/'.ltrim($path, '/');
    }

    protected function getApiToken(): string
    {
        return $this->config()['api_token'] ?? '';
    }

    protected function getSenderId(): string
    {
        return $this->config()['sid'] ?? '';
    }

    protected function getSecretKey(): string
    {
        return (string) ($this->config()['secret_key'] ?? '');
    }

    /**
     * The credentials every request carries.
     *
     * @return array<string, string>
     */
    protected function credentials(): array
    {
        return [
            'api_token' => $this->getApiToken(),
            'sid' => $this->getSenderId(),
        ];
    }
}

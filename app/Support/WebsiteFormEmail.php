<?php

namespace App\Support;

final class WebsiteFormEmail
{
    private const SUBJECT = 'nouveau message depuis le site';

    private const BODY_PREFIX = 'nouveau message reçu depuis le site.';

    /**
     * Identifies the exact notification template emitted by a Maracuja site
     * before it was connected directly to Cremona. A reply or forwarded email
     * deliberately does not match because its subject is no longer exact.
     */
    public static function isNotification(string $subject, string $body): bool
    {
        return mb_strtolower(trim($subject)) === self::SUBJECT
            && str_starts_with(mb_strtolower(trim($body)), self::BODY_PREFIX);
    }

    /**
     * Decodes the exact legacy notification template emitted by Maracuja sites.
     * Replies and manually composed emails are intentionally excluded.
     *
     * @return array{name: ?string, email: string, phone: ?string, subject: ?string, message: string}|null
     */
    public static function parse(string $subject, string $body): ?array
    {
        if (! self::isNotification($subject, $body)) {
            return null;
        }

        $labels = 'nom|email|téléphone|sujet|message';
        $email = self::field($body, 'email', $labels);
        $message = self::field($body, 'message', $labels, true);

        if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $message === null) {
            return null;
        }

        return [
            'name' => self::field($body, 'nom', $labels),
            'email' => $email,
            'phone' => self::field($body, 'téléphone', $labels),
            'subject' => self::field($body, 'sujet', $labels),
            'message' => $message,
        ];
    }

    private static function field(string $body, string $label, string $labels, bool $restOfMessage = false): ?string
    {
        $ending = $restOfMessage ? '$' : "(?=\\s+(?:{$labels})\\s*:|$)";
        $pattern = "/(?:^|\\s){$label}\\s*:\\s*(.+?){$ending}/isu";

        if (preg_match($pattern, $body, $matches) !== 1) {
            return null;
        }

        return self::clean($matches[1]);
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}

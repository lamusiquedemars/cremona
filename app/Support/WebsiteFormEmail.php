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
     * @return array{name: string, email: string, phone: ?string, subject: ?string, message: string}|null
     */
    public static function parse(string $subject, string $body): ?array
    {
        if (! self::isNotification($subject, $body)) {
            return null;
        }

        $pattern = '/^nouveau message reçu depuis le site\.\s+'
            .'nom\s*:\s*(?<name>[^\r\n]+)\s+'
            .'email\s*:\s*(?<email>[^\r\n]+)'
            .'(?:\s+téléphone\s*:\s*(?<phone>[^\r\n]+))?'
            .'(?:\s+sujet\s*:\s*(?<subject>[^\r\n]+))?'
            .'\s+message\s*:\s*(?<message>.+)$/isu';

        if (preg_match($pattern, trim($body), $matches) !== 1) {
            return null;
        }

        $message = trim($matches['message']);
        if ($message === '') {
            return null;
        }

        return [
            'name' => trim($matches['name']),
            'email' => trim($matches['email']),
            'phone' => self::clean($matches['phone'] ?? null),
            'subject' => self::clean($matches['subject'] ?? null),
            'message' => $message,
        ];
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}

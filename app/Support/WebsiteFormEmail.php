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
}

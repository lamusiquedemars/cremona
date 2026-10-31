<?php

namespace App\Support;

final class EmailBodyText
{
    /**
     * Produces a readable, plain-text representation of an email body.
     * Email archives remain untouched; this is used at import and display time.
     */
    public static function forDisplay(?string $body): string
    {
        $body = quoted_printable_decode(trim((string) $body));

        if ($body !== '' && ! mb_check_encoding($body, 'UTF-8')) {
            $body = mb_convert_encoding($body, 'UTF-8', 'Windows-1252');
        }

        // Some senders put HTML in the text MIME part. Preserve meaningful
        // line breaks before removing markup, then decode HTML entities.
        $body = preg_replace('/<(?:br|\\/p|\\/div|\\/li|\\/h[1-6])\\b[^>]*>/iu', "\n", $body) ?? $body;
        $body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = preg_replace("/[^\\S\n]+/u", ' ', $body) ?? $body;
        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;

        return trim($body) !== '' ? trim($body) : '(Message sans contenu texte)';
    }
}

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

        $body = self::withoutCssBlocks($body);

        // Some senders put HTML in the text MIME part, sometimes escaped as
        // &lt;p&gt;…&lt;/p&gt;. Decode entities first, then preserve meaningful line
        // breaks before removing the resulting markup.
        for ($pass = 0; $pass < 2; $pass++) {
            $decoded = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $body) {
                break;
            }

            $body = $decoded;
        }
        $body = preg_replace('/<(?:br|\\/p|\\/div|\\/li|\\/h[1-6])\\b[^>]*>/iu', "\n", $body) ?? $body;
        $body = strip_tags($body);
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = preg_replace("/[^\\S\n]+/u", ' ', $body) ?? $body;
        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;

        return trim($body) !== '' ? trim($body) : '(Message sans contenu texte)';
    }

    private static function withoutCssBlocks(string $body): string
    {
        $cssDepth = 0;
        $lines = [];

        foreach (preg_split('/\R/u', $body) ?: [] as $line) {
            $trimmed = trim($line);
            if ($cssDepth > 0) {
                $cssDepth += substr_count($line, '{') - substr_count($line, '}');

                continue;
            }

            // This deliberately targets CSS selectors and at-rules, not prose
            // containing a brace. It also handles malformed email text parts
            // where a prior importer has already stripped the <style> tags.
            $startsCss = preg_match('/^(?:@(?:media|font-face|keyframes)\b|(?:body|html|table|td|a|[.#][\w-]+)[\w\s,>+~:#.\[\]="\'\-]*\{)/iu', $trimmed) === 1;
            if ($startsCss) {
                $cssDepth = substr_count($line, '{') - substr_count($line, '}');

                continue;
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}

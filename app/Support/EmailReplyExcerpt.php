<?php

namespace App\Support;

class EmailReplyExcerpt
{
    public static function from(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));

        $parts = preg_split(
            '/(?:^|\n|\s)(?:le\s+.+?\s+a écrit\s*:|on\s+.+?\s+wrote\s*:|em\s+.+?\s+escreveu\s*:|[- ]*original message[- ]*:)/ui',
            $body,
            2,
        );
        $excerpt = trim($parts[0] ?? $body);
        $excerpt = trim((string) preg_split('/\n\s*>/u', $excerpt, 2)[0]);

        return $excerpt !== '' ? $excerpt : $body;
    }
}

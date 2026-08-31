<?php

namespace App\Support;

class EmailReplyExcerpt
{
    /** @return array{reply: string, quoted: ?string} */
    public static function split(string $body): array
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));

        $patterns = [
            '/(?:^|\n|\s)(?:le\s+.+?\s+a écrit\s*:|on\s+.+?\s+wrote\s*:|em\s+.+?\s+escreveu\s*:|[- ]*original message[- ]*:)/ui',
            '/\n\s*>/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $body, $match, PREG_OFFSET_CAPTURE) === 1) {
                $offset = $match[0][1];
                $reply = trim(substr($body, 0, $offset));
                $quoted = trim(substr($body, $offset));

                if ($reply !== '' && $quoted !== '') {
                    return ['reply' => $reply, 'quoted' => $quoted];
                }
            }
        }

        return ['reply' => $body, 'quoted' => null];
    }

    public static function quotedForDisplay(string $quoted): string
    {
        $quoted = preg_replace('/\h+>\h+>\h*/u', "\n>\n> ", $quoted) ?? $quoted;

        return preg_replace('/\h+>\h?/u', "\n> ", $quoted) ?? $quoted;
    }
}

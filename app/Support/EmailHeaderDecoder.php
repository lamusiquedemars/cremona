<?php

namespace App\Support;

final class EmailHeaderDecoder
{
    public static function text(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! str_contains($value, '=?')) {
            return $value;
        }

        $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded === false || trim($decoded) === '' ? $value : trim($decoded);
    }
}

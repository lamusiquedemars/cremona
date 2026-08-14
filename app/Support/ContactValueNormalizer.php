<?php

namespace App\Support;

use App\Enums\ContactMethodType;

class ContactValueNormalizer
{
    public function normalize(ContactMethodType $type, string $value): string
    {
        $value = trim($value);

        return match ($type) {
            ContactMethodType::Email => mb_strtolower($value),
            ContactMethodType::Phone => preg_replace('/[^\d+]/', '', $value) ?? '',
        };
    }
}

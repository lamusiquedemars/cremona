<?php

namespace App\Services;

use App\Enums\ContactMethodType;
use App\Models\Company;
use App\Models\ContactMethod;
use App\Models\Person;
use App\Support\ContactValueNormalizer;

class ClientContactDetailsManager
{
    /** @return array{primary_email: ?string, primary_phone: ?string} */
    public function primaryValues(Person|Company $client): array
    {
        return [
            'primary_email' => $this->primaryMethod($client, ContactMethodType::Email)?->value,
            'primary_phone' => $this->primaryMethod($client, ContactMethodType::Phone)?->value,
        ];
    }

    public function sync(Person|Company $client, ?string $email, ?string $phone): void
    {
        $this->syncMethod($client, ContactMethodType::Email, $email);
        $this->syncMethod($client, ContactMethodType::Phone, $phone);

        $client->unsetRelation('contactMethods');
    }

    private function syncMethod(Person|Company $client, ContactMethodType $type, ?string $value): void
    {
        $value = filled($value) ? trim($value) : null;
        $current = $this->primaryMethod($client, $type);

        if ($value === null) {
            $current?->delete();

            $replacement = $client->contactMethods()
                ->where('type', $type->value)
                ->orderBy('id')
                ->first();

            $replacement?->update(['is_primary' => true]);

            return;
        }

        $normalizedValue = app(ContactValueNormalizer::class)->normalize($type, $value);
        $matching = $client->contactMethods()
            ->where('type', $type->value)
            ->where('normalized_value', $normalizedValue)
            ->first();

        $method = $matching ?? $current;

        if ($method === null) {
            $method = $client->contactMethods()->create([
                'type' => $type->value,
                'value' => $value,
                'is_primary' => true,
            ]);
        } else {
            $method->update([
                'value' => $value,
                'is_primary' => true,
            ]);
        }

        $client->contactMethods()
            ->where('type', $type->value)
            ->whereKeyNot($method->getKey())
            ->update(['is_primary' => false]);
    }

    private function primaryMethod(Person|Company $client, ContactMethodType $type): ?ContactMethod
    {
        return $client->contactMethods()
            ->where('type', $type->value)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }
}

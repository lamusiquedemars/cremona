<?php

namespace App\Services;

use App\Enums\ContactMethodType;
use App\Models\Person;
use App\Support\ContactValueNormalizer;

class WebsiteContactResolver
{
    public function __construct(
        private readonly ContactMatcher $matcher,
        private readonly ContactValueNormalizer $normalizer,
    ) {}

    /**
     * Resolves a contact submitted through a structured website form.
     *
     * @return array{person: Person, created: bool}|null
     */
    public function resolve(?string $name, ?string $email, ?string $phone, ?string $source = null): ?array
    {
        $name = $this->clean($name);
        $email = $this->clean($email);
        $phone = $this->clean($phone);

        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = null;
        }

        if ($phone !== null && ! preg_match('/\\d/', $this->normalizer->normalize(ContactMethodType::Phone, $phone))) {
            $phone = null;
        }

        // A name alone is not a reliable enough key to create or attach a client.
        if ($email === null && $phone === null) {
            return null;
        }

        $matches = $this->matcher->suggestPeople($email, $phone);
        if ($matches->count() > 1) {
            // An email and a phone pointing to different people must remain a
            // deliberate human decision; never merge contacts silently.
            return null;
        }

        $person = $matches->first();
        $created = $person === null;

        if ($person === null) {
            $parts = preg_split('/\\s+/u', $name ?? '', 2) ?: [];
            $person = Person::query()->create([
                'display_name' => $name ?? $email ?? $phone,
                'first_name' => $parts[0] ?? null,
                'last_name' => $parts[1] ?? null,
                'source' => str($source ?? 'website')->limit(40, '')->toString(),
            ]);
        }

        $this->addContactMethod($person, ContactMethodType::Email, $email);
        $this->addContactMethod($person, ContactMethodType::Phone, $phone);

        return compact('person', 'created');
    }

    private function addContactMethod(Person $person, ContactMethodType $type, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $normalized = $this->normalizer->normalize($type, $value);
        if ($normalized === '' || $person->contactMethods()
            ->where('type', $type->value)
            ->where('normalized_value', $normalized)
            ->exists()) {
            return;
        }

        $person->contactMethods()->create([
            'type' => $type,
            'label' => 'Formulaire du site',
            'value' => $value,
            'is_primary' => ! $person->contactMethods()->where('type', $type->value)->exists(),
        ]);
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}

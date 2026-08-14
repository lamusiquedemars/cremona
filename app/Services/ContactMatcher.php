<?php

namespace App\Services;

use App\Enums\ContactMethodType;
use App\Models\Person;
use App\Support\ContactValueNormalizer;
use Illuminate\Support\Collection;

class ContactMatcher
{
    public function __construct(private readonly ContactValueNormalizer $normalizer) {}

    /**
     * @return Collection<int, Person>
     */
    public function suggestPeople(?string $email, ?string $phone): Collection
    {
        $values = collect([
            ContactMethodType::Email->value => $this->normalize(ContactMethodType::Email, $email),
            ContactMethodType::Phone->value => $this->normalize(ContactMethodType::Phone, $phone),
        ])->filter(fn (?string $value): bool => $value !== null);

        if ($values->isEmpty()) {
            return collect();
        }

        return Person::query()
            ->where(function ($query) use ($values): void {
                foreach ($values as $type => $value) {
                    $query->orWhereHas('contactMethods', fn ($contactMethods) => $contactMethods
                        ->where('type', $type)
                        ->where('normalized_value', $value));
                }
            })
            ->orderBy('display_name')
            ->get();
    }

    private function normalize(ContactMethodType $type, ?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = $this->normalizer->normalize($type, $value);

        return $normalized !== '' ? $normalized : null;
    }
}

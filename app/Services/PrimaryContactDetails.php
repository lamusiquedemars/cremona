<?php

namespace App\Services;

use App\Enums\ContactMethodType;
use Illuminate\Database\Eloquent\Model;

final class PrimaryContactDetails
{
    /** @return array{email: ?string, phone: ?string} */
    public function values(Model $record): array
    {
        return [
            'email' => $this->value($record, ContactMethodType::Email),
            'phone' => $this->value($record, ContactMethodType::Phone),
        ];
    }

    /** @param array{email?: ?string, phone?: ?string} $details */
    public function sync(Model $record, array $details): void
    {
        foreach ([ContactMethodType::Email->value => 'email', ContactMethodType::Phone->value => 'phone'] as $type => $key) {
            $type = ContactMethodType::from($type);
            $value = trim((string) ($details[$key] ?? ''));
            $method = $record->contactMethods()->where('type', $type->value)->orderByDesc('is_primary')->first();

            if ($value === '') {
                $method?->delete();

                continue;
            }

            if ($method) {
                $method->update(['value' => $value]);

                continue;
            }

            $record->contactMethods()->create([
                'type' => $type,
                'value' => $value,
                'is_primary' => ! $record->contactMethods()->where('type', $type->value)->exists(),
            ]);
        }
    }

    private function value(Model $record, ContactMethodType $type): ?string
    {
        return $record->contactMethods()
            ->where('type', $type->value)
            ->orderByDesc('is_primary')
            ->value('value');
    }
}

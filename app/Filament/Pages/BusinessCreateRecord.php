<?php

namespace App\Filament\Pages;

use App\Enums\ContactMethodType;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

abstract class BusinessCreateRecord extends CreateRecord
{
    /** @var array{primary_email?: ?string, primary_phone?: ?string}|null */
    private ?array $contactDetails = null;

    public function getTitle(): string|Htmlable
    {
        return 'Nouveau '.static::getResource()::getModelLabel();
    }

    public function getBreadcrumb(): string
    {
        return 'Nouveau';
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->contactDetails = $this->takeContactDetails($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->saveContactDetails();
    }

    /** @param array<string, mixed> $data */
    private function takeContactDetails(array &$data): ?array
    {
        if (! array_key_exists('primary_email', $data) && ! array_key_exists('primary_phone', $data)) {
            return null;
        }

        $details = [
            'primary_email' => $data['primary_email'] ?? null,
            'primary_phone' => $data['primary_phone'] ?? null,
        ];

        unset($data['primary_email'], $data['primary_phone']);

        return $details;
    }

    private function saveContactDetails(): void
    {
        if ($this->contactDetails === null || ! method_exists($this->record, 'contactMethods')) {
            return;
        }

        foreach ([ContactMethodType::Email->value => 'primary_email', ContactMethodType::Phone->value => 'primary_phone'] as $type => $field) {
            $value = trim((string) ($this->contactDetails[$field] ?? ''));
            $method = $this->record->contactMethods()->where('type', $type)->orderByDesc('is_primary')->first();

            if ($value === '') {
                $method?->delete();

                continue;
            }

            $method
                ? $method->update(['value' => $value])
                : $this->record->contactMethods()->create(['type' => $type, 'value' => $value, 'is_primary' => true]);
        }
    }
}

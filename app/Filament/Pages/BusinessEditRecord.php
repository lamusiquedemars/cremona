<?php

namespace App\Filament\Pages;

use App\Enums\ContactMethodType;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

abstract class BusinessEditRecord extends EditRecord
{
    /** @var array{primary_email?: ?string, primary_phone?: ?string}|null */
    private ?array $contactDetails = null;

    public function getTitle(): string|Htmlable
    {
        return 'Modifier · '.$this->getRecordTitle();
    }

    public function getBreadcrumb(): string
    {
        return $this->getRecordTitle();
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! array_key_exists('primary_email', $data) && ! array_key_exists('primary_phone', $data)) {
            return $data;
        }

        $this->contactDetails = [
            'primary_email' => $data['primary_email'] ?? null,
            'primary_phone' => $data['primary_phone'] ?? null,
        ];

        unset($data['primary_email'], $data['primary_phone']);

        return $data;
    }

    protected function afterSave(): void
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

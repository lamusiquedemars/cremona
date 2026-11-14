<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\Person;
use App\Services\ClientContactDetailsManager;

abstract class BusinessCreateClientRecord extends BusinessCreateRecord
{
    /** @var array{primary_email: ?string, primary_phone: ?string} */
    private array $contactDetails = ['primary_email' => null, 'primary_phone' => null];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->contactDetails = $this->extractContactDetails($data);

        unset($data['primary_email'], $data['primary_phone']);

        return parent::mutateFormDataBeforeCreate($data);
    }

    protected function afterCreate(): void
    {
        if ($this->record instanceof Person || $this->record instanceof Company) {
            app(ClientContactDetailsManager::class)->sync(
                $this->record,
                $this->contactDetails['primary_email'],
                $this->contactDetails['primary_phone'],
            );
        }
    }

    /** @param array<string, mixed> $data
     * @return array{primary_email: ?string, primary_phone: ?string}
     */
    private function extractContactDetails(array $data): array
    {
        return [
            'primary_email' => filled($data['primary_email'] ?? null) ? trim((string) $data['primary_email']) : null,
            'primary_phone' => filled($data['primary_phone'] ?? null) ? trim((string) $data['primary_phone']) : null,
        ];
    }
}

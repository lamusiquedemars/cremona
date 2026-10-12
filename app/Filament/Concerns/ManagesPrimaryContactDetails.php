<?php

namespace App\Filament\Concerns;

use App\Services\PrimaryContactDetails;

trait ManagesPrimaryContactDetails
{
    /** @var array{email?: ?string, phone?: ?string} */
    protected array $primaryContactDetails = [];

    /** @param array<string, mixed> $data */
    protected function extractPrimaryContactDetails(array $data): array
    {
        $this->primaryContactDetails = [
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ];

        unset($data['email'], $data['phone']);

        return $data;
    }

    protected function syncPrimaryContactDetails(): void
    {
        app(PrimaryContactDetails::class)->sync($this->record, $this->primaryContactDetails);
    }
}

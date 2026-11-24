<?php
namespace App\Filament\Resources\Availabilities\Pages;
use App\Filament\Pages\BusinessCreateRecord;
use App\Filament\Resources\Availabilities\AvailabilityResource;
use App\Models\ClientSearchMatch;

class CreateAvailability extends BusinessCreateRecord
{
    protected static string $resource = AvailabilityResource::class;

    /** @var array<int, int|string> */
    private array $manualPersonIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->manualPersonIds = $data['manual_person_ids'] ?? [];
        unset($data['manual_person_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach (array_unique($this->manualPersonIds) as $personId) {
            ClientSearchMatch::query()->firstOrCreate([
                'availability_id' => $this->record->id,
                'person_id' => $personId,
            ], [
                'organization_id' => $this->record->organization_id,
                'match_kind' => 'manual',
                'status' => 'suggested',
            ]);
        }
    }
}

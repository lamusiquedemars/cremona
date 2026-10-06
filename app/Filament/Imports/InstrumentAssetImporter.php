<?php

namespace App\Filament\Imports;

use App\Enums\InstrumentAssetStatus;
use App\Models\InstrumentAsset;
use App\Models\Organization;
use App\Models\RentalTier;
use App\Services\InstrumentRentalPricing;
use App\Tenancy\OrganizationContext;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class InstrumentAssetImporter extends Importer
{
    protected static ?string $model = InstrumentAsset::class;
    protected static bool $shouldPreventFormulaInjection = true;

    public function __invoke(array $data): void
    {
        $organization = Organization::query()->findOrFail($this->options['organization_id']);
        app(OrganizationContext::class)->run($organization, fn () => parent::__invoke($data));
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('reference')->label('Référence')->requiredMapping()->rules(['required', 'max:80']),
            ImportColumn::make('name')->label('Intitulé')->requiredMapping()->rules(['required', 'max:255']),
            ImportColumn::make('family')->label('Famille')->rules(['nullable', 'in:violon,alto,violoncelle,contrebasse,archet,autre']),
            ImportColumn::make('rental_size')->label('Taille'),
            ImportColumn::make('rental_tier')->label('Gamme de location'),
            ImportColumn::make('maker')->label('Luthier / fabricant'),
            ImportColumn::make('year')->label('Année'),
            ImportColumn::make('available_for_rental')->label('Proposer à la location')->boolean(),
            ImportColumn::make('available_for_sale')->label('Proposer à la vente')->boolean(),
        ];
    }

    public function resolveRecord(): ?Model
    {
        return InstrumentAsset::query()->firstOrNew(['reference' => $this->data['reference']]);
    }

    public function fillRecord(): void
    {
        $this->record->fill([
            'reference' => $this->data['reference'], 'name' => $this->data['name'], 'family' => $this->data['family'] ?: null,
            'rental_size' => $this->data['rental_size'] ?: null, 'maker' => $this->data['maker'] ?: null,
            'year' => $this->data['year'] ?: null, 'available_for_rental' => (bool) ($this->data['available_for_rental'] ?? false),
            'available_for_sale' => (bool) ($this->data['available_for_sale'] ?? false), 'ownership' => 'owned', 'status' => InstrumentAssetStatus::Available,
        ]);
        if (filled($this->data['rental_tier'] ?? null)) {
            $tier = RentalTier::query()->where('name', $this->data['rental_tier'])->where('is_active', true)->first();
            if (! $tier) throw ValidationException::withMessages(['rental_tier' => 'Gamme introuvable ou inactive.']);
            $this->record->rental_tier_id = $tier->id;
        }
        app(InstrumentRentalPricing::class)->applyToInstrument($this->record, requireProfile: $this->record->available_for_rental);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return "{$import->successful_rows} instrument(s) importé(s). {$import->getFailedRowsCount()} ligne(s) à corriger.";
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\InstrumentAssetStatus;
use App\Enums\RentalStatus;
use App\Enums\WorkshopOrderStatus;
use App\Models\InstrumentAsset;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Rental;
use App\Models\ServiceDefinition;
use App\Models\StockItem;
use App\Models\WorkshopOrder;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedContempoDemoScenario extends Command
{
    private const MARKER = 'DEMO-CONTEMPO';

    protected $signature = 'cremona:seed-contempo-demo {organization? : Slug de l’organisation Contempo}';

    protected $description = 'Crée un parcours de démonstration Contempo, entièrement identifiable et supprimable.';

    public function handle(OrganizationContext $context): int
    {
        $organization = $this->organization();

        if ($organization === null) {
            return self::FAILURE;
        }

        $context->run($organization, function (): void {
            DB::transaction(function (): void {
                $person = Person::query()->firstOrCreate(
                    ['source' => self::MARKER, 'display_name' => 'Camille Martin — démonstration'],
                    ['first_name' => 'Camille', 'last_name' => 'Martin', 'locale' => 'fr', 'country_code' => 'FR'],
                );

                $services = collect([
                    ['code' => self::MARKER.'-REMECHAGE', 'name' => 'Reméchage complet — démonstration', 'description' => 'Remplacement de la mèche et réglage de l’archet.', 'suggested_unit_amount' => 85],
                    ['code' => self::MARKER.'-AME', 'name' => 'Remplacement d’âme — démonstration', 'description' => 'Ajustement et pose d’une nouvelle âme.', 'suggested_unit_amount' => 45],
                    ['code' => self::MARKER.'-CHEVALET', 'name' => 'Ajustement de chevalet — démonstration', 'description' => 'Ajustement du chevalet au montage.', 'suggested_unit_amount' => 60],
                ])->mapWithKeys(function (array $service): array {
                    $record = ServiceDefinition::query()->firstOrCreate(['code' => $service['code']], [...$service, 'tax_rate' => 20, 'is_active' => true]);

                    return [$service['code'] => $record];
                });

                $stock = collect([
                    ['sku' => self::MARKER.'-CORDES', 'name' => 'Jeu de cordes violon — démonstration', 'quantity_on_hand' => 5, 'reorder_level' => 2, 'suggested_unit_amount' => 28],
                    ['sku' => self::MARKER.'-AME', 'name' => 'Âme de violon — démonstration', 'quantity_on_hand' => 8, 'reorder_level' => 3, 'suggested_unit_amount' => 8],
                    ['sku' => self::MARKER.'-COLOPHANE', 'name' => 'Colophane — démonstration', 'quantity_on_hand' => 6, 'reorder_level' => 2, 'suggested_unit_amount' => 12],
                    ['sku' => self::MARKER.'-MENTONNIERE', 'name' => 'Mentonnière — démonstration', 'quantity_on_hand' => 3, 'reorder_level' => 1, 'suggested_unit_amount' => 35],
                ])->mapWithKeys(function (array $item): array {
                    $record = StockItem::query()->firstOrCreate(['sku' => $item['sku']], [...$item, 'description' => 'Jeu de données supprimable '.self::MARKER, 'is_active' => true]);

                    return [$item['sku'] => $record];
                });

                $workshopInstrument = InstrumentAsset::query()->firstOrCreate(
                    ['reference' => self::MARKER.'-VIOLON'],
                    ['name' => 'Violon d’atelier — démonstration', 'family' => 'violon', 'maker' => 'Instrument confié', 'ownership' => 'consignment', 'status' => InstrumentAssetStatus::Available, 'description' => 'Instrument de démonstration interne, non publié.', 'available_for_sale' => false, 'available_for_rental' => false, 'is_site_published' => false],
                );
                $rentalInstrument = InstrumentAsset::query()->firstOrCreate(
                    ['reference' => self::MARKER.'-ALTO'],
                    ['name' => 'Alto d’étude — démonstration', 'family' => 'alto', 'maker' => 'Parc de démonstration', 'ownership' => 'owned', 'status' => InstrumentAssetStatus::Available, 'description' => 'Instrument de démonstration interne, non publié.', 'available_for_sale' => true, 'suggested_sale_amount' => 1800, 'available_for_rental' => true, 'suggested_rental_amount' => 45, 'is_site_published' => false],
                );

                $order = WorkshopOrder::query()->firstOrCreate(
                    ['reference' => self::MARKER.'-ATELIER'],
                    ['person_id' => $person->id, 'title' => 'Exercice : révision et reméchage', 'instrument_description' => 'Violon confié — essai du parcours atelier.', 'customer_instructions' => 'Vérifier la réponse de l’instrument et remplacer les cordes si nécessaire.', 'diagnosis' => 'Reméchage à prévoir ; âme et cordes à vérifier.', 'status' => WorkshopOrderStatus::Diagnosed, 'notes' => 'Jeu de données supprimable '.self::MARKER],
                );

                foreach ([
                    [$services[self::MARKER.'-REMECHAGE'], true],
                    [$services[self::MARKER.'-AME'], true],
                    [$services[self::MARKER.'-CHEVALET'], false],
                ] as [$service, $included]) {
                    $order->services()->firstOrCreate(
                        ['service_definition_id' => $service->id],
                        ['label_snapshot' => $service->name, 'description_snapshot' => $service->description, 'quantity' => 1, 'unit_amount' => $service->suggested_unit_amount, 'include_in_quote' => $included],
                    );
                }

                foreach ([
                    [$stock[self::MARKER.'-CORDES'], true],
                    [$stock[self::MARKER.'-AME'], true],
                    [$stock[self::MARKER.'-COLOPHANE'], false],
                ] as [$item, $included]) {
                    $order->stockItems()->firstOrCreate(
                        ['stock_item_id' => $item->id],
                        ['label_snapshot' => $item->name, 'quantity' => 1, 'unit_amount' => $item->suggested_unit_amount, 'include_in_quote' => $included],
                    );
                }

                Rental::query()->firstOrCreate(
                    ['reference' => self::MARKER.'-LOCATION'],
                    ['instrument_asset_id' => $rentalInstrument->id, 'person_id' => $person->id, 'status' => RentalStatus::Draft, 'starts_on' => today(), 'expected_return_on' => today()->addMonth(), 'unit_amount' => $rentalInstrument->suggested_rental_amount, 'deposit_amount' => 300, 'notes' => 'Jeu de données supprimable '.self::MARKER],
                );

                $this->components->info("Jeu de démonstration créé pour {$workshopInstrument->name} et {$rentalInstrument->name}.");
            });
        });

        $this->newLine();
        $this->line('Parcours à tester : Dossiers atelier > « Exercice : révision et reméchage » ; puis Locations > « '.self::MARKER.'-LOCATION ».');
        $this->line('Purge contrôlée : php artisan cremona:purge-contempo-demo '.escapeshellarg($organization->slug));

        return self::SUCCESS;
    }

    private function organization(): ?Organization
    {
        if (filled($slug = $this->argument('organization'))) {
            $organization = Organization::query()->where('slug', $slug)->first();
        } else {
            $organizations = Organization::query()->whereRaw('LOWER(name) like ?', ['%contempo%'])->get();
            $organization = $organizations->count() === 1 ? $organizations->first() : null;
        }

        if ($organization === null) {
            $this->components->error('Organisation Contempo introuvable ou ambiguë. Indique son slug : php artisan cremona:seed-contempo-demo <slug>.');
        }

        return $organization;
    }
}

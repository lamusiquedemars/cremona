<?php

namespace App\Console\Commands;

use App\Models\InstrumentAsset;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Quote;
use App\Models\Rental;
use App\Models\ServiceDefinition;
use App\Models\StockItem;
use App\Models\WorkshopOrder;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeContempoDemoScenario extends Command
{
    private const MARKER = 'DEMO-CONTEMPO';

    protected $signature = 'cremona:purge-contempo-demo {organization : Slug de l’organisation Contempo} {--force : Purge sans confirmation interactive}';

    protected $description = 'Supprime exclusivement le jeu de démonstration Contempo créé par cremona:seed-contempo-demo.';

    public function handle(OrganizationContext $context): int
    {
        $organization = Organization::query()->where('slug', $this->argument('organization'))->first();

        if ($organization === null) {
            $this->components->error('Organisation introuvable.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Supprimer uniquement les données marquées « DEMO-CONTEMPO » ?')) {
            $this->info('Purge annulée.');

            return self::SUCCESS;
        }

        $context->run($organization, function (): void {
            DB::transaction(function (): void {
                $orders = WorkshopOrder::query()->where('reference', self::MARKER.'-ATELIER')->get();
                Quote::query()->whereIn('workshop_order_id', $orders->pluck('id'))->delete();
                Rental::query()->where('reference', self::MARKER.'-LOCATION')->delete();
                $orders->each->delete();
                InstrumentAsset::query()->where('reference', 'like', self::MARKER.'-%')->delete();
                StockItem::query()->where('sku', 'like', self::MARKER.'-%')->delete();
                ServiceDefinition::query()->where('code', 'like', self::MARKER.'-%')->delete();
                Person::query()->where('source', self::MARKER)->where('display_name', 'Camille Martin — démonstration')->delete();
            });
        });

        $this->components->info('Jeu de démonstration Contempo supprimé.');

        return self::SUCCESS;
    }
}

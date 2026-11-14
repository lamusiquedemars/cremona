<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\LuthierQuoteLineTemplateCatalog;
use App\Services\LuthierServiceCatalog;
use Illuminate\Console\Command;

class SeedLuthierQuoteLineTemplates extends Command
{
    protected $signature = 'cremona:seed-luthier-quote-lines {organization? : Slug d’une organisation Luthier précise}';

    protected $description = 'Ajoute les lignes de devis Luthier initiales sans écraser les réglages existants.';

    public function handle(LuthierQuoteLineTemplateCatalog $catalog, LuthierServiceCatalog $services): int
    {
        $organizations = filled($this->argument('organization'))
            ? Organization::query()->where('slug', $this->argument('organization'))->get()
            : Organization::query()->where('vertical_pack', 'luthier')->get();

        if ($organizations->isEmpty()) {
            $this->components->warn('Aucune organisation Luthier à amorcer.');

            return self::SUCCESS;
        }

        $created = $organizations->sum(function (Organization $organization) use ($catalog, $services): int {
            $services->seed($organization);

            return $catalog->seed($organization);
        });

        $this->info("{$created} ligne(s) de devis ajoutée(s) pour {$organizations->count()} organisation(s) ; les lignes déjà présentes sont conservées.");

        return self::SUCCESS;
    }
}

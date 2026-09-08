<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\LuthierQuoteLineTemplateCatalog;
use Illuminate\Console\Command;

class SeedLuthierQuoteLineTemplates extends Command
{
    protected $signature = 'cremona:seed-luthier-quote-lines {organization : Slug de l’organisation Luthier}';

    protected $description = 'Ajoute les lignes de devis Luthier initiales sans écraser les réglages existants.';

    public function handle(LuthierQuoteLineTemplateCatalog $catalog): int
    {
        $organization = Organization::query()->where('slug', $this->argument('organization'))->firstOrFail();
        $created = $catalog->seed($organization);

        $this->info("{$created} ligne(s) de devis ajoutée(s) ; les lignes déjà présentes sont conservées.");

        return self::SUCCESS;
    }
}

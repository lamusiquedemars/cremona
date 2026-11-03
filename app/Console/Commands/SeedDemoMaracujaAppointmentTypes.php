<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\DemoMaracujaAppointmentTypeCatalog;
use Illuminate\Console\Command;

class SeedDemoMaracujaAppointmentTypes extends Command
{
    protected $signature = 'cremona:seed-demo-maracuja-appointment-types {organization=demo-maracuja : Slug de l’organisation pilote}';

    protected $description = 'Ajoute les motifs de rendez-vous de Demo Maracuja sans modifier ceux déjà créés.';

    public function handle(DemoMaracujaAppointmentTypeCatalog $catalog): int
    {
        $organization = Organization::query()->where('slug', $this->argument('organization'))->first();
        if ($organization === null) {
            $this->components->error('Organisation introuvable.');

            return self::FAILURE;
        }
        $created = $catalog->seed($organization);
        $this->info("{$created} motif(s) ajouté(s) à {$organization->name}; les motifs existants sont conservés.");

        return self::SUCCESS;
    }
}

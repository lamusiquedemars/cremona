<?php

namespace App\Console\Commands;

use App\Services\RentalGridRateManager;
use Illuminate\Console\Command;

class ApplyRentalGridRates extends Command
{
    protected $signature = 'cremona:apply-rental-grid-rates';

    protected $description = 'Applique les tarifs de location arrivés à leur date d’effet.';

    public function handle(RentalGridRateManager $rates): int
    {
        $updated = $rates->applyDueRates();

        $this->components->info("{$updated} location(s) mise(s) à jour.");

        return self::SUCCESS;
    }
}

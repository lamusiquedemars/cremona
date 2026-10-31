<?php

namespace App\Console\Commands;

use App\Services\SuperPdpSandboxClient;
use Illuminate\Console\Command;

class ConfigureSuperPdpSandboxVatRegime extends Command
{
    protected $signature = 'super-pdp:configure-sandbox-monthly-vat';
    protected $description = 'Configure le régime TVA mensuel dans Super PDP sandbox';

    public function handle(SuperPdpSandboxClient $client): int
    {
        $client->setMonthlyVatRegime();
        $this->info('Régime TVA mensuel configuré dans Super PDP sandbox.');

        return self::SUCCESS;
    }
}

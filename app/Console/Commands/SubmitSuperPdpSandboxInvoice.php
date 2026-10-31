<?php

namespace App\Console\Commands;

use App\Services\SuperPdpSandboxClient;
use Illuminate\Console\Command;
use Throwable;

class SubmitSuperPdpSandboxInvoice extends Command
{
    protected $signature = 'super-pdp:submit-sandbox-invoice {--b2c : Transmet un scénario B2C fictif}';
    protected $description = 'Valide puis remet une facture CII fictive à Super PDP sandbox';

    public function handle(SuperPdpSandboxClient $client): int
    {
        try {
            $result = $client->validateAndSubmitTestInvoice((bool) $this->option('b2c'));
            $this->info('Facture sandbox validée et remise à Super PDP. Identifiant : '.(string) data_get($result, 'id', 'non retourné'));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Recette sandbox impossible : '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

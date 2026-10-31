<?php

namespace App\Console\Commands;

use App\Services\SuperPdpSandboxClient;
use Illuminate\Console\Command;
use Throwable;

class GenerateSuperPdpSandboxInvoice extends Command
{
    protected $signature = 'super-pdp:generate-sandbox-invoice {--b2c : Génère un scénario B2C}';

    protected $description = 'Vérifie la génération d’une facture CII fictive par Super PDP sandbox, sans la transmettre';

    public function handle(SuperPdpSandboxClient $client): int
    {
        try {
            $content = $client->generateTestInvoice((bool) $this->option('b2c'));
            if (! str_contains($content, '<')) {
                $this->error('Super PDP sandbox n’a pas retourné de CII exploitable.');

                return self::FAILURE;
            }
            $this->info('Facture CII sandbox générée et non transmise. Empreinte SHA-256 : '.hash('sha256', $content));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Génération sandbox impossible.');

            return self::FAILURE;
        }
    }
}

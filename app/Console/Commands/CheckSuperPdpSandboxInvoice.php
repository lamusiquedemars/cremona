<?php

namespace App\Console\Commands;

use App\Services\SuperPdpClient;
use Illuminate\Console\Command;
use LogicException;

class CheckSuperPdpSandboxInvoice extends Command
{
    protected $signature = 'super-pdp:check-sandbox-invoice {invoiceId}';
    protected $description = 'Relève les événements d’une facture Super PDP sandbox';

    public function handle(SuperPdpClient $client): int
    {
        $clientId = config('services.super_pdp.sandbox_client_id');
        $clientSecret = config('services.super_pdp.sandbox_client_secret');
        if (! is_string($clientId) || ! is_string($clientSecret) || $clientId === '' || $clientSecret === '') {
            throw new LogicException('Les identifiants serveur Super PDP sandbox ne sont pas configurés.');
        }
        $events = data_get($client->invoiceEvents($clientId, $clientSecret, (string) $this->argument('invoiceId')), 'data', []);
        foreach ($events as $event) {
            $this->line((string) data_get($event, 'id').'  '.(string) data_get($event, 'status_code').'  '.(string) data_get($event, 'created_at'));
        }

        return self::SUCCESS;
    }
}

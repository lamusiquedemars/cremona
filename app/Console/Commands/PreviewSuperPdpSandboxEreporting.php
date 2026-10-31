<?php

namespace App\Console\Commands;

use App\Services\SuperPdpSandboxClient;
use Illuminate\Console\Command;

class PreviewSuperPdpSandboxEreporting extends Command
{
    protected $signature = 'super-pdp:preview-sandbox-ereporting {date : Date ISO de la période} {kind : transaction ou payment}';
    protected $description = 'Lit l’aperçu e-reporting Super PDP sandbox, sans créer de déclaration';

    public function handle(SuperPdpSandboxClient $client): int
    {
        $kind = (string) $this->argument('kind');
        if (! in_array($kind, ['transaction', 'payment'], true)) {
            $this->error('Le type doit être transaction ou payment.');

            return self::INVALID;
        }

        $this->line(json_encode(
            $client->ereportingPreview((string) $this->argument('date'), $kind),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return self::SUCCESS;
    }
}

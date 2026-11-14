<?php

namespace App\Console\Commands;

use App\Models\PrivateDocument;
use App\Models\Organization;
use App\Services\PrivateDocumentStorageMigrator;
use Illuminate\Console\Command;
use Throwable;

class MigratePrivateDocumentsToScaleway extends Command
{
    protected $signature = 'private-documents:migrate-to-scaleway
        {--execute : Copie, vérifie et bascule les documents ; sans cette option, réalise seulement l’inventaire contrôlé}
        {--organization= : Limite à une organisation (slug)}
        {--limit=0 : Limite le nombre de documents inspectés}';

    protected $description = 'Inventorie ou migre les documents privés vers le disque Scaleway privé avec contrôle SHA-256.';

    public function handle(PrivateDocumentStorageMigrator $migrator): int
    {
        $query = PrivateDocument::withoutGlobalScopes()->orderBy('id');
        $slug = $this->option('organization');

        if (is_string($slug) && $slug !== '') {
            $organization = Organization::query()->where('slug', $slug)->first();
            if ($organization === null) {
                $this->components->error('Organisation introuvable.');

                return self::FAILURE;
            }
            $query->where('organization_id', $organization->getKey());
        }

        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $query->limit($limit);
        }

        $execute = (bool) $this->option('execute');
        $counts = ['pending' => 0, 'verified' => 0, 'failed' => 0, 'already_on_target' => 0];

        $query->each(function (PrivateDocument $document) use ($migrator, $execute, &$counts): void {
            try {
                $status = $migrator->migrate($document, execute: $execute);
                $counts[$status]++;
            } catch (Throwable $exception) {
                $counts['failed']++;
                $this->components->error("Document {$document->public_id} : {$exception->getMessage()}");
            }
        });

        $mode = $execute ? 'Migration exécutée' : 'Inventaire contrôlé (aucun fichier ni registre basculé)';
        $this->components->info($mode.'. '.collect($counts)->map(fn (int $count, string $status): string => "{$status}: {$count}")->join(' · '));

        return $counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}

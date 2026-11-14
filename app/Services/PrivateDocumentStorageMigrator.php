<?php

namespace App\Services;

use App\Models\PrivateDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Throwable;

class PrivateDocumentStorageMigrator
{
    /**
     * Copies and verifies a document before changing its recorded disk.
     * Source files are intentionally retained: deletion is a separate,
     * audited retention decision.
     *
     * @return 'pending'|'verified'|'failed'|'already_on_target'
     */
    public function migrate(PrivateDocument $document, string $targetDisk = 'scaleway-private', bool $execute = false): string
    {
        if (! in_array($targetDisk, config('private-documents.allowed_disks', []), true)) {
            throw new LogicException('Le disque de destination des documents privés n’est pas autorisé.');
        }

        if ($document->disk === $targetDisk) {
            return 'already_on_target';
        }

        $journal = DB::table('private_document_storage_migrations')->updateOrInsert(
            ['private_document_id' => $document->getKey()],
            [
                'source_disk' => $document->disk,
                'source_path' => $document->path,
                'target_disk' => $targetDisk,
                'target_path' => $document->path,
                'sha256' => $document->sha256,
                'size' => $document->size,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        unset($journal);

        try {
            $this->assertMatchesRecord($document->disk, $document->path, $document->size, $document->sha256);

            if (! $execute) {
                return 'pending';
            }

            $this->copyIfNeeded($document->disk, $document->path, $targetDisk);
            $this->assertMatchesRecord($targetDisk, $document->path, $document->size, $document->sha256);

            $updated = DB::transaction(function () use ($document, $targetDisk): int {
                $updated = DB::table('private_documents')
                    ->where('id', $document->getKey())
                    ->where('disk', $document->disk)
                    ->where('path', $document->path)
                    ->update(['disk' => $targetDisk, 'path' => $document->path, 'updated_at' => now()]);

                DB::table('private_document_storage_migrations')
                    ->where('private_document_id', $document->getKey())
                    ->update([
                        'status' => 'verified',
                        'attempts' => DB::raw('attempts + 1'),
                        'verified_at' => now(),
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);

                return $updated;
            });

            if ($updated !== 1) {
                throw new RuntimeException('Le document a changé pendant sa migration ; la copie est conservée mais aucun basculement n’a été effectué.');
            }

            return 'verified';
        } catch (Throwable $exception) {
            DB::table('private_document_storage_migrations')
                ->where('private_document_id', $document->getKey())
                ->update([
                    'status' => 'failed',
                    'attempts' => DB::raw('attempts + 1'),
                    'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }

    private function copyIfNeeded(string $sourceDisk, string $path, string $targetDisk): void
    {
        if (Storage::disk($targetDisk)->exists($path)) {
            return;
        }

        $stream = Storage::disk($sourceDisk)->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('La lecture du document source a échoué.');
        }

        try {
            if (! Storage::disk($targetDisk)->writeStream($path, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException('La copie vers le stockage privé a échoué.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function assertMatchesRecord(string $disk, string $path, int $size, string $sha256): void
    {
        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('Le fichier est introuvable sur son stockage.');
        }

        if ((int) Storage::disk($disk)->size($path) !== $size || $this->sha256($disk, $path) !== $sha256) {
            throw new RuntimeException('La taille ou l’empreinte SHA-256 du fichier ne correspond pas au registre.');
        }
    }

    private function sha256(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('La lecture du document a échoué.');
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }
}

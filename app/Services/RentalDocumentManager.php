<?php

namespace App\Services;

use App\Enums\RentalDocumentStatus;
use App\Enums\RentalDocumentType;
use App\Models\PrivateDocument;
use App\Models\Rental;
use App\Models\RentalDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class RentalDocumentManager
{
    /** @param array<string, mixed> $snapshot */
    public function registerGenerated(
        Rental $rental,
        PrivateDocument $privateDocument,
        RentalDocumentType $type,
        string $templateVersion,
        array $snapshot,
        ?User $actor = null,
        ?RentalDocument $previousVersion = null,
    ): RentalDocument {
        return DB::transaction(function () use ($rental, $privateDocument, $type, $templateVersion, $snapshot, $actor, $previousVersion): RentalDocument {
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->getKey());
            $privateDocument = PrivateDocument::query()->lockForUpdate()->findOrFail($privateDocument->getKey());

            if ((int) $rental->organization_id !== (int) $privateDocument->organization_id) {
                throw new LogicException('Le PDF et la location doivent appartenir à la même organisation.');
            }

            $previous = $previousVersion === null ? null : RentalDocument::query()
                ->lockForUpdate()
                ->whereKey($previousVersion->getKey())
                ->firstOrFail();
            if ($previous !== null && ($previous->rental_id !== $rental->id || $previous->type !== $type)) {
                throw new LogicException('Une nouvelle version doit reprendre la même location et le même type de document.');
            }

            $privateDocument->links()->firstOrCreate([
                'organization_id' => $rental->organization_id,
                'linkable_type' => 'rental',
                'linkable_id' => $rental->id,
            ]);

            return RentalDocument::query()->create([
                'rental_id' => $rental->id,
                'private_document_id' => $privateDocument->id,
                'version_of_id' => $previous?->id,
                'version_number' => $previous === null ? 1 : $previous->version_number + 1,
                'type' => $type,
                'status' => RentalDocumentStatus::Generated,
                'template_version' => trim($templateVersion),
                'snapshot' => $snapshot,
                'content_sha256' => $privateDocument->sha256,
                'generated_at' => now(),
                'created_by_user_id' => $actor?->id,
            ]);
        });
    }
}

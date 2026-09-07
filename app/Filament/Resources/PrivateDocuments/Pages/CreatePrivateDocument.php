<?php

namespace App\Filament\Resources\PrivateDocuments\Pages;

use App\Filament\Resources\PrivateDocuments\PrivateDocumentResource;
use App\Models\PrivateDocument;
use App\Services\PrivateDocumentManager;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use LogicException;

class CreatePrivateDocument extends CreateRecord
{
    protected static string $resource = PrivateDocumentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $file = $data['file'] ?? null;

        if (! $file instanceof UploadedFile) {
            throw new LogicException('Veuillez choisir un fichier à déposer.');
        }

        /** @var PrivateDocument $document */
        $document = app(PrivateDocumentManager::class)->create($data, $file, auth()->user());

        return $document;
    }
}

<?php

namespace App\Filament\Resources\PrivateDocuments\Pages;

use App\Filament\Resources\PrivateDocuments\PrivateDocumentResource;
use App\Models\PrivateDocument;
use App\Services\PrivateDocumentManager;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPrivateDocument extends EditRecord
{
    protected static string $resource = PrivateDocumentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var PrivateDocument $document */
        $document = $this->record;

        return array_merge($data, app(PrivateDocumentManager::class)->formLinks($document));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var PrivateDocument $record */
        return app(PrivateDocumentManager::class)->update($record, $data);
    }
}

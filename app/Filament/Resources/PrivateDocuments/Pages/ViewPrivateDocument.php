<?php

namespace App\Filament\Resources\PrivateDocuments\Pages;

use App\Filament\Resources\PrivateDocuments\PrivateDocumentResource;
use App\Models\PrivateDocument;
use App\Services\PrivateDocumentManager;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;
use LogicException;

class ViewPrivateDocument extends ViewRecord
{
    protected static string $resource = PrivateDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Télécharger')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (): string => route('private-documents.download', $this->record->public_id))
                ->openUrlInNewTab(),
            Action::make('newVersion')
                ->label('Déposer une nouvelle version')
                ->icon(Heroicon::OutlinedArrowPath)
                ->schema([
                    FileUpload::make('file')->label('Nouveau fichier')->storeFiles(false)->required()->maxSize(25 * 1024),
                    TextInput::make('title')->label('Intitulé interne')->default(fn (): ?string => $this->record->title)->maxLength(255),
                    TextInput::make('category')->label('Catégorie')->default(fn (): ?string => $this->record->category)->maxLength(100),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'] ?? null;

                    if (! $file instanceof UploadedFile) {
                        throw new LogicException('Veuillez choisir le fichier de la nouvelle version.');
                    }

                    /** @var PrivateDocument $document */
                    $document = app(PrivateDocumentManager::class)->createVersion($this->record, $data, $file, auth()->user());
                    $this->redirect(PrivateDocumentResource::getUrl('view', ['record' => $document]));
                    Notification::make()->title('Nouvelle version déposée.')->success()->send();
                }),
            EditAction::make(),
        ];
    }
}

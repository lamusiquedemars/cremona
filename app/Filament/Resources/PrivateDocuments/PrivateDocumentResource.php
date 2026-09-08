<?php

namespace App\Filament\Resources\PrivateDocuments;

use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\PrivateDocuments\Pages\CreatePrivateDocument;
use App\Filament\Resources\PrivateDocuments\Pages\EditPrivateDocument;
use App\Filament\Resources\PrivateDocuments\Pages\ListPrivateDocuments;
use App\Filament\Resources\PrivateDocuments\Pages\ViewPrivateDocument;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\CrmTask;
use App\Models\IncomingRequest;
use App\Models\Person;
use App\Models\PrivateDocument;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PrivateDocumentResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = PrivateDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Relation client';

    protected static ?string $navigationLabel = 'Documents';

    protected static ?string $presentationKey = 'documents';

    protected static ?string $presentationGroupKey = 'relation_client';

    protected static ?string $modelLabel = 'document privé';

    protected static ?string $pluralModelLabel = 'documents privés';

    protected static ?string $recordTitleAttribute = 'original_name';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Document')->columnSpan(1)->schema([
                FileUpload::make('file')
                    ->label('Fichier')
                    ->storeFiles(false)
                    ->required()
                    ->visibleOn('create')
                    ->maxSize(25 * 1024)
                    ->helperText('25 Mo maximum. Le fichier reste exclusivement dans l’espace privé.'),
                TextInput::make('title')
                    ->label('Intitulé interne')
                    ->maxLength(255)
                    ->helperText('Facultatif : le nom du fichier reste toujours visible.'),
                TextInput::make('category')->label('Catégorie')->maxLength(100),
            ]),
            Section::make('Rattachements facultatifs')->columnSpan(1)->schema([
                Select::make('person_ids')->label('Contacts')->multiple()->options(fn (): array => Person::query()->orderBy('display_name')->pluck('display_name', 'id')->all())->searchable(),
                Select::make('company_ids')->label('Entreprises')->multiple()->options(fn (): array => Company::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                Select::make('incoming_request_ids')->label('Demandes')->multiple()->options(fn (): array => IncomingRequest::query()->orderByDesc('created_at')->limit(100)->get()->mapWithKeys(fn (IncomingRequest $request): array => [$request->getKey() => $request->subject ?: 'Demande sans objet'])->all())->searchable(),
                Select::make('conversation_ids')->label('Correspondances')->multiple()->options(fn (): array => Conversation::query()->orderByDesc('last_message_at')->limit(100)->get()->mapWithKeys(fn (Conversation $conversation): array => [$conversation->getKey() => $conversation->subject ?: 'Correspondance sans objet'])->all())->searchable(),
                Select::make('crm_task_ids')->label('Tâches')->multiple()->options(fn (): array => CrmTask::query()->orderByDesc('created_at')->limit(100)->pluck('title', 'id')->all())->searchable(),
                Select::make('appointment_ids')->label('Rendez-vous')->multiple()->options(fn (): array => Appointment::query()->orderByDesc('starts_at')->limit(100)->get()->mapWithKeys(fn (Appointment $appointment): array => [$appointment->getKey() => $appointment->title.' — '.$appointment->starts_at->format('d/m/Y H:i')])->all())->searchable(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Document')->columnSpan(1)->schema([
                TextEntry::make('original_name')->label('Fichier')->weight('semibold'),
                TextEntry::make('title')->label('Intitulé interne')->placeholder('—'),
                TextEntry::make('category')->label('Catégorie')->placeholder('—'),
                TextEntry::make('size')->label('Taille')->formatStateUsing(fn (int $state): string => number_format($state / 1024 / 1024, 2, ',', ' ').' Mo'),
                TextEntry::make('detected_mime_type')->label('Type')->placeholder('Non déterminé'),
            ]),
            Section::make('Traçabilité')->columnSpan(1)->schema([
                TextEntry::make('uploader.name')->label('Déposé par')->placeholder('Compte supprimé'),
                TextEntry::make('created_at')->label('Déposé le')->dateTime('d/m/Y H:i'),
                TextEntry::make('version_number')->label('Version')->formatStateUsing(fn (int $state): string => 'v'.$state),
                TextEntry::make('previousVersion.original_name')->label('Version précédente')->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('original_name')->label('Fichier')->searchable()->weight('medium')->wrap(),
            TextColumn::make('title')->label('Intitulé')->searchable()->placeholder('—')->wrap(),
            TextColumn::make('category')->label('Catégorie')->toggleable(),
            TextColumn::make('version_number')->label('Version')->formatStateUsing(fn (int $state): string => 'v'.$state),
            TextColumn::make('created_at')->label('Déposé le')->dateTime('d/m/Y H:i')->sortable(),
        ])->recordActions([ViewAction::make(), EditAction::make()])
            ->headerActions([CreateAction::make()->label('Déposer un document')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrivateDocuments::route('/'),
            'create' => CreatePrivateDocument::route('/create'),
            'view' => ViewPrivateDocument::route('/{record}'),
            'edit' => EditPrivateDocument::route('/{record}/edit'),
        ];
    }
}

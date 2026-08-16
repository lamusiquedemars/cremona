<?php

namespace App\Filament\Resources\Appointments;

use App\Enums\AppointmentModality;
use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\IncomingRequests\IncomingRequestResource;
use App\Filament\Resources\People\PersonResource;
use App\Models\Appointment;
use App\Tenancy\OrganizationContext;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Relation client';

    protected static ?string $navigationLabel = 'Rendez-vous';

    protected static ?string $modelLabel = 'rendez-vous';

    protected static ?string $pluralModelLabel = 'rendez-vous';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 40;

    protected static bool $isGloballySearchable = true;

    protected static int $globalSearchResultsLimit = 10;

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'person.display_name', 'company.name', 'location'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Appointment $record */
        return array_filter([
            'Contact' => $record->person?->display_name,
            'Date' => $record->starts_at->format('d/m/Y H:i'),
            'Statut' => $record->status->getLabel(),
        ]);
    }

    public static function getDefaultTimezone(): string
    {
        $organization = app(OrganizationContext::class)->require();

        return (string) ($organization->settings['timezone'] ?? config('app.timezone', 'UTC'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Rendez-vous')
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Objet')
                            ->required()
                            ->maxLength(255),
                        Grid::make(2)->schema([
                            DateTimePicker::make('starts_at')
                                ->label('Début')
                                ->required()
                                ->timezone(fn (?Appointment $record): string => $record?->timezone ?? static::getDefaultTimezone())
                                ->seconds(false),
                            DateTimePicker::make('ends_at')
                                ->label('Fin')
                                ->required()
                                ->timezone(fn (?Appointment $record): string => $record?->timezone ?? static::getDefaultTimezone())
                                ->seconds(false)
                                ->after('starts_at'),
                        ]),
                        Textarea::make('description')
                            ->label('Informations internes')
                            ->rows(4)
                            ->maxLength(5000),
                    ]),
                Section::make('État')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('status')
                            ->label('Statut')
                            ->options(AppointmentStatus::class)
                            ->default(AppointmentStatus::Scheduled)
                            ->required(),
                        Select::make('assigned_user_id')
                            ->label('Responsable')
                            ->options(fn (): array => app(OrganizationContext::class)
                                ->require()
                                ->users()
                                ->orderBy('name')
                                ->pluck('name', 'users.id')
                                ->all())
                            ->searchable()
                            ->preload(),
                        TextInput::make('timezone')
                            ->label('Fuseau horaire')
                            ->default(fn (): string => static::getDefaultTimezone())
                            ->required()
                            ->maxLength(64),
                    ]),
                Section::make('Participants et origine')
                    ->columnSpan(2)
                    ->schema([
                        Select::make('person_id')
                            ->label('Contact')
                            ->relationship('person', 'display_name')
                            ->searchable()
                            ->preload(),
                        Select::make('company_id')
                            ->label('Entreprise')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('incoming_request_id')
                            ->label('Demande d’origine')
                            ->relationship('incomingRequest', 'subject')
                            ->searchable(['subject', 'name_snapshot'])
                            ->preload(),
                    ]),
                Section::make('Modalité')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('modality')
                            ->label('Modalité')
                            ->options(AppointmentModality::class)
                            ->default(AppointmentModality::Video)
                            ->required(),
                        TextInput::make('location')
                            ->label('Lieu ou indication')
                            ->maxLength(255),
                        TextInput::make('meeting_url')
                            ->label('Lien de connexion')
                            ->url()
                            ->maxLength(2048),
                    ]),
                Section::make('Synchronisation externe')
                    ->description('Ces informations identifient le rendez-vous chez Brevo ou un autre fournisseur. Cremona ne gère pas les disponibilités.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('provider')
                                ->label('Fournisseur')
                                ->options([
                                    'manual' => 'Saisie manuelle',
                                    'brevo' => 'Brevo Meetings',
                                ])
                                ->default('manual')
                                ->required(),
                            TextInput::make('external_reference')
                                ->label('Référence externe')
                                ->maxLength(255),
                        ]),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Rendez-vous')->columnSpan(2)->schema([
                    TextEntry::make('title')->label('Objet')->weight('semibold')->size('lg'),
                    TextEntry::make('description')->label('Informations internes')->placeholder('—'),
                    Grid::make(2)->schema([
                        TextEntry::make('starts_at')->label('Début')->dateTime('d/m/Y H:i'),
                        TextEntry::make('ends_at')->label('Fin')->dateTime('d/m/Y H:i'),
                    ]),
                ]),
                Section::make('Suivi')->columnSpan(1)->schema([
                    TextEntry::make('status')->label('Statut')->badge(),
                    TextEntry::make('assignedUser.name')->label('Responsable')->placeholder('Non attribué'),
                    TextEntry::make('timezone')->label('Fuseau horaire'),
                ]),
                Section::make('Participants')->columnSpan(2)->schema([
                    TextEntry::make('person.display_name')
                        ->label('Contact')
                        ->url(fn (Appointment $record): ?string => $record->person
                            ? PersonResource::getUrl('view', ['record' => $record->person])
                            : null)
                        ->placeholder('—'),
                    TextEntry::make('company.name')
                        ->label('Entreprise')
                        ->url(fn (Appointment $record): ?string => $record->company
                            ? CompanyResource::getUrl('view', ['record' => $record->company])
                            : null)
                        ->placeholder('—'),
                    TextEntry::make('incomingRequest.subject')
                        ->label('Demande d’origine')
                        ->url(fn (Appointment $record): ?string => $record->incomingRequest
                            ? IncomingRequestResource::getUrl('view', ['record' => $record->incomingRequest])
                            : null)
                        ->placeholder('—'),
                ]),
                Section::make('Modalité')->columnSpan(1)->schema([
                    TextEntry::make('modality')->label('Modalité')->badge(),
                    TextEntry::make('location')->label('Lieu')->placeholder('—'),
                    TextEntry::make('meeting_url')->label('Connexion')->url(fn (?string $state): ?string => $state)->openUrlInNewTab()->placeholder('—'),
                    TextEntry::make('provider')
                        ->label('Origine')
                        ->formatStateUsing(fn (string $state): string => $state === 'brevo' ? 'Synchronisé par Brevo' : 'Importé'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('title')->label('Rendez-vous')->searchable()->weight('medium'),
                TextColumn::make('person.display_name')->label('Contact')->placeholder('—')->searchable(),
                TextColumn::make('modality')->label('Modalité')->badge(),
                TextColumn::make('status')->label('Statut')->badge()->sortable(),
                TextColumn::make('assignedUser.name')->label('Responsable')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(AppointmentStatus::class),
                SelectFilter::make('modality')->label('Modalité')->options(AppointmentModality::class),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppointments::route('/'),
            'view' => ViewAppointment::route('/{record}'),
        ];
    }
}

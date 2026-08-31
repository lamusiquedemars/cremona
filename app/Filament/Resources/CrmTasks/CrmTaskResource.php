<?php

namespace App\Filament\Resources\CrmTasks;

use App\Enums\CrmTaskPriority;
use App\Enums\CrmTaskStatus;
use App\Filament\Resources\CrmTasks\Pages\CreateCrmTask;
use App\Filament\Resources\CrmTasks\Pages\EditCrmTask;
use App\Filament\Resources\CrmTasks\Pages\ListCrmTasks;
use App\Filament\Resources\CrmTasks\Pages\ViewCrmTask;
use App\Models\Conversation;
use App\Models\CrmTask;
use App\Models\IncomingRequest;
use App\Tenancy\OrganizationContext;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
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
use UnitEnum;

class CrmTaskResource extends Resource
{
    protected static ?string $model = CrmTask::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Relation client';

    protected static ?string $navigationLabel = 'Tâches';

    protected static ?string $modelLabel = 'tâche';

    protected static ?string $pluralModelLabel = 'tâches';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 50;

    public static function organizationTimezone(): string
    {
        return app(OrganizationContext::class)->require()->timezone();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Tâche')->columnSpan(2)->schema([
                TextInput::make('title')->label('À faire')->required()->maxLength(255),
                Textarea::make('description')->label('Précisions internes')->rows(5)->maxLength(5000),
                Grid::make(2)->schema([
                    DateTimePicker::make('due_at')->label('Échéance')->timezone(fn (): string => static::organizationTimezone())->seconds(false),
                    Select::make('priority')->label('Priorité')->options(CrmTaskPriority::class)->default(CrmTaskPriority::Normal)->required(),
                ]),
            ]),
            Section::make('Suivi')->columnSpan(1)->schema([
                Select::make('status')->label('Statut')->options(CrmTaskStatus::class)->default(CrmTaskStatus::Open)->required(),
                Select::make('assigned_user_id')
                    ->label('Responsable')
                    ->options(fn (): array => app(OrganizationContext::class)->require()->users()->orderBy('name')->pluck('name', 'users.id')->all())
                    ->searchable()->preload(),
            ]),
            Section::make('Rattachements')->columnSpanFull()->columns(2)->schema([
                Select::make('person_id')->label('Contact')->relationship('person', 'display_name')->searchable()->preload(),
                Select::make('company_id')->label('Entreprise')->relationship('company', 'name')->searchable()->preload(),
                Select::make('incoming_request_id')
                    ->label('Demande')
                    ->relationship('incomingRequest', 'subject')
                    ->getOptionLabelFromRecordUsing(fn (IncomingRequest $request): string => $request->subject ?: 'Demande sans objet')
                    ->searchable(['subject', 'name_snapshot'])
                    ->preload(),
                Select::make('conversation_id')
                    ->label('Correspondance')
                    ->relationship('conversation', 'subject')
                    ->getOptionLabelFromRecordUsing(fn (Conversation $conversation): string => $conversation->subject ?: 'Sans objet')
                    ->searchable()
                    ->preload(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Tâche')->columnSpan(2)->schema([
                TextEntry::make('title')->label('À faire')->weight('semibold')->size('lg'),
                TextEntry::make('description')->label('Précisions internes')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Suivi')->columnSpan(1)->schema([
                TextEntry::make('status')->label('Statut')->badge(),
                TextEntry::make('priority')->label('Priorité')->badge(),
                TextEntry::make('assignedUser.name')->label('Responsable')->placeholder('Non attribué'),
                TextEntry::make('due_at')->label('Échéance')->dateTime('d/m/Y H:i')->timezone(fn (): string => static::organizationTimezone())->placeholder('Sans échéance'),
                TextEntry::make('completed_at')->label('Terminée le')->dateTime('d/m/Y H:i')->timezone(fn (): string => static::organizationTimezone())->placeholder('—'),
            ]),
            Section::make('Rattachements')->columnSpanFull()->columns(4)->schema([
                TextEntry::make('person.display_name')->label('Contact')->placeholder('—'),
                TextEntry::make('company.name')->label('Entreprise')->placeholder('—'),
                TextEntry::make('incomingRequest.subject')->label('Demande')->placeholder('—'),
                TextEntry::make('conversation.subject')->label('Correspondance')->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('due_at')->columns([
            TextColumn::make('title')->label('Tâche')->searchable()->weight('medium')->wrap(),
            TextColumn::make('status')->label('Statut')->badge()->sortable(),
            TextColumn::make('priority')->label('Priorité')->badge()->sortable(),
            TextColumn::make('due_at')->label('Échéance')->dateTime('d/m/Y H:i')->timezone(fn (): string => static::organizationTimezone())->sortable()->placeholder('—'),
            TextColumn::make('assignedUser.name')->label('Responsable')->placeholder('Non attribué'),
        ])->filters([
            SelectFilter::make('status')->label('Statut')->options(CrmTaskStatus::class),
            SelectFilter::make('priority')->label('Priorité')->options(CrmTaskPriority::class),
        ])->recordActions([ViewAction::make(), EditAction::make()])->headerActions([CreateAction::make()->label('Nouvelle tâche')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmTasks::route('/'),
            'create' => CreateCrmTask::route('/create'),
            'view' => ViewCrmTask::route('/{record}'),
            'edit' => EditCrmTask::route('/{record}/edit'),
        ];
    }
}

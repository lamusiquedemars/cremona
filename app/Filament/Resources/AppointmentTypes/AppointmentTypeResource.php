<?php

namespace App\Filament\Resources\AppointmentTypes;

use App\Enums\OrganizationPermission;
use App\Filament\Concerns\UsesOrganizationModule;
use App\Filament\Resources\AppointmentTypes\Pages\CreateAppointmentType;
use App\Filament\Resources\AppointmentTypes\Pages\EditAppointmentType;
use App\Filament\Resources\AppointmentTypes\Pages\ListAppointmentTypes;
use App\Models\AppointmentType;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AppointmentTypeResource extends Resource
{
    use UsesOrganizationModule;

    protected static ?string $model = AppointmentType::class;

    protected static string $organizationModule = 'appointments';

    protected static ?OrganizationPermission $organizationAccessPermission = OrganizationPermission::ManageCommercialReferences;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Référentiels métier';

    protected static ?string $navigationLabel = 'Motifs de rendez-vous';

    protected static ?string $modelLabel = 'motif de rendez-vous';

    protected static ?string $pluralModelLabel = 'motifs de rendez-vous';

    protected static ?int $navigationSort = 66;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make('Motif de rendez-vous')
                ->description('Un motif explique la raison du rendez-vous. Il peut être proposé sur votre page Brevo et utilisé lors d’une saisie manuelle.')
                ->columns(12)->columnSpanFull()->schema([
                    TextInput::make('label')->label('Intitulé')->required()->maxLength(255)->columnSpan(6),
                    TextInput::make('default_duration_minutes')->label('Durée habituelle')->numeric()->minValue(5)->maxValue(480)->suffix('min')->columnSpan(3),
                    Checkbox::make('is_active')->label('Motif actif')->default(true)->columnSpan(3),
                    Textarea::make('description')->label('Précisions internes')->rows(3)->maxLength(2000)->columnSpanFull(),
                    TextInput::make('brevo_meeting_name')->label('Nom du type de rendez-vous dans Brevo')
                        ->helperText('À renseigner seulement si Brevo utilise un intitulé différent. Il permet le rapprochement automatique.')
                        ->maxLength(255)->columnSpan(6),
                    Select::make('quote_line_template_id')->label('Offre commerciale associée')
                        ->relationship('quoteLineTemplate', 'label', fn ($query) => $query->where('is_active', true))
                        ->searchable()->preload()->helperText('Optionnel : elle facilite la préparation d’un devis, sans en créer un automatiquement.')
                        ->columnSpan(6),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('label')->columns([
            TextColumn::make('label')->label('Motif')->searchable()->sortable(),
            TextColumn::make('default_duration_minutes')->label('Durée')->suffix(' min')->placeholder('—')->sortable(),
            TextColumn::make('quoteLineTemplate.label')->label('Offre associée')->placeholder('—')->toggleable(),
            TextColumn::make('brevo_meeting_name')->label('Nom Brevo')->placeholder('Même intitulé')->toggleable(),
            ToggleColumn::make('is_active')->label('Actif'),
        ])->recordActions([EditAction::make()])->headerActions([CreateAction::make()->label('Nouveau motif')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAppointmentTypes::route('/'), 'create' => CreateAppointmentType::route('/create'), 'edit' => EditAppointmentType::route('/{record}/edit')];
    }
}

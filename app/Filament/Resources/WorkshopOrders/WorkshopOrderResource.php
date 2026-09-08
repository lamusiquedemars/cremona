<?php

namespace App\Filament\Resources\WorkshopOrders;

use App\Enums\WorkshopOrderStatus;
use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\WorkshopOrders\Pages\CreateWorkshopOrder;
use App\Filament\Resources\WorkshopOrders\Pages\EditWorkshopOrder;
use App\Filament\Resources\WorkshopOrders\Pages\ListWorkshopOrders;
use App\Models\IncomingRequest;
use App\Models\WorkshopOrder;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkshopOrderResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = WorkshopOrder::class;

    protected static ?string $navigationLabel = 'Dossiers atelier';

    protected static ?string $presentationGroupKey = 'relation_client';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'Relation client';

    protected static ?int $navigationSort = 55;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make('Dossier atelier')->columns(12)->columnSpanFull()->schema([
                TextInput::make('title')->label('Intitulé')->required()->columnSpan(6),
                Select::make('status')->label('Statut')->options(WorkshopOrderStatus::class)->default(WorkshopOrderStatus::Received)->required()->columnSpan(3),
                TextInput::make('reference')->label('Référence')->helperText('Générée automatiquement si laissée vide.')->columnSpan(3),
                Select::make('person_id')->label('Client')->relationship('person', 'display_name')->searchable()->preload()->columnSpan(6),
                Select::make('incoming_request_id')->label('Demande d’origine')->relationship('incomingRequest', 'subject')->getOptionLabelFromRecordUsing(fn (IncomingRequest $request): string => $request->subject ?: 'Demande sans objet')->searchable()->columnSpan(6),
                Textarea::make('instrument_description')->label('Instrument confié')->rows(3)->columnSpan(6),
                Textarea::make('customer_instructions')->label('Demande du client')->rows(3)->columnSpan(6),
                Textarea::make('diagnosis')->label('Diagnostic atelier')->rows(4)->columnSpanFull(),
                Repeater::make('services')->label('Prestations prévues')->relationship()->schema([
                    Select::make('service_definition_id')->label('Prestation')->relationship('definition', 'name')->searchable(),
                    TextInput::make('label_snapshot')->label('Intitulé')->required(),
                    Textarea::make('description_snapshot')->label('Description')->rows(2),
                    TextInput::make('quantity')->label('Quantité')->numeric()->default(1),
                    TextInput::make('unit_amount')->label('Prix HT')->numeric()->prefix('€')->default(0),
                    Checkbox::make('include_in_quote')->label('À ajouter au devis'),
                ])->columns(3)->columnSpanFull(),
                DateTimePicker::make('received_at')->label('Reçu le')->seconds(false)->columnSpan(3),
                DateTimePicker::make('due_at')->label('Échéance prévue')->seconds(false)->columnSpan(3),
                DateTimePicker::make('ready_at')->label('Prêt le')->seconds(false)->columnSpan(3),
                DateTimePicker::make('returned_at')->label('Restitué le')->seconds(false)->columnSpan(3),
                Textarea::make('notes')->label('Notes internes')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('reference')->label('Référence')->searchable(), TextColumn::make('title')->label('Dossier')->searchable()->wrap(), TextColumn::make('person.display_name')->label('Client')->placeholder('—'), TextColumn::make('status')->label('Statut')->badge(), TextColumn::make('due_at')->label('Échéance')->dateTime('d/m/Y')->placeholder('—'),
        ])->filters([SelectFilter::make('status')->options(WorkshopOrderStatus::class)])->recordActions([EditAction::make()])->headerActions([CreateAction::make()->label('Nouveau dossier atelier')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWorkshopOrders::route('/'), 'create' => CreateWorkshopOrder::route('/create'), 'edit' => EditWorkshopOrder::route('/{record}/edit')];
    }
}

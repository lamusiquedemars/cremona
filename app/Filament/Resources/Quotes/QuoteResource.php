<?php

namespace App\Filament\Resources\Quotes;

use App\Enums\QuoteStatus;
use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\Quotes\Pages\CreateQuote;
use App\Filament\Resources\Quotes\Pages\EditQuote;
use App\Filament\Resources\Quotes\Pages\ListQuotes;
use App\Filament\Resources\Quotes\Pages\ViewQuote;
use App\Models\IncomingRequest;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\OrganizationPresentation;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuoteResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = Quote::class;

    protected static ?string $navigationLabel = 'Devis';

    protected static ?string $presentationKey = 'quotes';

    protected static ?string $presentationGroupKey = 'relation_client';

    protected static ?string $modelLabel = 'devis';

    protected static ?string $pluralModelLabel = 'devis';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyEuro;

    protected static string|\UnitEnum|null $navigationGroup = 'Relation client';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(fn (): string => app(OrganizationPresentation::class)->label('quotes', 'Devis'))->columnSpan(2)->schema([
                TextInput::make('reference')->label('Référence')->required()->maxLength(80),
                TextInput::make('title')->label('Objet')->required()->maxLength(255),
                Textarea::make('introduction')->label('Introduction')->rows(3),
                Repeater::make('lines')->label('Lignes')->relationship()->orderColumn('position')->schema([
                    Select::make('kind')->label('Type')->options([
                        'service' => 'Prestation',
                        'product' => 'Produit',
                        'rental' => 'Location',
                        'fee' => 'Frais',
                    ]),
                    Textarea::make('description')->label('Description')->required()->rows(2)->columnSpan(2),
                    TextInput::make('quantity')->label('Quantité')->numeric()->default(1)->minValue(0.01),
                    TextInput::make('unit_amount')->label('Prix unitaire')->numeric()->prefix('€')->default(0),
                ])->columns(4)->addActionLabel('Ajouter une ligne')->columnSpanFull(),
            ]),
            Section::make('Suivi')->columnSpan(1)->schema([
                Select::make('status')->label('Statut')->options(QuoteStatus::class)->default(QuoteStatus::Draft)->required(),
                TextInput::make('currency')->label('Devise')->default('EUR')->required()->length(3),
                DatePicker::make('issued_on')->label('Date du devis'), DatePicker::make('valid_until')->label('Valable jusqu’au'),
                TextInput::make('discount_amount')->label('Remise globale')->numeric()->prefix('€')->default(0),
            ]),
            Section::make('Destinataire et conditions')->columnSpanFull()->columns(3)->schema([
                Select::make('person_id')->label('Contact')->relationship('person', 'display_name')->searchable()->preload(),
                Select::make('company_id')->label('Entreprise')->relationship('company', 'name')->searchable()->preload(),
                Select::make('incoming_request_id')->label('Demande')->relationship('incomingRequest', 'subject')->getOptionLabelFromRecordUsing(fn (IncomingRequest $request): string => $request->subject ?: 'Demande sans objet')->searchable()->preload(),
                Textarea::make('tax_note')->label('Note fiscale')->rows(2), Textarea::make('payment_terms')->label('Conditions de règlement')->rows(2), Textarea::make('notes')->label('Notes internes')->rows(2),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Devis')->columnSpan(2)->schema([TextEntry::make('reference')->label('Référence')->weight('semibold'), TextEntry::make('title')->label('Objet')->size('lg'), TextEntry::make('introduction')->label('Introduction')->columnSpanFull(), RepeatableEntry::make('lines')->label('Détail')->schema([TextEntry::make('kind')->label('Type'), TextEntry::make('description')->label('Description'), TextEntry::make('quantity')->label('Qté'), TextEntry::make('unit_amount')->label('Prix')->money(fn (QuoteLine $record) => $record->quote->currency), TextEntry::make('total_amount')->label('Total')->money(fn (QuoteLine $record) => $record->quote->currency)])->columns(5)->columnSpanFull()]),
            Section::make('Total')->columnSpan(1)->schema([TextEntry::make('status')->label('Statut')->badge(), TextEntry::make('subtotal_amount')->label('Sous-total')->money(fn (Quote $record) => $record->currency), TextEntry::make('discount_amount')->label('Remise')->money(fn (Quote $record) => $record->currency), TextEntry::make('total_amount')->label('Total final')->money(fn (Quote $record) => $record->currency)->weight('bold')]),
            Section::make('Conditions')->columnSpanFull()->columns(3)->schema([TextEntry::make('tax_note')->label('Note fiscale')->placeholder('—'), TextEntry::make('payment_terms')->label('Règlement')->placeholder('—'), TextEntry::make('valid_until')->label('Valable jusqu’au')->date('d/m/Y')->placeholder('—')]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([TextColumn::make('reference')->label('Référence')->searchable(), TextColumn::make('title')->label('Objet')->searchable()->wrap(), TextColumn::make('status')->label('Statut')->badge(), TextColumn::make('total_amount')->label('Total')->money(fn (Quote $record) => $record->currency)->sortable(), TextColumn::make('valid_until')->label('Valable jusqu’au')->date('d/m/Y')->placeholder('—')])->filters([SelectFilter::make('status')->options(QuoteStatus::class)])->recordActions([ViewAction::make(), EditAction::make()])->headerActions([CreateAction::make()->label(fn (): string => app(OrganizationPresentation::class)->createActionLabel('quotes', 'Devis'))]);
    }

    public static function getPages(): array
    {
        return ['index' => ListQuotes::route('/'), 'create' => CreateQuote::route('/create'), 'view' => ViewQuote::route('/{record}'), 'edit' => EditQuote::route('/{record}/edit')];
    }
}

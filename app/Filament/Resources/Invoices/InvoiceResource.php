<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceStatus;
use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Invoice;
use App\Models\OrganizationInvoiceSettings;
use App\Models\Rental;
use App\Models\WorkshopOrder;
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

class InvoiceResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = Invoice::class;

    protected static string $organizationModule = 'invoicing';

    protected static ?string $presentationGroupKey = 'commercial_activity';

    protected static ?string $presentationKey = 'invoicing';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyEuro;

    protected static string|\UnitEnum|null $navigationGroup = 'Activité commerciale';

    protected static ?string $navigationLabel = 'Factures';

    protected static ?string $modelLabel = 'facture';

    protected static ?string $pluralModelLabel = 'factures';

    protected static ?int $navigationSort = 70;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make('Informations de facturation')
                ->description('Préparez la facture, son destinataire et ses conditions avant de lui attribuer son numéro définitif.')
                ->columns(12)->columnSpanFull()->schema([
                    TextInput::make('reference')->label('Numéro')->disabled()->dehydrated(false)->placeholder('Attribué à l’émission')->helperText('Le numéro définitif est attribué une seule fois, à l’émission.')->columnSpan(3),
                    TextInput::make('title')->label('Objet')->required()->maxLength(255)->columnSpan(6),
                    Select::make('kind')->label('Type')->options(['invoice' => 'Facture', 'credit_note' => 'Avoir'])->default('invoice')->required()->columnSpan(3),
                    Select::make('person_id')->label('Client particulier')->relationship('person', 'display_name')->searchable()->preload()->live()->columnSpan(6),
                    Select::make('company_id')->label('Client professionnel')->relationship('company', 'name')->searchable()->preload()->live()->columnSpan(6),
                    DatePicker::make('issued_on')->label('Date d’émission')->native(false)->columnSpan(3),
                    DatePicker::make('supply_on')->label('Date de livraison / prestation')->native(false)->columnSpan(3),
                    DatePicker::make('due_on')->label('Échéance')->native(false)->columnSpan(3),
                    TextInput::make('currency')->label('Devise')->default(fn (): string => (string) (OrganizationInvoiceSettings::query()->value('currency') ?? 'EUR'))->required()->length(3)->columnSpan(3),
                    Select::make('rental_id')->label('Location liée')->options(fn (): array => Rental::query()->with('instrument')->orderByDesc('created_at')->limit(150)->get()->mapWithKeys(fn (Rental $rental): array => [$rental->id => $rental->reference.' — '.($rental->instrument?->name ?? 'Instrument')])->all())->searchable()->columnSpan(4),
                    Select::make('workshop_order_id')->label('Dossier atelier lié')->options(fn (): array => WorkshopOrder::query()->orderByDesc('created_at')->limit(150)->pluck('reference', 'id')->all())->searchable()->columnSpan(4),
                    Select::make('quote_id')->label('Devis lié')->relationship('quote', 'reference')->searchable()->preload()->columnSpan(4),
                    Textarea::make('introduction')->label('Introduction')->rows(2)->columnSpanFull(),
                    Repeater::make('lines')->relationship()->orderColumn('position')->label('Lignes')->schema([
                        Textarea::make('description')->label('Description')->required()->rows(2)->columnSpan(2),
                        TextInput::make('quantity')->label('Qté')->numeric()->minValue(0.001)->default(1),
                        TextInput::make('unit')->label('Unité')->placeholder('ex. pièce'),
                        TextInput::make('unit_amount')->label('Prix HT')->numeric()->prefix('€')->default(0),
                        TextInput::make('discount_amount')->label('Remise HT')->numeric()->prefix('€')->default(0),
                        TextInput::make('tax_rate')->label('TVA %')->numeric()->minValue(0)->maxValue(100)->default(fn (): float => (float) (OrganizationInvoiceSettings::query()->value('default_tax_rate') ?? 20)),
                        Select::make('tax_category')->label('Catégorie TVA')->options([
                            'standard' => 'Taux normal', 'exempt' => 'Exonérée', 'zero_rated' => 'Taux zéro',
                            'reverse_charge' => 'Autoliquidation', 'out_of_scope' => 'Hors champ',
                        ])->default('standard')->required(),
                        Textarea::make('tax_exemption_reason')->label('Motif TVA')->rows(2)->maxLength(500)
                            ->helperText('Obligatoire lorsque la catégorie n’est pas « Taux normal ».')->columnSpan(2),
                    ])->columns(3)->addActionLabel('Ajouter une ligne')->columnSpanFull()
                        ->disabled(fn (?Invoice $record): bool => $record !== null && $record->status !== InvoiceStatus::Draft),
                    Textarea::make('payment_terms')->label('Conditions de règlement')->rows(3)->columnSpan(4),
                    Textarea::make('payment_instructions')->label('Instructions de paiement')->rows(3)->columnSpan(4),
                    Textarea::make('late_payment_terms')->label('Retard de paiement')->rows(3)->columnSpan(4),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Facture')->columnSpan(2)->schema([
                TextEntry::make('reference')->label('Numéro')->placeholder('Brouillon'),
                TextEntry::make('title')->label('Objet')->weight('semibold')->size('lg'),
                TextEntry::make('recipient_snapshot.name')->label('Client')->placeholder('À renseigner'),
                TextEntry::make('issued_on')->label('Émise le')->date('d/m/Y')->placeholder('—'),
                RepeatableEntry::make('lines')->label('Détail')->schema([
                    TextEntry::make('description')->label('Description'), TextEntry::make('quantity')->label('Qté'), TextEntry::make('unit_amount')->label('HT')->money('EUR'), TextEntry::make('tax_rate')->label('TVA')->suffix(' %'), TextEntry::make('total_amount')->label('TTC')->money('EUR'),
                ])->columns(5)->columnSpanFull(),
            ]),
            Section::make('Totaux')->columnSpan(1)->schema([
                TextEntry::make('status')->label('Statut')->badge(),
                TextEntry::make('subtotal_amount')->label('Total HT')->money('EUR'),
                TextEntry::make('tax_amount')->label('TVA')->money('EUR'),
                TextEntry::make('total_amount')->label('Total TTC')->money('EUR')->weight('bold'),
                TextEntry::make('balance_amount')->label('Reste à régler')->money('EUR')->weight('bold'),
            ]),
            Section::make('Règlement')->columnSpanFull()->columns(3)->schema([
                TextEntry::make('due_on')->label('Échéance')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('payment_terms')->label('Conditions')->placeholder('—'),
                TextEntry::make('payment_instructions')->label('Instructions')->placeholder('—'),
            ]),
            Section::make('Transmission réglementaire')->columnSpanFull()->schema([
                RepeatableEntry::make('transmissions')->label('Journal de transmission')->schema([
                    TextEntry::make('provider')->label('Canal')->formatStateUsing(fn (?string $state): string => $state === 'super_pdp' ? 'Super PDP' : (string) $state),
                    TextEntry::make('status')->label('État')->badge()->formatStateUsing(fn (?string $state): string => match ($state) {
                        'awaiting_configuration' => 'En attente de configuration',
                        'ready_to_transmit' => 'Prête à transmettre',
                        'validated' => 'Document validé',
                        'validation_failed' => 'Validation refusée',
                        'submitting' => 'Transmission en cours',
                        'submission_unknown' => 'Résultat inconnu — vérifier Super PDP',
                        'submitted' => 'Remise à Super PDP',
                        'submission_failed' => 'Transmission refusée',
                        'sent' => 'Envoyée',
                        'refused' => 'Refusée par le destinataire',
                        'invalid' => 'Document invalide ou inadmissible',
                        'rejected' => 'Rejetée',
                        default => str($state ?? '—')->replace('_', ' ')->headline()->toString(),
                    }),
                    TextEntry::make('metadata.message')->label('Information')->placeholder('—'),
                    TextEntry::make('last_error')->label('Information ou erreur')->placeholder('—')->columnSpan(2),
                    RepeatableEntry::make('metadata.events')->label('Événements Super PDP')->schema([
                        TextEntry::make('status_code')->label('Code'),
                        TextEntry::make('status_text')->label('Statut')->placeholder('—'),
                        TextEntry::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('data.reason')->label('Motif')->placeholder('—'),
                    ])->columns(4)->columnSpanFull(),
                    TextEntry::make('created_at')->label('Créée le')->dateTime('d/m/Y H:i'),
                ])->columns(4),
            ])->visible(fn (Invoice $record): bool => $record->status !== InvoiceStatus::Draft),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('reference')->label('Numéro')->placeholder('Brouillon')->searchable()->sortable(),
            TextColumn::make('title')->label('Objet')->searchable()->wrap()->sortable(),
            TextColumn::make('status')->label('Statut')->badge()->sortable(),
            TextColumn::make('total_amount')->label('TTC')->money(fn (Invoice $record) => $record->currency)->sortable(),
            TextColumn::make('balance_amount')->label('À régler')->money(fn (Invoice $record) => $record->currency)->sortable(),
            TextColumn::make('due_on')->label('Échéance')->date('d/m/Y')->placeholder('—')->sortable(),
        ])->filters([SelectFilter::make('status')->options(InvoiceStatus::class)])
            ->recordActions([ViewAction::make(), EditAction::make()->label('Modifier')->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Draft)])
            ->recordUrl(fn (Invoice $record): string => self::getUrl('view', ['record' => $record]))
            ->headerActions([CreateAction::make()->label('Nouvelle facture')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInvoices::route('/'), 'create' => CreateInvoice::route('/create'), 'view' => ViewInvoice::route('/{record}'), 'edit' => EditInvoice::route('/{record}/edit')];
    }
}

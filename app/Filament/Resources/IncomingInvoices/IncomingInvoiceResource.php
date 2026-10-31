<?php

namespace App\Filament\Resources\IncomingInvoices;

use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\IncomingInvoices\Pages\ListIncomingInvoices;
use App\Filament\Resources\IncomingInvoices\Pages\ViewIncomingInvoice;
use App\Models\IncomingInvoice;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IncomingInvoiceResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = IncomingInvoice::class;

    protected static string $organizationModule = 'invoicing';

    protected static ?string $presentationGroupKey = 'commercial_activity';

    protected static ?string $presentationKey = 'invoicing';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Activité commerciale';

    protected static ?string $navigationLabel = 'Factures reçues';

    protected static ?string $modelLabel = 'facture reçue';

    protected static ?string $pluralModelLabel = 'factures reçues';

    protected static ?int $navigationSort = 71;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Facture reçue')->columnSpan(2)->columns(2)->schema([
                TextEntry::make('reference')->label('Référence')->placeholder('—'),
                TextEntry::make('supplier_name')->label('Fournisseur')->placeholder('—'),
                TextEntry::make('issued_on')->label('Émise le')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('status')->label('Statut Super PDP')->badge()->placeholder('—'),
            ]),
            Section::make('Synchronisation')->columnSpan(1)->schema([
                TextEntry::make('provider')->label('Canal')->formatStateUsing(fn (): string => 'Super PDP'),
                TextEntry::make('synchronized_at')->label('Dernière synchronisation')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('originalPrivateDocument.original_name')->label('Original conservé')->placeholder('Pas encore téléchargé'),
            ]),
            Section::make('Événements Super PDP')->columnSpanFull()->schema([
                RepeatableEntry::make('events')->label('Journal de réception')->schema([
                    TextEntry::make('status_code')->label('Code')->badge()->placeholder('—'),
                    TextEntry::make('status_text')->label('Statut')->placeholder('—'),
                    TextEntry::make('occurred_at')->label('Date')->dateTime('d/m/Y H:i')->placeholder('—'),
                ])->columns(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('issued_on', 'desc')->columns([
            TextColumn::make('reference')->label('Référence')->searchable()->sortable()->placeholder('—'),
            TextColumn::make('supplier_name')->label('Fournisseur')->searchable()->sortable()->placeholder('—'),
            TextColumn::make('issued_on')->label('Émise le')->date('d/m/Y')->sortable()->placeholder('—'),
            TextColumn::make('status')->label('Statut Super PDP')->badge()->placeholder('—'),
            TextColumn::make('synchronized_at')->label('Synchronisée le')->dateTime('d/m/Y H:i')->sortable(),
        ])->recordActions([ViewAction::make()])
            ->recordUrl(fn (IncomingInvoice $record): string => self::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncomingInvoices::route('/'),
            'view' => ViewIncomingInvoice::route('/{record}'),
        ];
    }
}

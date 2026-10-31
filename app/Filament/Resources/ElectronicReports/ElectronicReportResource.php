<?php

namespace App\Filament\Resources\ElectronicReports;

use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\ElectronicReports\Pages\ListElectronicReports;
use App\Filament\Resources\ElectronicReports\Pages\ViewElectronicReport;
use App\Models\ElectronicReport;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ElectronicReportResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = ElectronicReport::class;

    protected static string $organizationModule = 'invoicing';

    protected static ?string $presentationGroupKey = 'commercial_activity';

    protected static ?string $presentationKey = 'invoicing';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Activité commerciale';

    protected static ?string $navigationLabel = 'E-reporting';

    protected static ?string $modelLabel = 'opération e-reporting';

    protected static ?string $pluralModelLabel = 'opérations e-reporting';

    protected static ?int $navigationSort = 72;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Opération préparée')->columnSpan(2)->columns(2)->schema([
                TextEntry::make('invoice.reference')->label('Facture')->placeholder('—'),
                TextEntry::make('kind')->label('Nature')->formatStateUsing(fn (string $state): string => $state === 'payment' ? 'Encaissement' : 'Transaction'),
                TextEntry::make('scope')->label('Périmètre')->badge()->formatStateUsing(fn (string $state): string => $state === 'b2c' ? 'B2C' : 'B2B international'),
                TextEntry::make('occurred_on')->label('Date')->date('d/m/Y'),
                TextEntry::make('amount')->label('Montant')->money(fn (ElectronicReport $record): string => $record->currency),
                TextEntry::make('status')->label('État')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending_review' => 'À contrôler', 'previewed' => 'Aperçu contrôlé', default => $state,
                }),
            ]),
            Section::make('Transmission')->columnSpan(1)->schema([
                TextEntry::make('remote_id')->label('Identifiant Super PDP')->placeholder('Non transmis'),
                TextEntry::make('submitted_at')->label('Transmise le')->dateTime('d/m/Y H:i')->placeholder('Non transmise'),
                TextEntry::make('acknowledged_at')->label('Accusé le')->dateTime('d/m/Y H:i')->placeholder('Aucun accusé'),
                TextEntry::make('last_error')->label('Erreur')->placeholder('—'),
            ]),
            Section::make('Données conservées')->columnSpanFull()->schema([
                TextEntry::make('payload')->label('Instantané source')->formatStateUsing(fn (array $state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '—')->fontFamily('mono')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('occurred_on', 'desc')->columns([
            TextColumn::make('invoice.reference')->label('Facture')->searchable()->sortable()->placeholder('—'),
            TextColumn::make('kind')->label('Nature')->formatStateUsing(fn (string $state): string => $state === 'payment' ? 'Encaissement' : 'Transaction'),
            TextColumn::make('scope')->label('Périmètre')->badge()->formatStateUsing(fn (string $state): string => $state === 'b2c' ? 'B2C' : 'B2B international'),
            TextColumn::make('amount')->label('Montant')->money(fn (ElectronicReport $record): string => $record->currency),
            TextColumn::make('occurred_on')->label('Date')->date('d/m/Y')->sortable(),
            TextColumn::make('status')->label('État')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                'pending_review' => 'À contrôler', 'previewed' => 'Aperçu contrôlé', default => $state,
            }),
        ])->filters([
            SelectFilter::make('status')->options(['pending_review' => 'À contrôler', 'previewed' => 'Aperçu contrôlé', 'submitted' => 'Transmise', 'acknowledged' => 'Accusée', 'rejected' => 'Rejetée']),
            SelectFilter::make('scope')->options(['b2c' => 'B2C', 'b2b_international' => 'B2B international']),
        ])->recordActions([ViewAction::make()])
            ->recordUrl(fn (ElectronicReport $record): string => self::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListElectronicReports::route('/'),
            'view' => ViewElectronicReport::route('/{record}'),
        ];
    }
}

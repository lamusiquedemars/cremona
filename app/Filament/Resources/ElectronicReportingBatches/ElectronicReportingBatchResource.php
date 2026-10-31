<?php

namespace App\Filament\Resources\ElectronicReportingBatches;

use App\Filament\Concerns\UsesOrganizationPresentation;
use App\Filament\Resources\ElectronicReportingBatches\Pages\ListElectronicReportingBatches;
use App\Models\ElectronicReportingBatch;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ElectronicReportingBatchResource extends Resource
{
    use UsesOrganizationPresentation;

    protected static ?string $model = ElectronicReportingBatch::class;

    protected static string $organizationModule = 'invoicing';

    protected static ?string $presentationGroupKey = 'commercial_activity';

    protected static ?string $presentationKey = 'invoicing';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Activité commerciale';

    protected static ?string $navigationLabel = 'Accusés e-reporting';

    protected static ?string $pluralModelLabel = 'accusés e-reporting';

    protected static ?int $navigationSort = 73;

    public static function table(Table $table): Table
    {
        return $table->defaultSort('end_period', 'desc')->columns([
            TextColumn::make('kind')->label('Nature')->badge(),
            TextColumn::make('role_code')->label('Rôle')->badge(),
            TextColumn::make('start_period')->label('Début')->date('d/m/Y')->placeholder('—'),
            TextColumn::make('end_period')->label('Fin')->date('d/m/Y')->placeholder('—'),
            TextColumn::make('events')->label('Événements')->formatStateUsing(fn (?array $state): string => (string) count($state ?? [])),
            TextColumn::make('synchronized_at')->label('Relevé le')->dateTime('d/m/Y H:i')->placeholder('—'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListElectronicReportingBatches::route('/')];
    }
}

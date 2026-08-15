<?php

namespace App\Filament\Resources\People\RelationManagers;

use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Company;
use App\Tenancy\OrganizationContext;
use Filament\Actions\AttachAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompaniesRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'companies';

    protected static ?string $title = 'Entreprises';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedBuildingOffice2;

    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('update', $this->getOwnerRecord());
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Entreprise')
                    ->description(fn ($record): ?string => $record->legal_name)
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('pivot.job_title')->label('Fonction')->placeholder('—'),
                IconColumn::make('pivot.is_primary')->label('Principale')->boolean(),
                TextColumn::make('industry')->label('Secteur')->placeholder('—'),
                TextColumn::make('website')->label('Site')->url(fn (?string $state): ?string => $state)->openUrlInNewTab(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Rattacher une entreprise')
                    ->modalHeading('Rattacher une entreprise existante')
                    ->modalSubmitActionLabel('Rattacher')
                    ->attachAnother(false)
                    ->recordSelectSearchColumns(['name', 'legal_name'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->label('Entreprise'),
                        TextInput::make('job_title')
                            ->label('Fonction')
                            ->maxLength(255),
                        Toggle::make('is_primary')
                            ->label('Entreprise principale'),
                    ])
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'organization_id' => app(OrganizationContext::class)->require()->getKey(),
                    ])
                    ->after(function (Company $record): void {
                        $recordedAt = now();
                        $record->update(['last_activity_at' => $recordedAt]);
                        $this->getOwnerRecord()->update(['last_activity_at' => $recordedAt]);
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Company $record): string => CompanyResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Company $record): string => CompanyResource::getUrl('view', ['record' => $record]));
    }
}

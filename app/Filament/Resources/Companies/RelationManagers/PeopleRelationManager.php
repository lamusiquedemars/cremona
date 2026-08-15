<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Filament\Resources\People\PersonResource;
use App\Models\Person;
use App\Tenancy\OrganizationContext;
use BackedEnum;
use Filament\Actions\AttachAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PeopleRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'people';

    protected static ?string $title = 'Contacts';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedUsers;

    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('update', $this->getOwnerRecord());
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('display_name')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Contact')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('pivot.job_title')->label('Fonction')->placeholder('—'),
                IconColumn::make('pivot.is_primary')->label('Principal')->boolean(),
                TextColumn::make('contactMethods.value')
                    ->label('Coordonnées')
                    ->listWithLineBreaks()
                    ->limitList(2),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Rattacher un contact')
                    ->modalHeading('Rattacher un contact existant')
                    ->modalSubmitActionLabel('Rattacher')
                    ->attachAnother(false)
                    ->recordSelectSearchColumns(['display_name', 'first_name', 'last_name'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->label('Contact'),
                        TextInput::make('job_title')
                            ->label('Fonction')
                            ->maxLength(255),
                        Toggle::make('is_primary')
                            ->label('Contact principal'),
                    ])
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'organization_id' => app(OrganizationContext::class)->require()->getKey(),
                    ])
                    ->after(function (Person $record): void {
                        $recordedAt = now();
                        $record->update(['last_activity_at' => $recordedAt]);
                        $this->getOwnerRecord()->update(['last_activity_at' => $recordedAt]);
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Person $record): string => PersonResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Person $record): string => PersonResource::getUrl('view', ['record' => $record]));
    }
}

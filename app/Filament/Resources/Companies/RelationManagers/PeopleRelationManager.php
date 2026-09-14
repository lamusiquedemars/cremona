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

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('common.contacts');
    }

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
                    ->label(__('common.contact'))
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('pivot.job_title')->label(__('common.function'))->placeholder('—'),
                IconColumn::make('pivot.is_primary')->label(__('common.primary'))->boolean(),
                TextColumn::make('contactMethods.value')
                    ->label(__('common.contact_details'))
                    ->listWithLineBreaks()
                    ->limitList(2),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label(__('common.attach_contact'))
                    ->modalHeading(__('common.attach_existing_contact'))
                    ->modalSubmitActionLabel(__('common.attach'))
                    ->attachAnother(false)
                    ->recordSelectSearchColumns(['display_name', 'first_name', 'last_name'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->label(__('common.contact')),
                        TextInput::make('job_title')
                            ->label(__('common.function'))
                            ->maxLength(255),
                        Toggle::make('is_primary')
                            ->label(__('common.primary_contact')),
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

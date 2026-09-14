<?php

namespace App\Filament\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'notes';

    protected static ?string $title = 'Notes';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedChatBubbleBottomCenterText;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('body')
                    ->label(__('cremona.request.internal_note'))
                    ->wrap(),
                TextColumn::make('author.name')
                    ->label(__('cremona.request.by')),
                TextColumn::make('created_at')
                    ->label(__('cremona.request.added_on'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('cremona.request.add_note'))
                    ->modalHeading(__('cremona.request.add_internal_note'))
                    ->modalSubmitActionLabel(__('cremona.request.add'))
                    ->createAnother(false)
                    ->schema([
                        Textarea::make('body')
                            ->label(__('cremona.request.internal_note'))
                            ->required()
                            ->rows(5)
                            ->maxLength(5000),
                    ])
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'author_user_id' => auth()->id(),
                    ]),
            ])
            ->recordAction(null)
            ->recordUrl(null);
    }
}

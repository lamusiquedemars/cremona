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
                    ->label('Note')
                    ->wrap(),
                TextColumn::make('author.name')
                    ->label('Par'),
                TextColumn::make('created_at')
                    ->label('Ajoutée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Ajouter une note')
                    ->modalHeading('Ajouter une note interne')
                    ->modalSubmitActionLabel('Ajouter')
                    ->createAnother(false)
                    ->schema([
                        Textarea::make('body')
                            ->label('Note')
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

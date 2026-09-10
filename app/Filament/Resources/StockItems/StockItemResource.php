<?php

namespace App\Filament\Resources\StockItems;

use App\Enums\StockMovementType;
use App\Filament\Concerns\UsesOrganizationModule;
use App\Filament\Resources\StockItems\Pages\CreateStockItem;
use App\Filament\Resources\StockItems\Pages\EditStockItem;
use App\Filament\Resources\StockItems\Pages\ListStockItems;
use App\Models\StockItem;
use App\Services\StockManager;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockItemResource extends Resource
{
    use UsesOrganizationModule;

    protected static ?string $model = StockItem::class;

    protected static string $organizationModule = 'inventory';

    protected static ?string $navigationLabel = 'Articles de stock';

    protected static ?string $modelLabel = 'article de stock';

    protected static ?string $pluralModelLabel = 'articles de stock';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration de l’organisation';

    protected static ?int $navigationSort = 75;

    public static function form(Schema $s): Schema
    {
        return $s->columns(2)->components([TextInput::make('name')->label('Intitulé')->required(), TextInput::make('sku')->label('Référence interne'), TextInput::make('quantity_on_hand')->label('Quantité disponible')->numeric()->default(0), TextInput::make('reorder_level')->label('Alerte sous')->numeric()->default(0), TextInput::make('suggested_unit_amount')->label('Prix HT indicatif')->numeric()->prefix('€')->default(0)]);
    }

    public static function table(Table $t): Table
    {
        return $t->columns([
            TextColumn::make('name')->label('Article')->searchable(),
            TextColumn::make('quantity_on_hand')->label('Disponible')->color(fn (StockItem $record): string => $record->quantity_on_hand <= $record->reorder_level ? 'danger' : 'success'),
            TextColumn::make('reorder_level')->label('Alerte sous'),
            TextColumn::make('suggested_unit_amount')->label('Prix HT')->money('EUR'),
            TextColumn::make('latestMovement.occurred_at')->label('Dernier mouvement')->since()->placeholder('—')->toggleable(),
        ])->recordActions([
            Action::make('recordMovement')
                ->label('Mouvement')
                ->schema([
                    Select::make('type')->label('Nature')->options(StockMovementType::class)->default(StockMovementType::Receipt->value)->required(),
                    TextInput::make('quantity')->label('Quantité')->numeric()->required()->helperText('Positive, sauf pour une correction d’inventaire qui peut être négative.'),
                    Textarea::make('note')->label('Note')->rows(2),
                ])
                ->action(function (StockItem $record, array $data): void {
                    try {
                        app(StockManager::class)->record($record, StockMovementType::from($data['type']), (float) $data['quantity'], $data['note'] ?? null);
                    } catch (\LogicException $exception) {
                        Notification::make()->title('Mouvement non enregistré')->body($exception->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Mouvement de stock enregistré')->success()->send();
                }),
            EditAction::make(),
        ])->headerActions([CreateAction::make()->label('Nouvel article')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockItems::route('/'), 'create' => CreateStockItem::route('/create'), 'edit' => EditStockItem::route('/{record}/edit')];
    }
}

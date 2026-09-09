<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\WorkshopOrder;
use Illuminate\Support\Facades\DB;
use LogicException;

class StockManager
{
    public function record(StockItem $item, StockMovementType $type, float $quantity, ?string $note = null, ?WorkshopOrder $order = null): StockMovement
    {
        return DB::transaction(function () use ($item, $type, $quantity, $note, $order): StockMovement {
            $item = StockItem::query()->lockForUpdate()->findOrFail($item->getKey());
            $change = $this->changeFor($type, $quantity);
            $after = round((float) $item->quantity_on_hand + $change, 2);

            if ($after < 0) {
                throw new LogicException("Le mouvement laisserait « {$item->name} » avec un stock négatif.");
            }

            $item->update(['quantity_on_hand' => $after]);

            return StockMovement::query()->create([
                'stock_item_id' => $item->getKey(),
                'workshop_order_id' => $order?->getKey(),
                'type' => $type,
                'quantity_change' => $change,
                'quantity_after' => $after,
                'occurred_at' => now(),
                'note' => $note,
            ]);
        });
    }

    public function applyWorkshopConsumption(WorkshopOrder $order): int
    {
        return DB::transaction(function () use ($order): int {
            $lines = $order->stockItems()->whereNull('applied_at')->get();

            foreach ($lines as $line) {
                $item = $line->stockItem;
                if ($item === null) {
                    throw new LogicException('Un article prévu pour ce dossier atelier n’existe plus.');
                }
                $this->record($item, StockMovementType::Consumption, (float) $line->quantity, "Consommé pour le dossier {$order->reference}", $order);
                $line->update(['applied_at' => now()]);
            }

            return $lines->count();
        });
    }

    private function changeFor(StockMovementType $type, float $quantity): float
    {
        if ($type === StockMovementType::Adjustment) {
            if ($quantity == 0.0) {
                throw new LogicException('Une correction d’inventaire doit modifier la quantité.');
            }

            return $quantity;
        }

        if ($quantity <= 0) {
            throw new LogicException('La quantité doit être supérieure à zéro.');
        }

        return match ($type) {
            StockMovementType::Receipt, StockMovementType::Return => $quantity,
            StockMovementType::Consumption, StockMovementType::Sale => -$quantity,
            StockMovementType::Adjustment => $quantity,
        };
    }
}

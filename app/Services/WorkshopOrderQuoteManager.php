<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\WorkshopOrder;
use Illuminate\Support\Facades\DB;
use LogicException;

class WorkshopOrderQuoteManager
{
    public function sync(WorkshopOrder $order): Quote
    {
        return DB::transaction(function () use ($order): Quote {
            $quote = Quote::query()->firstOrCreate(['workshop_order_id' => $order->id], ['title' => 'Intervention atelier — '.$order->title, 'person_id' => $order->person_id, 'incoming_request_id' => $order->incoming_request_id, 'status' => QuoteStatus::Draft]);

            if ($quote->status !== QuoteStatus::Draft) {
                throw new LogicException('Ce devis a déjà été envoyé ou traité ; ses lignes atelier ne peuvent plus être modifiées automatiquement.');
            }

            foreach ($order->services()->where('include_in_quote', true)->get() as $service) {
                $quote->lines()->firstOrCreate(['workshop_order_service_id' => $service->id], ['kind' => 'service', 'description' => $service->description_snapshot ?: $service->label_snapshot, 'quantity' => $service->quantity, 'unit_amount' => $service->unit_amount]);
            }

            foreach ($order->stockItems()->where('include_in_quote', true)->get() as $item) {
                $quote->lines()->firstOrCreate(['workshop_order_stock_item_id' => $item->id], ['kind' => 'product', 'description' => $item->label_snapshot, 'quantity' => $item->quantity, 'unit_amount' => $item->unit_amount]);
            }

            return $quote->refresh();
        });
    }
}

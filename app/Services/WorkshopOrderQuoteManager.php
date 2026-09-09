<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\WorkshopOrder;
use Illuminate\Support\Facades\DB;

class WorkshopOrderQuoteManager
{
    public function sync(WorkshopOrder $order): Quote
    {
        return DB::transaction(function () use ($order): Quote {
            $quote = Quote::query()->firstOrCreate(['workshop_order_id' => $order->id], ['title' => 'Intervention atelier — '.$order->title, 'person_id' => $order->person_id, 'incoming_request_id' => $order->incoming_request_id]);
            foreach ($order->services()->where('include_in_quote', true)->get() as $service) {
                $quote->lines()->firstOrCreate(['workshop_order_service_id' => $service->id], ['kind' => 'service', 'description' => $service->description_snapshot ?: $service->label_snapshot, 'quantity' => $service->quantity, 'unit_amount' => $service->unit_amount]);
            }

            return $quote->refresh();
        });
    }
}

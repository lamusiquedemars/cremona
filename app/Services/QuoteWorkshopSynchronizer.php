<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Enums\WorkshopOrderStatus;
use App\Models\Quote;
use App\Models\WorkshopOrder;

class QuoteWorkshopSynchronizer
{
    public function sync(Quote $quote): void
    {
        if ($quote->workshop_order_id === null) {
            return;
        }

        $order = WorkshopOrder::query()
            ->where('organization_id', $quote->organization_id)
            ->whereKey($quote->workshop_order_id);

        // Fill an empty contact without replacing an explicitly assigned customer.
        if ($quote->person_id !== null) {
            (clone $order)->where(function ($query) use ($quote): void {
                $query->whereNull('person_id');
                if ($quote->status === QuoteStatus::Draft && $quote->wasChanged('person_id') && $quote->getOriginal('person_id') !== null) {
                    $query->orWhere('person_id', $quote->getOriginal('person_id'));
                }
            })->update(['person_id' => $quote->person_id]);
        }

        if ($quote->status === QuoteStatus::Accepted) {
            (clone $order)
                ->whereIn('status', [WorkshopOrderStatus::Diagnosed, WorkshopOrderStatus::AwaitingApproval])
                ->update([
                    'status' => WorkshopOrderStatus::Approved,
                    'authorized_at' => $quote->accepted_at ?? now(),
                ]);
        }
    }

    public function syncFromOrder(WorkshopOrder $order): void
    {
        if ($order->person_id === null) {
            return;
        }

        $order->quote()->where('organization_id', $order->organization_id)
            ->where('status', QuoteStatus::Draft)
            ->where(function ($query) use ($order): void {
                $query->whereNull('person_id');
                if ($order->wasChanged('person_id') && $order->getOriginal('person_id') !== null) {
                    $query->orWhere('person_id', $order->getOriginal('person_id'));
                }
            })->update(['person_id' => $order->person_id]);
    }
}

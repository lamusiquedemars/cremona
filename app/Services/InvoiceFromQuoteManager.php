<?php

namespace App\Services;

use App\Enums\WorkshopOrderStatus;
use App\Models\Invoice;
use App\Models\OrganizationInvoiceSettings;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Support\Facades\DB;
use LogicException;

class InvoiceFromQuoteManager
{
    public function createDraft(Quote $quote): Invoice
    {
        return DB::transaction(function () use ($quote): Invoice {
            $quote = Quote::query()->with('lines')->lockForUpdate()->findOrFail($quote->id);

            if ($quote->status->value !== 'accepted') {
                throw new LogicException('Seul un devis accepté peut être transformé en facture.');
            }

            $existing = Invoice::query()->where('quote_id', $quote->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            $order = $quote->workshopOrder;
            if ($quote->workshop_order_id !== null && ! in_array($order?->status, [WorkshopOrderStatus::Ready, WorkshopOrderStatus::Returned], true)) {
                throw new LogicException('Déclarez le travail réalisé dans le dossier atelier avant de préparer la facture.');
            }

            if ($quote->lines->isEmpty()) {
                throw new LogicException('Ce devis ne contient aucune ligne à facturer.');
            }

            $settings = OrganizationInvoiceSettings::query()->first();
            $invoice = Invoice::query()->create([
                'quote_id' => $quote->id,
                'workshop_order_id' => $quote->workshop_order_id,
                'person_id' => $quote->person_id,
                'company_id' => $quote->company_id,
                'title' => $quote->title,
                'introduction' => $quote->introduction,
                'currency' => $quote->currency,
                'supply_on' => $order === null
                    ? $quote->issued_on
                    : $order->ready_at?->setTimezone($order->organization->timezone())->toDateString(),
                'payment_terms' => $quote->payment_terms,
            ]);

            $subtotalInCents = $quote->lines->sum(fn (QuoteLine $line): int => max(0, (int) round((float) $line->total_amount * 100)));
            $discountInCents = $order === null ? 0 : min($subtotalInCents, max(0, (int) round((float) $quote->discount_amount * 100)));
            $processedSubtotal = 0;
            $processedDiscount = 0;

            foreach ($quote->lines as $line) {
                $processedSubtotal += max(0, (int) round((float) $line->total_amount * 100));
                $allocatedDiscount = $discountInCents === 0 ? 0 : (int) round($discountInCents * $processedSubtotal / $subtotalInCents);
                $invoice->lines()->create([
                    'position' => $line->position,
                    'source_type' => $line->getMorphClass(),
                    'source_id' => $line->id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_amount' => $line->unit_amount,
                    'discount_amount' => ($allocatedDiscount - $processedDiscount) / 100,
                    'tax_rate' => $settings?->default_tax_rate ?? 20,
                ]);
                $processedDiscount = $allocatedDiscount;
            }

            return $invoice->fresh('lines');
        });
    }
}

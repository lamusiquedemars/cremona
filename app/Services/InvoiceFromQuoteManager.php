<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\OrganizationInvoiceSettings;
use App\Models\Quote;
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
                'supply_on' => $quote->issued_on,
                'payment_terms' => $quote->payment_terms,
            ]);

            foreach ($quote->lines as $line) {
                $invoice->lines()->create([
                    'position' => $line->position,
                    'source_type' => $line->getMorphClass(),
                    'source_id' => $line->id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_amount' => $line->unit_amount,
                    'tax_rate' => $settings?->default_tax_rate ?? 20,
                ]);
            }

            return $invoice->fresh('lines');
        });
    }
}

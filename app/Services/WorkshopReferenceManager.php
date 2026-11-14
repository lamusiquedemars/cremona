<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Quote;
use App\Models\WorkshopOrder;
use Illuminate\Support\Facades\DB;
use LogicException;

class WorkshopReferenceManager
{
    public function forQuote(Quote $quote): string
    {
        return DB::transaction(function () use ($quote): string {
            Organization::query()->whereKey($quote->organization_id)->lockForUpdate()->firstOrFail();
            if ($quote->workshop_order_id === null) {
                return app(QuoteNumberGenerator::class)->next((int) $quote->organization_id, $quote->created_at);
            }

            $order = WorkshopOrder::query()->where('organization_id', $quote->organization_id)
                ->lockForUpdate()->findOrFail($quote->workshop_order_id);
            if ($order->quote()->where('id', '!=', $quote->id ?? 0)->exists()) {
                throw new LogicException('Ce dossier atelier possède déjà un devis. Ouvrez le devis lié.');
            }

            if (preg_match('/^AT[0-9]{6}$/', $order->reference)) {
                return 'D'.substr($order->reference, 2);
            }

            $reference = app(QuoteNumberGenerator::class)->next((int) $quote->organization_id, $order->created_at);
            $order->forceFill([
                'previous_reference' => $order->previous_reference ?? $order->reference,
                'reference' => 'AT'.substr($reference, 1),
            ])->saveQuietly();

            return $reference;
        });
    }

    public function reconcile(): void
    {
        DB::transaction(function (): void {
            // Refuse ambiguous historical links instead of silently choosing a quote.
            if (Quote::query()->whereNotNull('workshop_order_id')->groupBy('workshop_order_id')
                ->havingRaw('COUNT(*) > 1')->select('workshop_order_id')->first() !== null) {
                throw new LogicException('Plusieurs devis partagent un dossier atelier : résoudre ces liens avant la reprise des références.');
            }

            Quote::query()->orderBy('id')->each(function (Quote $quote): void {
                $order = $quote->workshopOrder;
                $reference = $quote->reference;
                if (str_starts_with($reference, 'Q-')) {
                    $reference = $this->forQuote($quote);
                    $quote->forceFill([
                        'previous_reference' => $quote->previous_reference ?? $quote->reference,
                        'reference' => $reference,
                    ])->saveQuietly();
                }
                if ($order !== null && preg_match('/^D[0-9]{6}$/', $reference)) {
                    $target = 'AT'.substr($reference, 1);
                    if ($order->reference !== $target) {
                        $order->forceFill([
                            'previous_reference' => $order->previous_reference ?? $order->reference,
                            'reference' => $target,
                        ])->saveQuietly();
                    }
                }
            });

            WorkshopOrder::query()->where('reference', 'like', 'AT-%')->doesntHave('quote')->each(function (WorkshopOrder $order): void {
                $reference = app(QuoteNumberGenerator::class)->next((int) $order->organization_id, $order->created_at);
                $order->forceFill([
                    'previous_reference' => $order->previous_reference ?? $order->reference,
                    'reference' => 'AT'.substr($reference, 1),
                ])->saveQuietly();
            });
        });
    }
}

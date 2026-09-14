<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use LogicException;

class QuoteWorkflowManager
{
    public function markSent(Quote $quote, string $via): Quote
    {
        return DB::transaction(function () use ($quote, $via): Quote {
            $quote = Quote::query()->lockForUpdate()->with('workshopOrder')->findOrFail($quote->id);

            if ($quote->status !== QuoteStatus::Draft) {
                throw new LogicException('Seul un devis brouillon peut être marqué comme envoyé.');
            }
            if (! $quote->lines()->exists()) {
                throw new LogicException('Ajoutez au moins une ligne avant l’envoi du devis.');
            }

            $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now(), 'sent_via' => $via]);

            if ($quote->workshopOrder !== null) {
                app(WorkshopOrderWorkflowManager::class)->markAwaitingApproval($quote->workshopOrder);
            }

            return $quote->fresh();
        });
    }

    public function markAccepted(Quote $quote): Quote
    {
        return $this->transition($quote, QuoteStatus::Sent, QuoteStatus::Accepted);
    }

    public function markDeclined(Quote $quote): Quote
    {
        return $this->transition($quote, QuoteStatus::Sent, QuoteStatus::Declined);
    }

    private function transition(Quote $quote, QuoteStatus $from, QuoteStatus $to): Quote
    {
        return DB::transaction(function () use ($quote, $from, $to): Quote {
            $quote = Quote::query()->lockForUpdate()->findOrFail($quote->id);

            if ($quote->status !== $from) {
                throw new LogicException("Cette action est disponible uniquement lorsque le devis est « {$from->getLabel()} ».");
            }

            $quote->update(['status' => $to]);

            return $quote->fresh();
        });
    }
}

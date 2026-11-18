<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Mail\QuoteSent;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Throwable;

class QuoteWorkflowManager
{
    public function markSent(Quote $quote, string $via): Quote
    {
        return DB::transaction(function () use ($quote, $via): Quote {
            $quote = Quote::query()->lockForUpdate()->with(['organization.legalProfile', 'workshopOrder', 'person.contactMethods', 'company.contactMethods'])->findOrFail($quote->id);

            return $this->markAsSent($quote, $via);
        });
    }

    public function sendByEmail(Quote $quote, string $recipientEmail): Quote
    {
        $recipientEmail = trim($recipientEmail);

        if (! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new LogicException('Saisissez une adresse e-mail valide pour envoyer le devis.');
        }

        return DB::transaction(function () use ($quote, $recipientEmail): Quote {
            $quote = Quote::query()->lockForUpdate()->with(['organization.legalProfile', 'workshopOrder', 'person.contactMethods', 'company.contactMethods'])->findOrFail($quote->id);
            $this->assertCanBeSent($quote);

            $pdf = app(QuotePdfRenderer::class)->render($quote);
            try {
                Mail::to($recipientEmail)->send(new QuoteSent($quote, $pdf));
            } catch (Throwable $exception) {
                throw new LogicException('Le devis n’a pas pu être envoyé par e-mail. Vérifiez la configuration d’envoi et réessayez.', 0, $exception);
            }

            return $this->markAsSent($quote, 'email');
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

            $quote->load('workshopOrder');
            $quote->update(['status' => $to]);

            return $quote->fresh();
        });
    }

    private function markAsSent(Quote $quote, string $via): Quote
    {
        $this->assertCanBeSent($quote);

        $profiles = app(QuoteDocumentProfileManager::class);
        $issuer = $profiles->assertIssuerIsReady($quote->organization);
        $recipient = $profiles->recipientSnapshot($quote);

        $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now(), 'sent_via' => $via, 'issuer_snapshot' => $issuer->snapshot(), 'recipient_snapshot' => $recipient]);

        if ($quote->workshopOrder !== null) {
            app(WorkshopOrderWorkflowManager::class)->markAwaitingApproval($quote->workshopOrder);
        }

        return $quote->fresh();
    }

    private function assertCanBeSent(Quote $quote): void
    {
        if ($quote->status !== QuoteStatus::Draft) {
            throw new LogicException('Seul un devis brouillon peut être marqué comme envoyé.');
        }
        if (! $quote->lines()->exists()) {
            throw new LogicException('Ajoutez au moins une ligne avant l’envoi du devis.');
        }
    }
}

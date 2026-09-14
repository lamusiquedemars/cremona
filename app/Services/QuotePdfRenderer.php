<?php

namespace App\Services;

use App\Models\OrganizationQuoteSettings;
use App\Models\Quote;
use Dompdf\Dompdf;
use Dompdf\Options;
use LogicException;

class QuotePdfRenderer
{
    public function render(Quote $quote): string
    {
        $quote->loadMissing(['organization', 'person.contactMethods', 'company.contactMethods', 'lines']);
        $profiles = app(QuoteDocumentProfileManager::class);
        $issuer = $quote->issuer_snapshot ?: $profiles->assertIssuerIsReady($quote->organization)->snapshot();
        $recipient = $quote->recipient_snapshot ?: $profiles->recipientSnapshot($quote);

        if (blank($recipient['name'] ?? null)) {
            throw new LogicException('Le destinataire du devis est incomplet.');
        }

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $quoteSettings = OrganizationQuoteSettings::withoutGlobalScopes()->where('organization_id', $quote->organization_id)->first();
        $pdf->loadHtml(view('quotes.pdf', compact('quote', 'issuer', 'recipient', 'quoteSettings'))->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}

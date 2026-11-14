<?php

namespace App\Services;

use App\Models\Invoice;
use App\Tenancy\OrganizationContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use LogicException;

class InvoicePdfRenderer
{
    public function render(Invoice $invoice): string
    {
        return app(OrganizationContext::class)->run($invoice->organization, fn (): string => $this->renderForOrganization($invoice));
    }

    private function renderForOrganization(Invoice $invoice): string
    {
        $invoice->loadMissing('lines');
        if ($invoice->issued_at === null || $invoice->reference === null || $invoice->issuer_snapshot === null || $invoice->recipient_snapshot === null) {
            throw new LogicException('Émettez la facture avant de générer son PDF.');
        }
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        // Les caractères français sont couverts par les polices PDF de base ;
        // éviter une police TrueType complète réduit fortement l'empreinte mémoire
        // lors des émissions en série.
        $options->set('defaultFont', 'Helvetica');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('invoices.pdf', ['invoice' => $invoice, 'issuer' => $invoice->issuer_snapshot, 'recipient' => $invoice->recipient_snapshot])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}

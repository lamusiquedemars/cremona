<?php

namespace App\Services;

use App\Enums\RentalDocumentType;
use Dompdf\Dompdf;
use Dompdf\Options;

class RentalContractRenderer
{
    /** @param array<string, mixed> $snapshot */
    public function render(RentalDocumentType $type, array $snapshot): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('rentals.contract-pdf', compact('type', 'snapshot'))->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}

<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceTransmission;
use LogicException;

class ElectronicInvoiceFinalizationManager
{
    /** @return array{transmission: InvoiceTransmission, transmitted: bool} */
    public function finalizeAndTransmit(Invoice $invoice): array
    {
        // Check the connection before giving a draft its irreversible number.
        app(SuperPdpConnectionResolver::class)->credentials($invoice);
        if ($invoice->company_id !== null && blank(Company::withoutGlobalScopes()->find($invoice->company_id)?->registration_number)) {
            throw new LogicException('Renseignez le SIREN ou SIRET du client professionnel avant la finalisation.');
        }

        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice = app(InvoiceWorkflowManager::class)->issue($invoice);
        }

        $transmission = $invoice->transmissions()->where('provider', 'super_pdp')->latest()->first();
        if ($transmission === null) {
            throw new LogicException('Cette facture ne comporte pas de circuit de facturation électronique.');
        }

        if (in_array($transmission->status, ['submitted', 'sent'], true)) {
            return ['transmission' => $transmission, 'transmitted' => true];
        }
        if ($transmission->status === 'submission_unknown') {
            throw new LogicException('Le résultat de transmission est inconnu. Vérifiez d’abord la transmission existante.');
        }
        if (in_array($transmission->status, ['rejected', 'refused', 'invalid'], true)) {
            throw new LogicException('Cette facture doit être corrigée par un avoir ou un nouveau document avant toute nouvelle transmission.');
        }

        if ($transmission->status !== 'validated') {
            $validation = app(InvoiceElectronicDocumentManager::class)->generateAndValidate($invoice);
            if (! $validation['valid']) {
                $transmission = $invoice->transmissions()->where('provider', 'super_pdp')->latest()->firstOrFail();

                return ['transmission' => $transmission, 'transmitted' => false];
            }
        }

        return [
            'transmission' => app(SuperPdpTransmissionManager::class)->submit($invoice),
            'transmitted' => true,
        ];
    }
}

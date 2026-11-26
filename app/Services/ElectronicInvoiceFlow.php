<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;

class ElectronicInvoiceFlow
{
    public function isB2c(Invoice $invoice): bool
    {
        return $invoice->company_id === null;
    }

    public function processingRule(Invoice $invoice): string
    {
        if ($this->isB2c($invoice)) {
            return 'B2C';
        }

        return $this->recipientCountry($invoice) === 'FR' ? 'B2B' : 'B2BInt';
    }

    public function actionLabel(Invoice $invoice): string
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return $this->isB2c($invoice) ? 'Reprendre le traitement e-reporting' : 'Reprendre la transmission';
        }

        return $this->isB2c($invoice) ? 'Finaliser la facture' : 'Finaliser et transmettre';
    }

    public function confirmationDescription(Invoice $invoice): string
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return 'La facture est déjà finalisée et son numéro est définitif. Cette action reprend uniquement le traitement technique en attente ; elle ne créera pas de nouvelle facture ni de nouveau numéro.';
        }

        return $this->isB2c($invoice)
            ? 'La facture recevra son numéro définitif. Ses données de transaction seront ensuite confiées à la plateforme pour l’e-reporting ; le PDF reste à remettre au client par le canal de votre choix.'
            : 'La facture recevra son numéro définitif, puis sera contrôlée et remise au circuit de facturation électronique. Elle ne pourra plus être modifiée après sa finalisation.';
    }

    public function submissionMessage(Invoice $invoice): string
    {
        return $this->isB2c($invoice)
            ? 'Données de transaction B2C remises à Super PDP pour l’e-reporting. Le client ne reçoit rien via ce circuit.'
            : 'Facture remise à Super PDP pour routage vers la plateforme du client professionnel.';
    }

    private function recipientCountry(Invoice $invoice): string
    {
        $country = data_get($invoice->recipient_snapshot, 'country_code');
        if (! is_string($country) || $country === '') {
            $country = Company::withoutGlobalScopes()->find($invoice->company_id)?->country_code;
        }

        return strtoupper((string) $country);
    }
}

<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\ContactMethod;
use App\Models\Invoice;
use App\Models\OrganizationInvoiceSettings;
use App\Models\OrganizationLegalProfile;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use LogicException;

class InvoiceWorkflowManager
{
    public function refreshAmounts(Invoice $invoice): void
    {
        if (! $invoice->exists) {
            return;
        }
        $invoice = Invoice::withoutGlobalScopes()->with('lines')->findOrFail($invoice->id);
        $subtotal = round((float) $invoice->lines->sum('net_amount'), 2);
        $tax = round((float) $invoice->lines->sum('tax_amount'), 2);
        $total = round((float) $invoice->lines->sum('total_amount'), 2);
        $paid = (float) $invoice->paid_amount;
        $invoice->forceFill([
            'subtotal_amount' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'balance_amount' => max(0, round($total - $paid, 2)),
        ])->saveQuietly();
    }

    public function refreshPayments(Invoice $invoice): void
    {
        if (! $invoice->exists) {
            return;
        }
        $invoice = Invoice::withoutGlobalScopes()->with('payments')->findOrFail($invoice->id);
        $paid = round((float) $invoice->payments->sum('amount'), 2);
        $balance = max(0, round((float) $invoice->total_amount - $paid, 2));
        $status = $balance <= 0.0 ? InvoiceStatus::Paid : ($paid > 0 ? InvoiceStatus::PartiallyPaid : InvoiceStatus::Issued);
        $invoice->forceFill([
            'paid_amount' => $paid,
            'balance_amount' => $balance,
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::Paid ? ($invoice->paid_at ?? now()) : null,
        ])->saveQuietly();
    }

    public function issue(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->with(['organization', 'lines', 'person.contactMethods', 'company.contactMethods'])->findOrFail($invoice->id);
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw new LogicException('Seul un brouillon peut être émis.');
            }
            if ($invoice->lines->isEmpty()) {
                throw new LogicException('Ajoutez au moins une ligne avant d’émettre la facture.');
            }

            $issuer = $this->issuerSnapshot($invoice);
            $recipient = $this->recipientSnapshot($invoice);
            $settings = OrganizationInvoiceSettings::withoutGlobalScopes()
                ->where('organization_id', $invoice->organization_id)
                ->lockForUpdate()
                ->firstOrCreate(['organization_id' => $invoice->organization_id]);
            $number = $settings->next_reference_number;
            $settings->forceFill(['next_reference_number' => $number + 1])->saveQuietly();
            $issuedOn = $invoice->issued_on ?? today();
            $dueOn = $invoice->due_on ?? ($settings->default_due_days ? $issuedOn->addDays($settings->default_due_days) : null);
            $prefix = strtoupper(trim((string) $settings->reference_prefix)) ?: 'FAC';
            $reference = $prefix.'-'.($issuedOn->format('Y')).'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);

            $this->refreshAmounts($invoice);
            $invoice->refresh();
            $invoice->forceFill([
                'reference' => $reference,
                'status' => InvoiceStatus::Issued,
                'issued_on' => $issuedOn,
                'due_on' => $dueOn,
                'payment_terms' => $invoice->payment_terms ?? $settings->default_payment_terms,
                'payment_instructions' => $invoice->payment_instructions ?? $settings->payment_instructions,
                'late_payment_terms' => $invoice->late_payment_terms ?? $settings->late_payment_terms,
                'issuer_snapshot' => $issuer,
                'recipient_snapshot' => $recipient,
                'settings_snapshot' => [
                    'vat_regime' => $settings->vat_regime,
                    'regulatory_provider' => $settings->regulatory_provider,
                    'super_pdp_enabled' => $settings->super_pdp_enabled,
                ],
                'issued_at' => now(),
            ])->save();
            app(InvoiceDocumentManager::class)->ensurePdf($invoice->fresh(['organization', 'lines']));
            $this->queueRegulatoryTransmission($invoice, $settings);

            return $invoice->fresh(['lines', 'transmissions', 'pdfPrivateDocument']);
        });
    }

    private function queueRegulatoryTransmission(Invoice $invoice, OrganizationInvoiceSettings $settings): void
    {
        $invoice = $invoice->fresh('pdfPrivateDocument');

        $invoice->transmissions()->create([
            'provider' => $settings->regulatory_provider ?: 'super_pdp',
            'direction' => 'outbound',
            'status' => $settings->super_pdp_enabled ? 'ready_to_transmit' : 'awaiting_configuration',
            'payload_sha256' => $invoice->pdfPrivateDocument?->sha256,
            'metadata' => [
                'purpose' => 'regulatory_invoice_transmission',
                'message' => $settings->super_pdp_enabled
                    ? 'La facture est prête pour le connecteur Super PDP.'
                    : 'Connectez Super PDP dans les réglages de facturation avant toute transmission réglementaire.',
            ],
        ]);
    }

    /** @return array<string, string|null> */
    private function issuerSnapshot(Invoice $invoice): array
    {
        $profile = OrganizationLegalProfile::withoutGlobalScopes()->where('organization_id', $invoice->organization_id)->first();
        if ($profile === null) {
            throw new LogicException('Complétez les coordonnées et mentions légales avant d’émettre une facture.');
        }
        foreach (['display_name', 'legal_name', 'email', 'address_line_1', 'postal_code', 'city', 'country_code', 'registration_number'] as $field) {
            if (blank($profile->{$field})) {
                throw new LogicException('Complétez « '.match ($field) { 'display_name' => 'Nom affiché', 'legal_name' => 'Raison sociale', 'registration_number' => 'SIRET ou numéro d’immatriculation', default => $field }. ' » avant d’émettre une facture.');
            }
        }

        return $profile->snapshot();
    }

    /** @return array<string, string|null> */
    private function recipientSnapshot(Invoice $invoice): array
    {
        $recipient = $invoice->company_id !== null
            ? Company::withoutGlobalScopes()->find($invoice->company_id)
            : Person::withoutGlobalScopes()->find($invoice->person_id);
        if ($recipient === null) {
            throw new LogicException('Renseignez un client avant d’émettre la facture.');
        }
        $email = ContactMethod::withoutGlobalScopes()
            ->where('organization_id', $invoice->organization_id)
            ->where('contactable_type', $recipient->getMorphClass())
            ->where('contactable_id', $recipient->getKey())
            ->where('type', 'email')->orderByDesc('is_primary')->value('value');

        return [
            'name' => $recipient instanceof Company ? ($recipient->legal_name ?: $recipient->name) : $recipient->display_name,
            'email' => $email,
            'address_line_1' => $recipient->address_line_1,
            'address_line_2' => $recipient->address_line_2,
            'postal_code' => $recipient->postal_code,
            'city' => $recipient->city,
            'country_code' => $recipient->country_code,
        ];
    }
}

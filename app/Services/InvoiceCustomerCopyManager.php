<?php

namespace App\Services;

use App\Mail\InvoiceCustomerCopySent;
use App\Models\Invoice;
use App\Models\InvoiceCustomerDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

class InvoiceCustomerCopyManager
{
    public function sendByEmail(Invoice $invoice, string $recipientEmail, ?User $actor = null): InvoiceCustomerDelivery
    {
        $recipientEmail = trim($recipientEmail);
        if (! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new LogicException('Saisissez une adresse e-mail valide pour envoyer la copie de facture.');
        }

        return DB::transaction(function () use ($invoice, $recipientEmail, $actor): InvoiceCustomerDelivery {
            $invoice = Invoice::query()->lockForUpdate()->with('pdfPrivateDocument')->findOrFail($invoice->id);
            if ($invoice->issued_at === null || $invoice->pdfPrivateDocument === null) {
                throw new LogicException('Finalisez la facture avant d’envoyer une copie au client.');
            }
            if (! app(ElectronicInvoiceFlow::class)->isB2c($invoice)) {
                throw new LogicException('La copie e-mail est réservée aux factures destinées à un particulier.');
            }

            $document = $invoice->pdfPrivateDocument;
            $pdf = Storage::disk($document->disk)->get($document->path);
            try {
                Mail::to($recipientEmail)->send(new InvoiceCustomerCopySent($invoice, $pdf));
            } catch (Throwable $exception) {
                throw new LogicException('La copie de facture n’a pas pu être envoyée par e-mail. Vérifiez la configuration d’envoi et réessayez.', 0, $exception);
            }

            return InvoiceCustomerDelivery::query()->create([
                'invoice_id' => $invoice->id,
                'sent_by_user_id' => $actor?->id,
                'recipient_email' => $recipientEmail,
                'sent_at' => now(),
            ]);
        });
    }
}

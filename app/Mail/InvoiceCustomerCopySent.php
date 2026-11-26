<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Mail\Mailable;

class InvoiceCustomerCopySent extends Mailable
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $pdf,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Votre facture '.$this->invoice->reference)
            ->view('mail.invoice-customer-copy-sent')
            ->attachData($this->pdf, 'Facture-'.$this->invoice->reference.'.pdf', ['mime' => 'application/pdf']);
    }
}

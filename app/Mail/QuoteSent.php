<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Mail\Mailable;

class QuoteSent extends Mailable
{
    public function __construct(
        public readonly Quote $quote,
        public readonly string $pdf,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Votre devis '.$this->quote->reference)
            ->view('mail.quote-sent')
            ->attachData($this->pdf, 'Devis-'.$this->quote->reference.'.pdf', ['mime' => 'application/pdf']);
    }
}

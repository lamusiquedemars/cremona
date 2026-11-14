<?php

namespace App\Mail;

use App\Models\RentalAcceptanceRequest;
use Illuminate\Mail\Mailable;

class RentalAcceptanceReceipt extends Mailable
{
    public function __construct(public readonly RentalAcceptanceRequest $acceptance) {}

    public function build(): self
    {
        return $this->subject('Votre accord a bien été enregistré')->view('mail.rental-acceptance-receipt');
    }
}

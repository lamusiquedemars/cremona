<?php

namespace App\Mail;

use App\Models\Rental;
use Illuminate\Mail\Mailable;

class RentalHandoverConfirmation extends Mailable
{
    public function __construct(public readonly Rental $rental) {}

    public function build(): self
    {
        return $this->subject('Confirmation de la remise de votre instrument')->view('mail.rental-handover-confirmation');
    }
}

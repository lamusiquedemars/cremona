<?php

namespace App\Mail;

use App\Models\Rental;
use Illuminate\Mail\Mailable;

class RentalReturnConfirmation extends Mailable
{
    public function __construct(public readonly Rental $rental) {}

    public function build(): self
    {
        return $this->subject('Confirmation de la restitution de votre instrument')->view('mail.rental-return-confirmation');
    }
}

<?php

namespace App\Mail;

use App\Models\RentalAcceptanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RentalAcceptanceInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly RentalAcceptanceRequest $request, public readonly string $url) {}

    public function build(): self
    {
        return $this->subject('Documents de location à accepter')
            ->view('mail.rental-acceptance-invitation');
    }
}

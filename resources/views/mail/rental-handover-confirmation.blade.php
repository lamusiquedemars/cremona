Bonjour {{ $rental->person?->display_name }},

La remise de l’instrument {{ $rental->instrument?->name }} a été enregistrée le {{ $rental->starts_on?->format('d/m/Y') }}.

Loyer mensuel : {{ number_format((float) $rental->unit_amount + (float) $rental->insurance_monthly_amount, 2, ',', ' ') }} €.
@if($rental->expected_return_on)
Retour prévu : {{ $rental->expected_return_on->format('d/m/Y') }}.
@endif

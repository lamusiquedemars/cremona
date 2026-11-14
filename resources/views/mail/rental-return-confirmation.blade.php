Bonjour {{ $rental->person?->display_name }},

La restitution de l’instrument {{ $rental->instrument?->name }} a été enregistrée le {{ $rental->returned_on?->format('d/m/Y') }}.

Merci. L’atelier reviendra vers vous si un ajustement est nécessaire.

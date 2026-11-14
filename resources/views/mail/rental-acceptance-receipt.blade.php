Bonjour {{ $acceptance->recipient_name }},

Votre accord pour la location {{ $acceptance->rental->reference }} a bien été enregistré le {{ $acceptance->accepted_at->setTimezone($acceptance->rental->organization->timezone())->format('d/m/Y à H:i') }}.

L’atelier vous contactera pour organiser la remise de l’instrument.

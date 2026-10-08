Bonjour {{ $request->recipient_name }},

{{ $request->rental->organization->name }} vous invite à consulter et accepter les documents liés à la location {{ $request->rental->reference }}.

Ce lien personnel est valable jusqu’au {{ $request->expires_at->format('d/m/Y à H:i') }} :
{{ $url }}

Cette acceptation est enregistrée dans Cremona. Elle ne constitue pas une signature électronique qualifiée.

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autoriser l’accès — Cremona</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        body { align-items: center; background: #f8f7f4; color: #20201f; display: flex; justify-content: center; margin: 0; min-height: 100vh; padding: 24px; }
        main { background: #fff; border: 1px solid #e7e4de; border-radius: 16px; box-shadow: 0 12px 32px rgb(34 32 29 / 8%); max-width: 520px; padding: 32px; width: 100%; }
        .eyebrow { color: #78716c; font-size: .8rem; font-weight: 700; letter-spacing: .08em; margin: 0 0 12px; text-transform: uppercase; }
        h1 { font-size: 1.5rem; line-height: 1.25; margin: 0 0 12px; }
        p { color: #57534e; line-height: 1.55; margin: 0; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
        button { border-radius: 8px; cursor: pointer; font: inherit; font-weight: 650; padding: 10px 16px; }
        .continue { background: #d97706; border: 1px solid #d97706; color: #fff; }
        .cancel { background: #fff; border: 1px solid #d6d3d1; color: #44403c; }
    </style>
</head>
<body>
    <main>
        <p class="eyebrow">Cremona</p>
        <h1>Ouvrir la gestion depuis {{ $client->name }}</h1>
        <p>
            {{ $client->name }} demande l’accès à votre espace de gestion Cremona.
            Vous pourrez revenir au site à tout moment.
        </p>

        <div class="actions">
            <form method="post" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->get('state') }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button class="continue" type="submit">Continuer</button>
            </form>

            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->get('state') }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button class="cancel" type="submit">Annuler</button>
            </form>
        </div>
    </main>
</body>
</html>

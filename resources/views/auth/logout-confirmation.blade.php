<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Déconnexion · Cremona</title>
</head>
<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#f8fafc;color:#0f172a;font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;padding:24px;box-sizing:border-box;">
    <main style="width:min(100%,420px);background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:28px;box-shadow:0 12px 28px rgb(15 23 42 / 8%);">
        <h1 style="margin:0;font-size:1.25rem;">Se déconnecter ?</h1>
        <p style="margin:10px 0 24px;color:#475569;line-height:1.5;">Votre session sera fermée sur cet appareil.</p>
        <form action="{{ $logoutAction }}" method="post" style="display:flex;gap:12px;align-items:center;">
            @csrf
            <button type="submit" style="border:0;border-radius:9px;background:#b45309;color:#fff;padding:10px 14px;font:inherit;font-weight:600;cursor:pointer;">Se déconnecter</button>
            <a href="{{ $cancelUrl }}" style="color:#475569;text-decoration:none;font-weight:600;">Annuler</a>
        </form>
    </main>
</body>
</html>

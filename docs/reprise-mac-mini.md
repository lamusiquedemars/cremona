# Reprise Cremona sur le Mac mini — 16 septembre 2026

## État à reprendre

Branche de travail : `feat/admin-interface-translations` (ne pas repartir de `main`).
Commit du correctif devis : `bcd7b66`.

Le lot corrige les lignes absentes du PDF en restaurant le contexte d’organisation lors du rendu, complète les coordonnées imprimées et respecte la devise. Les conditions manquantes sont signalées. Les nouveaux devis sont numérotés `D260001`, avec compteur annuel par organisation. Les références existantes restent inchangées.

Validation locale : 14 tests ciblés, 85 assertions, build frontend et Pint réussis. PDF d’une page et PDF de 30 lignes sur quatre pages contrôlés visuellement et par extraction de texte. Les artefacts de contrôle et sauvegardes SFTP sont temporaires sur la machine d’origine et ne sont pas dans Git.

## Production : activée et téléchargement confirmé

Six fichiers ont été envoyés par SFTP dans `/htdocs/cremona.maracujadigital.fr` et leur contenu relu pour vérifier leur intégrité :

- `app/Models/Quote.php`
- `app/Services/QuoteNumberGenerator.php`
- `app/Services/QuotePdfRenderer.php`
- `app/Filament/Resources/Quotes/QuoteResource.php`
- `resources/views/quotes/pdf.blade.php`
- `database/migrations/2026_09_16_000000_create_quote_number_sequences.php`

Les anciens fichiers ont été sauvegardés dans `/private/tmp/cremona-quotes-backup` sur la machine d’origine. Le formulaire distant conservait quelques libellés français littéraux, là où la branche emploie les clés de traduction : le fichier publié a conservé ces libellés et reçu uniquement la modification du champ référence. Cette différence est volontaire ; ne pas en déduire que la publication est incomplète.

`.env`, `vendor`, `storage` et les données n’ont pas été modifiés par le transfert. `/up` répondait HTTP 200 après publication.

Le 16 septembre, Ivo a confirmé l’exécution des commandes d’activation
ci-dessous. La migration du compteur et les caches sont donc actifs. Il a aussi
téléchargé un devis existant depuis Cremona : le parcours PDF authentifié est
confirmé en production.

Conformément à `docs/deploiement-lws.md`, l’agent publie par SFTP ; Ivo exécute les commandes Laravel dans le terminal SSH LWS :

```bash
cd ~/htdocs/cremona.maracujadigital.fr &&
composer dump-autoload --no-dev --optimize &&
php artisan migrate --force --path=database/migrations/2026_09_16_000000_create_quote_number_sequences.php &&
php artisan optimize:clear &&
php artisan config:cache &&
php artisan route:cache &&
php artisan view:cache
```

À la reprise : ne pas réexécuter ces commandes pour ce lot. Vérifier seulement
la numérotation lors de la prochaine création réelle de devis. Ne pas créer de
données commerciales fictives en production. Ne jamais lancer `migrate:fresh`,
`db:wipe` ou régénérer `APP_KEY`.

## Synchronisation sur le Mac mini

Dans le dépôt Cremona, examiner d’abord `git status` pour préserver tout travail local, puis récupérer la branche distante. Si la copie est propre :

```bash
git fetch origin
git switch feat/admin-interface-translations
git pull --ff-only
```

Les dépendances locales ne sont pas versionnées. Si nécessaire : `composer install` puis `npm ci --ignore-scripts` et `npm run build`. Les tests utilisent exclusivement la base MySQL dédiée `cremona_testing` et nécessitent une clé d’application de test. Ne pas réutiliser la base de production.

Le fichier SFTP local `.vscode/sftp.json` et ses secrets ne sont pas dans Git. Utiliser la configuration déjà présente sur le Mac mini ; ne pas publier de secrets dans le dépôt ou le chat.

## Travaux produit suivants

Voir `docs/numerotation-et-administration-commerciale.md` : future administration des numérotations, nom de rubrique à définir, réglages par organisation et aperçu documentaire. Les dossiers doivent à terme recevoir un compteur continu sur six chiffres, mais ce changement n’a pas été implémenté dans le lot devis.

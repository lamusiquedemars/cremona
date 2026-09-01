# Déploiement LWS — Cremona

## Cible

Le CRM sera publié sur `https://cremona.maracujadigital.fr`. Il reste privé :
la page d'accueil redirige vers la connexion Cremona.

## Base MySQL partagée

LWS fournit une base commune aux sites Maracuja. Cremona doit utiliser cette
base, mais avec un préfixe propre :

```env
DB_PREFIX=cremona_
```

Ne jamais utiliser `migrate:fresh`, `db:wipe`, ni une commande qui supprime
globalement des tables sur cet hébergement.

## Variables de production minimales

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cremona.maracujadigital.fr
DB_CONNECTION=mysql
DB_PREFIX=cremona_
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Les identifiants MySQL restent ceux de la base LWS existante. `APP_KEY` doit
être générée pour Cremona uniquement, jamais copiée depuis un autre site.

## Préparation et publication

### Répartition opérationnelle permanente

- L’agent prépare et envoie les fichiers via le SFTP LWS déjà configuré dans
  `/Users/ivocorreiademelo/Sites/.vscode/sftp.json`.
- Ivo ne travaille pas sur une copie locale : il exécute uniquement les
  commandes Laravel indiquées par l’agent dans le terminal SSH LWS, depuis
  `~/htdocs/cremona.maracujadigital.fr`, et en renvoie le résultat.
- Avant tout envoi, l’agent contrôle le répertoire distant. Il préserve
  systématiquement `.env`, `vendor`, `storage` et la base de données, sauf
  demande explicite contraire.

Cette répartition vaut pour toutes les publications Cremona à venir.

### Configuration des secrets et intégrations Google Ads

Les secrets Google ne transitent ni par le chat, ni par Git, ni par les écrans
des organisations. L’agent installe les variables exclusivement dans le `.env`
de production via le canal SFTP LWS existant, puis Ivo exécute seulement la
reconstruction de cache indiquée. Avant cela, l’agent vérifie la présence des
clés sans en lire ou afficher la valeur.

Les valeurs nécessaires sont centralisées une fois pour toute l’agence :

```env
GOOGLE_ADS_DEVELOPER_TOKEN=…
GOOGLE_ADS_OAUTH_CLIENT_ID=…
GOOGLE_ADS_OAUTH_CLIENT_SECRET=…
GOOGLE_ADS_LOGIN_CUSTOMER_ID=…
GOOGLE_ADS_API_ACCESS_LEVEL=basic
```

`GOOGLE_ADS_API_ACCESS_LEVEL` ne peut être `basic` ou `standard` qu’après
confirmation dans l’API Center Google Ads. Un accès `test` reste `pending` :
il ne doit jamais accéder aux comptes clients réels.

Pour chaque nouveau client, la procédure est ensuite toujours la même :

1. créer ou enregistrer le compte Ads client dans son organisation Cremona ;
2. Ivo (compte gestionnaire Maracuja) ouvre l’action **Autoriser Google Ads** ;
3. il choisit son compte agence qui a déjà accès au compte Ads client ;
4. Cremona conserve le jeton de rafraîchissement chiffré dans le coffre
   plateforme Maracuja, une seule fois pour l’agence ;
5. chaque organisation ne reçoit que l’identifiant de son compte Ads ;
6. synchroniser en lecture seule et vérifier les résultats avant toute action
   de création ou d’activation de campagne.

Le client n’a donc jamais à communiquer son mot de passe Google à Maracuja et
aucune campagne, paiement ou diffusion ne résulte de cette autorisation.

La mise en place se fait d’abord dans `Plateforme > Infrastructure Google Ads`.
Avant l’autorisation, ajouter l’URI suivante au client OAuth Google Cloud :

```txt
https://cremona.maracujadigital.fr/integrations/google-ads/agency/callback
```

L’activation est réversible depuis cette même page. Ne jamais recopier un token
Google depuis le presse-papiers ou le chat dans une migration ou une commande :
les valeurs restent dans `.env` et ne sont jamais affichées.

1. Créer le sous-domaine `cremona.maracujadigital.fr` dans LWS.
2. Envoyer le projet dans le répertoire racine imposé par LWS. Les fichiers
   `.htaccess` et `index.php` à la racine assurent le routage Laravel sans
   exposer `.env` ou `vendor`.
3. Sur le serveur, exécuter :

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

4. Configurer une tâche cron LWS qui appelle `php artisan schedule:run` chaque
   minute, et un processus de file d'attente pour `php artisan queue:work`.

## Après la publication

Une fois HTTPS actif, ajouter cette URL dans le client OAuth Google :

```txt
https://cremona.maracujadigital.fr/integrations/google-ads/callback
```

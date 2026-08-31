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

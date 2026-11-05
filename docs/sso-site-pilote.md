# Pilote SSO — Maracuja Site et Cremona

> État : code prêt à déployer ; activation production à effectuer d'abord pour
> `site.maracujadigital.fr` et l'organisation `demo-maracuja`.

## But du pilote

Un membre de **Demo Maracuja** se connecte une seule fois depuis
`site.maracujadigital.fr/admin`. Il gère les contenus du site et retrouve dans
le même menu les raccourcis **Clients**, **Demandes**, **Rendez-vous** et
**Devis**. Ces raccourcis ouvrent Cremona dans la bonne organisation, sans
redemander de mot de passe.

Ce n'est ni un iframe ni un partage de cookie. Les deux applications restent
indépendantes ; seule l'identité est déléguée par Cremona.

## Mécanisme retenu

- Cremona est le serveur OAuth 2.0 ; Laravel Passport utilise le flux
  Authorization Code avec PKCE.
- Le site est un client OAuth **public** : aucun secret partagé n'est placé
  dans son dépôt ou son `.env`.
- Le code d'autorisation est à usage unique, attaché à un `state` et à un
  vérificateur PKCE conservés dans la session du site.
- Cremona délivre un jeton court, puis répond avec le profil minimal et les
  raccourcis autorisés.
- Chaque client OAuth est rattaché, côté Cremona, à une organisation précise.
  Il ne peut pas sélectionner une autre organisation dans l'URL ou le jeton.
- La liste reçue est filtrée par modules activés **et** par droits individuels
  du membre. Le site ne peut jamais accorder un accès supplémentaire.

Le premier passage peut demander à l'utilisateur de confirmer l'ouverture du
portail. Passport conserve ensuite l'autorisation tant que le jeton est actif.

## Périmètre du pilote

La création automatique d'un compte local du site est limitée aux membres
autorisés de Demo Maracuja. Le rôle local par défaut est explicitement fixé à
`admin` pour cette démonstration. Ce choix n'est pas généralisable : avant
l'ouverture à un client, il faudra concevoir la correspondance entre rôle Site
et rôle Cremona, ainsi que la migration explicite des comptes locaux existants.

Le pilote ne met pas encore en œuvre la déconnexion fédérée ni une console
générique de raccordement de sites. Il doit d'abord être validé en réel.

## Activation de production

1. Déployer Cremona, appliquer les migrations OAuth et créer les clés dans le
   volume persistant `storage/app/passport` : `php artisan passport:keys`.
2. Créer un client public dont la seule URL de retour est
   `https://site.maracujadigital.fr/admin/sso/callback`.
3. Poser le même identifiant public dans les environnements de Cremona
   (`CREMONA_SSO_DEMO_SITE_CLIENT_ID`) et du site
   (`CREMONA_SSO_CLIENT_ID`), puis activer `CREMONA_SSO_ENABLED=true` côté
   site. Aucun secret n'est à recopier.
4. Déployer le site et appliquer sa migration `cremona_subject`.
5. Vérifier avec un membre réel de Demo Maracuja : connexion depuis le site,
   menu filtré, ouverture de chaque raccourci, refus d'un module retiré,
   suspension et retour au site.

La clé OAuth est privée et persistante ; elle ne doit jamais être affichée,
committée ni recréée à chaque release.

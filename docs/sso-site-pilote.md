# Pilote SSO — Maracuja Site et Cremona

> État : pilote publié le 5 novembre 2026 pour
> `site.maracujadigital.fr` et l'organisation `site.maracujadigital.fr`
> (nom affiché : Demo Maracuja).

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

Pour un site expressément déclaré et le seul périmètre `site:access`, Passport
ouvre directement le portail après contrôle de l'appartenance : l'utilisateur
ne voit pas un écran de consentement technique.

## Périmètre du pilote

La création automatique d'un compte local du site est limitée aux membres
autorisés de Demo Maracuja. Le rôle local par défaut est explicitement fixé à
`admin` pour cette démonstration. Ce choix n'est pas généralisable : avant
l'ouverture à un client, il faudra concevoir la correspondance entre rôle Site
et rôle Cremona, ainsi que la migration explicite des comptes locaux existants.

La déconnexion fédérée est livrée pour le pilote. Le site émet un état aléatoire
de cinq minutes conservé dans sa session, puis le transmet en POST à Cremona.
Cremona accepte uniquement l'origine exacte du site enregistré, vérifie le
client, l'organisation et l'utilisateur connecté, ferme sa session puis revient
vers l'URL de fin de session déclarée. Le site compare l'état reçu et ferme sa
propre session. L'URL de retour n'est jamais fournie par le navigateur et aucun
jeton OAuth n'est conservé pour cette fonction.

OAuth 2.0 ne normalise pas la déconnexion fédérée. Cette mise en œuvre est donc
un contrat interne, volontairement restreint à chaque site configuré ; un futur
passage à OpenID Connect pourra le remplacer par le standard RP-Initiated
Logout, sans changer l'expérience utilisateur.

## Condition d'accès

La possession d'un compte Cremona ne suffit pas : la personne doit être un
membre actif de **Demo Maracuja** et disposer des droits correspondant aux
modules affichés. C'est cette appartenance, et non l'adresse e-mail seule,
qui autorise le site à créer sa session locale. Un compte local du site qui
porte la même adresse mais n'est pas encore relié est volontairement refusé :
son rattachement doit être explicite, jamais implicite.

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

## Recette du pilote publié

1. Ouvrir `https://site.maracujadigital.fr/admin` et choisir la connexion
   Cremona.
2. Se connecter avec un membre actif de Demo Maracuja.
3. Vérifier l'arrivée dans l'administration du site, puis les raccourcis
   **Clients**, **Demandes**, **Rendez-vous** et **Devis** visibles selon les
   droits du membre.
4. Ouvrir un raccourci : Cremona doit afficher directement Demo Maracuja,
   sans seconde authentification.
5. Dans le menu utilisateur du site, choisir **Se déconnecter de l’espace**.
   Le retour doit être la page de connexion du site. Rechoisir la connexion
   doit demander les identifiants de l'espace central : aucune action dans
   Cremona ne doit être demandée à l'utilisateur.

Le compte de démonstration créé uniquement au niveau « plateforme » doit être
ajouté à l'organisation avant de pouvoir achever cette recette.

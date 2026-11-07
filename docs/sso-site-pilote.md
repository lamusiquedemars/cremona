# Pilote SSO — Maracuja Site et Cremona

> État : pilote publié le 7 novembre 2026 pour
> `site.maracujadigital.fr` et l'organisation `site.maracujadigital.fr`
> (nom affiché : Demo Maracuja).

## But du pilote

Un membre de **Demo Maracuja** se connecte une seule fois depuis
`site.maracujadigital.fr/admin`. Il gère les contenus du site et retrouve dans
le même menu les raccourcis **Clients**, **Demandes**, **Rendez-vous** et
**Devis**. Ces raccourcis ouvrent Cremona dans la bonne organisation, sans
redemander de mot de passe.

Ce n'est ni un iframe ni un partage de cookie. Les deux applications restent
indépendantes. Le site demeure l'interface de connexion visible par le client ;
Cremona vérifie séparément qu'il a bien le droit d'ouvrir la gestion métier.

## Mécanisme retenu

- L'utilisateur saisit son adresse et son mot de passe sur **son site**. Le
  mot de passe est vérifié par le site et ne quitte jamais sa base.
- Le site demande ensuite à Cremona une autorisation signée HMAC, valable une
  minute. Cremona vérifie le raccordement, l'organisation associée et que la
  même adresse e-mail est membre actif de cette organisation.
- Cremona renvoie exclusivement l'identité minimale et les raccourcis métier
  autorisés. Ils sont filtrés par modules actifs **et** droits individuels ; le
  site ne peut jamais accorder un droit supplémentaire.
- Quand l'utilisateur ouvre un raccourci, le site demande un ticket unique,
  valable une minute. Cremona consomme ce ticket, ouvre sa propre session et
  redirige directement vers le module demandé. Aucun mot de passe, cookie ni
  jeton durable n'est partagé avec le navigateur.
- Chaque raccordement est lié à une seule organisation. L'organisation ne peut
  pas être choisie ou modifiée dans l'URL.

## Périmètre du pilote

Un compte actif doit exister des deux côtés, avec la même adresse e-mail : le
compte local donne accès au site ; l'appartenance Cremona donne accès aux
modules métier. Les rôles restent indépendants : un rédacteur du site ne gagne
pas un droit commercial, et un utilisateur de Cremona ne devient pas éditeur
du site par accident. La création et la désactivation sont donc explicites dans
chaque application.

La déconnexion fédérée est livrée pour le pilote. Le site émet un état aléatoire
de cinq minutes conservé dans sa session, puis le transmet en POST à Cremona
avec une preuve HMAC liée au raccordement. Cremona vérifie le client,
l'organisation, cette preuve et l'utilisateur connecté, ferme sa session puis
revient vers l'URL de fin de session déclarée. Le site compare le même état et
ferme sa propre session. L'URL de retour n'est jamais fournie par le navigateur.

Cette preuve évite de dépendre des en-têtes `Origin` ou `Referer`, variables
selon les navigateurs. Le secret HMAC est distinct pour chaque raccordement et
reste uniquement dans les environnements du site et de Cremona ; il n'est ni
dans Git ni dans le navigateur. Aucun chemin ni URL de retour transmis par le
navigateur n’est accepté.

## Contrat d’URL et responsabilités

| Rôle | URL à utiliser | Responsabilité |
| --- | --- | --- |
| Visiteur | Domaine public du client | Contenus et formulaires publics. |
| Membre ou administrateur de l’organisation | `https://site-client/admin` | Administration éditoriale et raccourcis métier autorisés. |
| Administrateur Maracuja | Espace plateforme Cremona | Raccordement du site, utilisateurs, droits et sécurité. |

`/admin/sso/*` et `/site-portal/*` portent des échanges techniques courts. Ils
ne sont pas des pages utilisateur et une consultation directe est refusée par
conception.

## Condition d'accès

La possession d'un compte dans une seule application ne suffit pas : la
personne doit avoir un compte local actif et être membre actif de **Demo
Maracuja** avec les droits correspondant aux modules affichés. La
correspondance par adresse e-mail est contrôlée par le raccordement déclaré ;
elle ne crée ni compte ni droit par elle-même.

## Activation de production

1. Déclarer, côté Cremona, un raccordement dont l'identifiant, l'organisation,
   l'URL du site, les modules exposés et le secret HMAC sont propres au client.
2. Poser cet identifiant et ce secret dans les environnements du site et de
   Cremona, puis activer `CREMONA_SSO_ENABLED=true` côté site.
3. Créer les comptes locaux du site, puis ajouter les mêmes personnes comme
   membres actifs de l'organisation dans Cremona en choisissant leurs droits.
4. Déployer les deux applications et vider leurs caches de configuration.
5. Vérifier avec un membre réel : connexion depuis le site, menu filtré,
   ouverture de chaque raccourci, refus d'un module retiré, suspension et
   retour à la page de connexion du site.

L'identifiant de raccordement et son secret sont persistants. Ils ne doivent
jamais être affichés, committés ni recréés à chaque release.

## Recette du pilote publié

1. Ouvrir `https://site.maracujadigital.fr/admin`.
2. Se connecter avec les identifiants du site d'un membre actif de Demo
   Maracuja.
3. Vérifier l'arrivée dans l'administration du site, puis les raccourcis
   **Clients**, **Demandes**, **Rendez-vous** et **Devis** visibles selon les
   droits du membre.
4. Ouvrir un raccourci : Cremona doit afficher directement Demo Maracuja,
   sans seconde authentification.
5. Dans le menu utilisateur du site, choisir **Se déconnecter de l’espace**.
   Le retour doit être la page de connexion du site. Rechoisir la connexion
   doit demander les identifiants du site : aucune action dans Cremona ne doit
   être demandée à l'utilisateur.

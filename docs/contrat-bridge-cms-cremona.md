# Contrat du bridge CMS ↔ Cremona

> Statut : spécification du premier lot à implémenter. Aucune bascule de site
> existant n'est autorisée par ce document seul.
>
> Version du contrat : `v1` — 8 septembre 2026.

Ce contrat applique le [Blueprint des sites autonomes et connectés](blueprint-sites-connectes.md).
Il transforme le formulaire d'un site connecté en événement fiable vers
Cremona, sans transformer le CMS en second CRM.

## 1. État constaté

| Application | État actuel | Écart à combler |
| --- | --- | --- |
| Atelier Ivo | Le formulaire crée une `ContactSubmission`, puis appelle déjà `POST /api/v1/incoming-requests` avec un jeton et une clé d'idempotence | l'appel est synchrone et sans reprise ; le stockage local reste la file de travail de secours |
| Contempo | `ContactForm` enregistre une `Inquiry` locale si ce module est actif | aucun connecteur Cremona ni reprise fiable |
| Cremona | L'API `POST /api/v1/incoming-requests` est authentifiée par intégration, isolée par organisation et idempotente | la liaison entre le jeton et le `site_reference` doit être contrôlée ; l'API ne reçoit pas encore une outbox CMS générique |

Le point de départ est donc réutilisable, mais ce n'est pas encore un bridge
installable ni suffisamment fiable pour devenir le seul flux métier.

## 2. Responsabilité du bridge

Le bridge est un module CMS optionnel, activé seulement pour le profil « site
connecté à la gestion ». Il ne crée ni contact CRM, ni demande locale, ni écran
de traitement dans le CMS. Il assure uniquement :

1. la construction de l'événement depuis le formulaire validé ;
2. son écriture atomique dans une outbox CMS ;
3. sa transmission rejouable à Cremona ;
4. la conservation d'une trace technique limitée ;
5. un lien d'accès à l'espace de gestion configuré.

`ContactForm` reste responsable de la page publique, de la validation,
l'anti-spam et de l'accusé de réception. `Inquiries` est incompatible avec le
mode connecté : il est désactivé pour la soumission courante et son menu n'est
pas présenté comme une deuxième boîte de réception.

## 3. Outbox CMS

Après validation du formulaire, et dans la même transaction locale, le CMS crée
une ligne `cremona_outbox_messages` :

| Champ | Rôle |
| --- | --- |
| `event_id` (ULID) | identifiant public stable de l'événement |
| `event_type` | `incoming_request.created` en v1 |
| `idempotency_key` | `<site_reference>:contact:<identifiant-local-stable>` |
| `payload_encrypted` | contenu transmis, chiffré au repos |
| `payload_hash` | détection d'une réutilisation différente de la clé |
| `status` | `pending`, `delivering`, `delivered` ou `failed` |
| `attempts`, `next_attempt_at`, `last_error_code` | reprise observable, sans secret ni corps d'erreur distant |
| `remote_request_id`, `delivered_at` | preuve de réception Cremona |

La réponse publique est immédiate : le visiteur n'attend jamais la disponibilité
de Cremona. Un job asynchrone envoie l'événement après le commit, avec délai
progressif et verrou par ligne. Les échecs transitoires sont rejoués ; après un
nombre documenté de tentatives, le message devient `failed` et déclenche une
alerte d'administration. Il reste rejouable manuellement.

Après succès, le contenu chiffré est purgé selon une durée de conservation
configurable ; la clé, l'empreinte, les dates et l'identifiant Cremona restent
pour l'audit. La rétention locale ne constitue pas une liste de demandes et ne
donne pas lieu à un écran de réponse.

## 4. API `incoming-requests` v1

Le premier lot conserve l'endpoint existant :

```text
POST /api/v1/incoming-requests
Authorization: Bearer <key_id>.<secret>
Idempotency-Key: <clé stable>
Accept: application/json
```

L'API reçoit au minimum :

```json
{
  "source": {
    "channel": "website",
    "name": "maracuja-cms",
    "site_reference": "contempo-luthiers",
    "form_reference": "contact"
  },
  "contact": {
    "name": "Camille Martin",
    "email": "camille@example.test",
    "phone": "+33..."
  },
  "request": {
    "subject": "Révision d'un violon",
    "message": "..."
  }
}
```

`attribution`, `answers` et `consent` restent des extensions déjà prévues par
l'API. Les ajouts futurs sont additifs ; un changement de sens, de champ requis
ou de sécurité impose une version `v2`.

| Réponse | Sens pour l'outbox |
| --- | --- |
| `201` | demande créée ; mémoriser l'identifiant distant |
| `200` | même clé et même contenu déjà reçus ; mémoriser l'identifiant distant |
| `409` | même clé avec contenu différent ; arrêter et signaler une anomalie |
| `401`, `403` | connexion invalide ou révoquée ; arrêter et alerter |
| `422` | bug de contrat ou configuration de formulaire ; arrêter et alerter |
| `429`, `5xx`, indisponibilité réseau | échec transitoire ; rejouer |

Cremona crée ou rapproche le contact selon ses règles CRM, crée la demande et
sa conversation initiale, puis expose son identifiant et son statut. Le CMS ne
reçoit pas le contenu de la conversation en retour.

## 5. Sécurité et isolation

- Un jeton est créé pour une seule organisation et le secret n'est affiché
  qu'une fois ; Cremona ne conserve que son empreinte.
- Le bridge conserve le secret uniquement dans l'environnement du site, jamais
  en base, Git, export ou écran CMS.
- L'intégration de type `maracuja_cms` doit lister les `site_reference`
  autorisés. L'API refuse un événement dont la référence n'est pas rattachée à
  cette organisation et à ce jeton.
- La révocation du jeton bloque immédiatement les appels et rend l'état visible
  dans l'outbox ; la rotation crée un nouveau jeton avant révocation de l'ancien.
- Les entrées sont limitées en taille, validées côté CMS et côté Cremona. Les
  pièces jointes ne sont pas incluses dans ce premier contrat.
- Chaque événement conserve son site source, son identifiant, ses tentatives et
  son résultat afin d'auditer une erreur sans exposer les secrets.

## 6. Emails et notifications

Le bridge ne traite pas les emails directs. Dans le profil connecté, la boîte
professionnelle est relevée par l'intégration IMAP de l'organisation dans
Cremona, où les réponses sont rédigées et envoyées.

Le CMS peut continuer à envoyer l'accusé de réception du formulaire au visiteur.
Son email d'alerte interne est désactivé pour éviter une seconde boîte de
réception ; Cremona porte la notification de la nouvelle demande.

## 7. Installation et bascule d'un site

1. Créer l'organisation, le site rattaché et le jeton d'intégration dans
   Cremona ; vérifier l'organisation, le `site_reference` et l'URL cible.
2. Installer/activer le bridge dans le CMS, renseigner ses seules variables
   d'environnement, puis lancer le contrôle de prérequis.
3. En local, tester deux organisations et deux sites simulés ; une soumission de
   démonstration doit créer une seule demande et conversation dans la bonne
   organisation Cremona, y compris après reprise.
4. Déployer ce lot sur le premier site de production sans activer encore la
   bascule. Vérifier la configuration et une soumission réelle avec Ivo.
5. Activer le mode connecté : `Inquiries` ne reçoit plus de nouvelles demandes,
   ses ressources et indicateurs sont masqués ; le formulaire et son accusé de
   réception restent actifs.
6. Vérifier l'outbox et le flux réel pendant une période convenue. Les demandes
   locales historiques restent intactes et accessibles en lecture.
7. Décider séparément d'un import historique idempotent. Il marque les éléments
   importés, ne supprime aucune ligne locale et n'est jamais lancé par défaut.

La bascule est réversible avant l'import historique : on désactive le bridge et
réactive `Inquiries`. Les événements déjà reçus dans Cremona ne sont pas
supprimés ; ils restent tracés et doivent être traités sans doublon.

## 8. Preuves de fin avant premier déploiement

- un site connecté peut envoyer deux fois la même soumission sans créer deux
  demandes ni deux conversations ;
- une indisponibilité Cremona laisse le formulaire utilisable et l'outbox livre
  ultérieurement l'événement ;
- deux sites et deux organisations ne peuvent pas se transmettre de données ;
- une référence de site non autorisée, un jeton révoqué et un payload invalide
  sont refusés et observables ;
- le CMS connecté n'affiche plus de boîte de demandes ni de compteur concurrent ;
- un email direct relevé par Cremona et un formulaire du site sont visibles dans
  le même espace organisationnel ;
- l'historique local n'est ni supprimé ni modifié par la bascule.

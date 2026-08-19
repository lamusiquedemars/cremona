# ADR 0001 — Domaine de correspondance client

- Statut : proposé, en attente de validation
- Date : 18 août 2026
- Portée : Cremona canonique, puis adaptation mono-site dans Maracuja CMS Starter
- Décision préalable : aucune implémentation des lots 2 à 7 avant validation des
  points listés en fin de document

## Contexte vérifié

Cremona possède déjà le CRM V1 multi-organisation : personnes, entreprises,
coordonnées, demandes entrantes, rapprochement, responsables, statuts, notes,
historique, rendez-vous, intégrations chiffrées et API idempotente. Le domaine de
correspondance doit compléter ce CRM, sans remplacer `IncomingRequest` ni
dupliquer son workflow métier.

Maracuja CMS Starter et Marcos Túlio possèdent déjà `Conversation` et la table
`conversation_messages`. Ce modèle sert aujourd'hui au chat web et à l'IA : un
message n'a qu'un auteur, un corps texte, un canal, une visibilité, un statut de
livraison simple et un identifiant externe. Il ne représente pas encore un email
RFC, ses destinataires, ses références, ses copies IMAP ou ses pièces jointes.

Marcos ajoute des statuts et champs juridiques, des textes portugais et le rôle
`client_manager`. Ces écarts doivent être préservés lors du portage ; ils ne
doivent pas entrer dans le noyau Cremona.

Le nouveau Maracuja CMS multi-site ne contient que `Site`, `Domain`,
`SiteModule` et l'isolation `site_id`. Il ne doit posséder ni conversations ni
messages métier, seulement une future outbox technique de transmission.

## Décision proposée

### 1. Vocabulaire et tables

Le modèle canonique utilise les noms suivants :

| Modèle | Table | Responsabilité |
| --- | --- | --- |
| `Conversation` | `conversations` | Fil métier cohérent avec un contact, indépendamment du canal initial. |
| `ConversationUserState` | `conversation_user_states` | État de lecture propre à un utilisateur. |
| `ConversationMessage` | `conversation_messages` | Contenu entrant ou sortant et en-têtes structurants. |
| `MessageParticipant` | `message_participants` | Expéditeur et destinataires multiples (`from`, `to`, `cc`, `bcc`, `reply_to`). |
| `MessageReference` | `message_references` | Valeurs ordonnées de `References`, interrogeables pour le threading. |
| `MessageAttachment` | `message_attachments` | Métadonnées et chemin privé d'une pièce jointe. |
| `MessageThreadCandidate` | `message_thread_candidates` | Rattachements proposés lorsqu'aucun choix unique n'est fiable. |
| `EmailMailbox` | `email_mailboxes` | Configuration non secrète et état opérationnel d'une boîte. |
| `EmailFolder` | `email_folders` | Dossier IMAP suivi et curseur `UIDVALIDITY`/UID. |
| `EmailMessageCopy` | `email_message_copies` | Présence d'un message dans un dossier IMAP donné. |
| `EmailSyncRun` | `email_sync_runs` | Exécution observable d'une relève ou d'un rapprochement. |

Le nom PHP `ConversationMessage` évite un `App\Models\Message` ambigu. La table
`conversation_messages` est conservée afin de faciliter l'adaptation ultérieure
du starter sans créer un second système de conversations.

`IncomingRequestActivity` et `CrmNote` restent distincts. Une activité peut
projeter « message reçu », « message accepté par SMTP », « rattachement corrigé »
ou « synchronisation échouée », mais ne contient jamais le corps complet de
l'email. Une note interne n'est pas un faux email et reste dans `crm_notes`.

### 2. Relations

Toutes les tables ci-dessus portent `organization_id`, utilisent
`BelongsToOrganization` et sont invisibles sans organisation active.

- une organisation possède plusieurs conversations, messages, boîtes, dossiers
  et exécutions de synchronisation ;
- une conversation appartient éventuellement à une `Person`, une `Company`, une
  `IncomingRequest` et un utilisateur responsable ;
- une personne et une entreprise possèdent plusieurs conversations ;
- une demande possède au plus une conversation principale et la clé
  `conversations.incoming_request_id` est donc nullable et unique ;
- une conversation possède plusieurs messages et états de lecture utilisateur ;
- un message appartient normalement à une conversation, mais
  `conversation_id` reste nullable pendant un rattachement ambigu ou impossible ;
- un message possède plusieurs participants, références, pièces jointes, copies
  IMAP et candidats de rattachement ;
- une boîte appartient à une `OrganizationIntegration` de type email ;
- l'intégration conserve exclusivement les secrets chiffrés IMAP/SMTP ;
- une boîte possède plusieurs dossiers et exécutions de synchronisation ;
- un dossier appartient à une boîte et possède plusieurs copies IMAP ;
- une copie IMAP appartient à un message et à un dossier.

Les relations nullable ne permettent pas les fuites inter-organisation : chaque
affectation est validée dans le contexte actif, sur le modèle des contrôles déjà
présents pour les demandes, contacts et rendez-vous. Des index composites
commencent par `organization_id`. Les contraintes d'unicité dépendant du tenant
sont également composites, sauf les ULID publics qui restent uniques globalement.

### 3. Attributs structurants

`conversations` contient au minimum : `organization_id`, `public_id` ULID,
`person_id`, `company_id`, `incoming_request_id`, `assigned_user_id`,
`initial_channel`, `subject`, `normalized_subject`, `status`,
`first_message_at`, `last_message_at`, `last_inbound_at`, `last_outbound_at`,
`closed_at`, `archived_at` et les timestamps.

`conversation_messages` contient au minimum : `organization_id`, `public_id`
ULID, `conversation_id`, `email_mailbox_id`, `author_user_id`, `direction`,
`channel`, `subject`, `body_text`, `body_html_sanitized`, `message_id`,
`in_reply_to`, `transport_status`, `threading_status`, `idempotency_key`,
`payload_fingerprint`, `authored_at`, `queued_at`, `accepted_at`, `received_at`,
`failed_at`, `synchronized_at`, `failure_code`, `failure_message` et les
timestamps.

Les en-têtes bruts utiles au diagnostic peuvent être conservés dans un champ
texte privé à durée courte. `References` n'est pas stocké dans un JSON : chaque
identifiant devient une ligne ordonnée de `message_references`. Les participants
et pièces jointes ont également leurs propres tables.

`message_participants` conserve le rôle, le nom affiché, l'adresse telle que
reçue, l'adresse normalisée et la position. Une ligne peut référencer un
`ContactMethod` si le rapprochement est certain, sans perdre le snapshot reçu.

`message_attachments` conserve le nom, le type MIME déclaré et détecté, la
taille, l'empreinte SHA-256, le disque et le chemin privés, le `Content-ID`, la
disposition inline ou attachment, ainsi que l'état de contrôle. Aucun chemin
public n'est accepté.

`email_folders` conserve le nom distant, le rôle `inbox` ou `sent`,
`uid_validity`, `last_uid`, l'état de synchronisation, la dernière réussite et
la dernière erreur. `email_message_copies` prend une contrainte unique sur
boîte, dossier, `uid_validity` et UID. Cette séparation permet de rapprocher une
copie de `Sent` d'un message déjà créé par l'administration sans le dupliquer.

### 4. États et transitions

#### Conversation

Les états sont volontairement indépendants du workflow de la demande :

- `open` : une action de l'équipe est attendue ;
- `waiting_customer` : la dernière action utile vient de l'équipe ;
- `closed` : fil clos manuellement ou par règle explicite.

L'archivage est représenté par `archived_at`, pas par un quatrième état. Un
message client reçu place ou replace la conversation en `open`, y compris après
fermeture. Un message sortant accepté par SMTP peut la placer en
`waiting_customer`. La fermeture reste explicite. Le caractère lu/non lu est
calculé par `conversation_user_states`, jamais encodé dans le statut partagé.

#### Message

La direction vaut `inbound` ou `outbound`. Le canal initial couvre d'abord
`website` et `email` ; d'autres canaux pourront être ajoutés sans confondre le
canal avec la direction.

Le statut de transport vaut :

- `received` pour un message entrant enregistré ;
- `draft` pour une réponse encore modifiable ;
- `queued` pour une réponse confiée à la file ;
- `accepted` lorsque le serveur SMTP l'a acceptée ;
- `failed` lorsque l'envoi a échoué de manière visible et relançable.

Transitions sortantes : `draft → queued → accepted|failed`, puis
`failed → queued` lors d'une relance. `accepted` ne signifie jamais livré. Un
futur retour DSN pourra ajouter un état distinct sans réinterpréter l'historique.

Le statut de threading, séparé du transport, vaut `pending`, `matched`,
`ambiguous`, `unmatched` ou `ignored`.

#### Synchronisation

Une exécution `EmailSyncRun` vaut `queued`, `running`, `succeeded`, `partial` ou
`failed`. Un dossier vaut `idle`, `syncing` ou `error`. Une boîte vaut `active`,
`degraded`, `paused` ou `revoked`. Une erreur conserve un code stable, un message
expurgé, les compteurs et les dates de tentative ; jamais un secret.

Un verrou distribué par boîte empêche deux relèves simultanées. Une exécution
abandonnée peut être marquée échouée puis rejouée depuis les curseurs persistés.

### 5. Threading et idempotence

Le rattachement suit cet ordre strict dans la même organisation :

1. `In-Reply-To` correspondant à un `Message-ID` connu ;
2. premier élément pertinent de `References` correspondant à un message connu ;
3. identifiant externe ou clé d'idempotence déjà connu ;
4. participants, sujet normalisé et fenêtre temporelle configurée ;
5. candidats visibles si plusieurs conversations restent possibles ;
6. nouvelle conversation si aucun candidat fiable n'existe et si la politique
   d'admission l'autorise ; sinon message non rattaché visible.

Les identifiants RFC sont conservés sous leur forme reçue et sous une forme
canonique destinée à la recherche. Le sujet normalisé retire les préfixes usuels
de réponse/transfert, normalise espaces et casse, mais ne suffit jamais seul à
fusionner deux fils.

Une correspondance exacte par en-tête peut rattacher automatiquement. La règle
heuristique participants+sujet+temps ne rattache automatiquement que si elle
produit un candidat unique au-dessus d'un seuil documenté et aucune autre
conversation ouverte concurrente. Sinon `message_thread_candidates` expose les
choix et leurs raisons.

Un rattachement manuel écrit l'acteur, la date, l'ancienne conversation, la
nouvelle conversation et la justification dans le journal d'audit. Il déplace
le message, jamais une demande ni un dossier entier implicitement.

L'idempotence utilise plusieurs niveaux :

- unicité `(email_folder_id, uid_validity, remote_uid)` pour une copie IMAP ;
- clé d'idempotence organisationnelle pour les messages créés par formulaire ou
  administration ;
- `Message-ID` et empreinte de secours pour rapprocher SMTP et `Sent` ;
- empreinte seulement comme signal de secours, jamais comme preuve universelle
  de fusion.

Un changement de `UIDVALIDITY` invalide le curseur du dossier et déclenche un
rescan borné avec les mêmes règles de déduplication.

### 6. Synchronisation email

La première version synchronise uniquement `INBOX` et le dossier `Sent` détecté
par attribut IMAP spécial, avec possibilité de confirmer le dossier dans la
configuration non secrète. Spam, corbeille, brouillons, newsletters et
notifications système explicitement exclues ne sont pas importés.

L'email sortant suit : création `draft`, passage `queued`, job d'envoi,
attribution du `Message-ID`, acceptation SMTP ou échec. La copie ultérieure de
`Sent` crée un `EmailMessageCopy` et complète le message existant. Elle ne crée
pas un second `ConversationMessage`.

Les jobs réseau ne transportent que des identifiants de modèles. Ils sont
rejouables, limités par boîte et utilisent temporisation progressive. Les
notifications techniques émises par le formulaire portent une clé ou un en-tête
d'exclusion afin de ne jamais devenir un faux message client.

### 7. Visibilité et permissions

Les permissions proposées complètent, sans supprimer, `view_crm` et
`manage_crm` :

| Permission | Owner | Administrator | Collaborator | Viewer |
| --- | ---: | ---: | ---: | ---: |
| `view_correspondence` | oui | oui | oui | oui |
| `reply_correspondence` | oui | oui | oui | non |
| `manage_correspondence_links` | oui | oui | oui | non |
| `erase_correspondence` | oui | oui | non | non |
| `manage_email_mailboxes` | oui | oui | non | non |

Les surcharges explicites par membership restent possibles. Un administrateur
de plateforme traverse les organisations uniquement dans un contexte
d'organisation explicite et audité ; le global scope n'est jamais désactivé par
l'interface client.

Lire une correspondance ne donne pas accès aux credentials. Répondre ne permet
pas de changer la boîte. Effacer n'est pas un `delete()` direct : seul le service
de rétention/effacement, autorisé et audité, peut le faire.

Pour Marcos, `client_manager` recevra à terme l'équivalent local de voir,
répondre et rattacher, mais jamais la gestion des secrets ou réglages techniques.

### 8. Sécurité

- secrets IMAP/SMTP uniquement dans `OrganizationIntegration.credentials`, avec
  cast chiffré, champs cachés, révocation et audit expurgé ;
- HTML assaini avant persistance d'affichage ; images distantes bloquées par
  défaut ; corps texte toujours disponible ;
- pièces jointes sur disque privé, taille et types autorisés, nom de stockage
  généré, empreinte et contrôle avant téléchargement ;
- aucune valeur secrète, corps de message ou pièce jointe dans les logs de jobs
  et d'audit ;
- validation d'appartenance à l'organisation sur chaque relation, plus tests
  anti-fuite couvrant lecture, création, rattachement, recherche et fichiers ;
- liste et aperçu n'affichent le Bcc qu'aux utilisateurs habilités à voir le
  message sortant concerné ;
- les données brutes mises en quarantaine après erreur de parsing restent sur
  stockage privé et sont purgées rapidement.

### 9. Rétention proposée

La politique est configurable par organisation dans des bornes imposées par la
plateforme. Les valeurs par défaut proposées sont :

- conversation ouverte ou en attente : aucune purge automatique ;
- conversation close et liée au CRM : archivage après 24 mois d'inactivité,
  effacement 5 ans après la dernière activité ;
- message non rattaché ou ambigu : 180 jours pour permettre la correction ;
- brouillon abandonné ou message définitivement échoué : 90 jours ;
- exécutions de synchronisation et détails d'erreur : 90 jours ;
- source MIME brute réussie : non conservée ; source en quarantaine après
  erreur de parsing : 7 jours ;
- pièces jointes : même durée que leur message ;
- après déclenchement de l'effacement : archive logique pendant 30 jours, puis
  suppression physique du corps, HTML, participants et fichiers privés ;
- journal d'audit minimal d'un effacement : 6 ans, sans corps, adresse complète,
  nom de fichier ni secret.

Une obligation de conservation ou un litige place la conversation en
`legal_hold` et suspend la purge. Une demande d'accès, d'export ou d'effacement
utilise un processus dédié et audité ; elle ne dépend pas de la purge planifiée.
Ces durées sont des valeurs produit proposées, pas une conclusion juridique, et
doivent être validées avant implémentation.

### 10. Propriété des données

- Cremona est la source de vérité des contacts, demandes et correspondances de
  la plateforme multi-site ;
- chaque installation autonome est sa propre source de vérité locale et ne
  dépend jamais de Cremona à l'exécution ;
- Maracuja CMS Starter reçoit plus tard une adaptation mono-site du même
  comportement en prolongeant ses tables actuelles ;
- Marcos reçoit ensuite un portage sélectif qui préserve portugais, champs
  juridiques, rôle `client_manager` et configuration par environnement ;
- le nouveau Maracuja CMS ne stocke que le formulaire public et une outbox
  technique temporaire jusqu'à l'accusé API Cremona ; il ne stocke aucun fil
  métier durable.

### 11. Stratégie de migration

Toutes les migrations sont additives, réversibles et testées sur MySQL/MariaDB.

1. Dans Cremona, créer les nouvelles tables et enums sans modifier le sens des
   tables CRM existantes.
2. Ajouter les relations nullable aux modèles existants et les index composites,
   puis couvrir chaque modèle par des tests anti-fuite.
3. Faire produire au parcours formulaire une conversation et un message initial
   dans la même transaction que la demande, avec clé d'idempotence.
4. Activer l'interface et un transport factice derrière une capacité dédiée.
5. Ajouter seulement ensuite les tables opérationnelles IMAP/SMTP et la
   synchronisation par boîte.
6. Dans le starter, conserver `conversations` et `conversation_messages`, ajouter
   les colonnes et tables manquantes, et faire évoluer progressivement la classe
   `Message` vers le vocabulaire `ConversationMessage` sans rupture de données.
7. Comparer starter et Marcos avant chaque portage ; ne jamais copier les
   migrations de configuration client ni modifier les données de production
   implicitement.
8. Pour Maracuja CMS multi-site, traiter l'outbox et le contrat API dans le lot 6,
   sans introduire les tables définies dans cet ADR.

Le déploiement se fait en deux temps lorsque nécessaire : schéma compatible
avec l'ancien code, puis code qui l'utilise. Aucune migration ne renomme ou ne
supprime une colonne existante dans le même déploiement.

## Options écartées

- Réutiliser `incoming_request_activities` comme stockage des emails : contenu
  trop riche, pièces jointes et participants incompatibles avec un journal
  immuable léger.
- Mettre toutes les données RFC dans un JSON : recherche, contraintes,
  idempotence et rattachement seraient fragiles.
- Faire de Maracuja CMS ou de Marcos une projection runtime de Cremona : cela
  contredit leur autonomie et crée plusieurs sources CRM.
- Stocker seulement un lien `mailto:` : aucune chronologie, livraison,
  synchronisation `Sent` ni correction de rattachement.
- Importer toute la boîte : périmètre, confidentialité et bruit incompatibles
  avec le premier socle.
- Extraire immédiatement un package Laravel complet : les règles de tenancy et
  permissions diffèrent entre Cremona et les installations autonomes.

## Décisions à valider avant le lot 2

1. Valider les noms de modèles et tables, notamment `ConversationMessage` avec
   table `conversation_messages`.
2. Valider la relation un-à-zéro/un entre `IncomingRequest` et sa conversation
   principale, au lieu d'autoriser plusieurs conversations par demande.
3. Valider qu'un message ambigu peut exister temporairement sans conversation,
   avec des candidats explicites et un rattachement audité.
4. Valider les trois statuts de conversation (`open`, `waiting_customer`,
   `closed`) et l'archivage séparé.
5. Valider les statuts de transport, en particulier le mot `accepted` qui ne
   promet pas une livraison.
6. Valider les permissions proposées et le droit de rattachement accordé par
   défaut au rôle `collaborator`.
7. Valider les durées de rétention proposées, en particulier 5 ans pour une
   correspondance CRM close et 6 ans pour l'audit minimal.
8. Valider l'absence de conservation durable du MIME brut après parsing réussi.

Tant que ces huit points ne sont pas validés, cet ADR reste « proposé » et aucun
élément du lot 2 ne doit être créé.

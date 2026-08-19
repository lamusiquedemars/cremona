# Chantier — Correspondance client centralisée

> Document de passation pour ouvrir un nouveau chat.
> État constaté le 18 août 2026.

## 1. Mission

Construire une correspondance client professionnelle et bidirectionnelle dans
les produits Maracuja, sans remettre en cause l'autonomie des sites déjà livrés
et sans recréer le CRM de Cremona.

Le besoin fonctionnel de référence est le suivant :

1. un visiteur envoie un message depuis un site ;
2. le professionnel le voit dans l'administration et dans sa boîte email ;
3. le contact est créé ou rapproché dans l'administration ;
4. le professionnel peut répondre depuis l'administration, le webmail, son
   ordinateur ou son téléphone ;
5. les messages suivants, entrants et sortants, apparaissent dans la même
   chronologie dans l'administration ;
6. les erreurs, doublons et rattachements ambigus restent visibles et
   corrigeables.

Ce comportement correspond au socle d'un CRM disposant d'une synchronisation
email. Il ne faut pas le réduire à un simple bouton `mailto:`.

## 2. Décisions déjà validées

Ces décisions ne doivent pas être rouvertes sans élément nouveau et explicite.

### 2.1 Marcos Túlio reste autoportant

Le site Marcos Túlio ne doit pas dépendre de Cremona à l'exécution. Ses
contacts, demandes, conversations et emails restent dans sa propre application
et sa propre base de données.

Une indisponibilité de Cremona ne doit jamais empêcher le site Marcos Túlio de
fonctionner, recevoir un formulaire, afficher l'administration ou synchroniser
sa boîte email.

### 2.2 Cremona possède déjà une V1 CRM

Ne pas proposer de « créer le CRM » comme s'il n'existait pas. Cremona possède
déjà notamment :

- organisations et isolation multi-organisation ;
- membres, rôles et permissions ;
- personnes et entreprises ;
- coordonnées multiples ;
- demandes entrantes ;
- qualification et rapprochement des contacts ;
- attribution, statuts et résultats ;
- notes internes ;
- historique immuable ;
- archivage ;
- API d'intégration sécurisée et idempotente ;
- rendez-vous et synchronisation Brevo ;
- secrets d'intégration chiffrés.

Le manque actuel est un sous-système de **correspondance client** :
conversations, emails entrants et sortants, synchronisation des boîtes et
chronologie unifiée.

### 2.3 Les deux modes de déploiement restent distincts

```text
Sites autonomes
Maracuja CMS Starter → installation locale → données locales
                                      └────→ Marcos Túlio

Plateforme future
Maracuja CMS multi-site → API sécurisée → Cremona
                                            └→ CRM et correspondance
```

Il n'y a pas de synchronisation runtime Marcos → Cremona à construire dans ce
chantier.

### 2.4 Le nouveau Maracuja CMS ne devient pas un troisième CRM

Dans l'architecture multi-site cible, Maracuja CMS possède les sites, domaines,
thèmes, contenus, formulaires publics et médias publics. Cremona possède les
contacts, demandes et correspondances métier.

Maracuja CMS pourra conserver une file technique temporaire pour rejouer une
transmission API, mais pas une deuxième source de vérité CRM.

## 3. Dépôts concernés

### 3.1 Cremona

- Chemin : `/Users/ivocorreiademelo/Sites/cremona`
- Branche : `main`
- État de référence : commit `abff2a4 Synchronize Brevo meeting webhooks`
- Laravel `^13.17`, Filament `^4.0`, PHP `^8.3`
- Rôle : CRM SaaS multi-organisation et modèle canonique de la correspondance
  client.

Éléments à lire en premier :

- `README.md` ;
- `docs/architecture.md` ;
- `docs/demarrage-cremona-maracuja-cms.md` ;
- `app/Models/Person.php` ;
- `app/Models/IncomingRequest.php` ;
- `app/Models/IncomingRequestActivity.php` ;
- `app/Models/OrganizationIntegration.php` ;
- `app/Services/IncomingRequestManager.php` ;
- `app/Services/OrganizationIntegrationManager.php` ;
- `app/Filament/Resources/IncomingRequests/` ;
- les tests CRM, d'intégration et d'isolation.

Attention : `docs/demarrage-cremona-maracuja-cms.md` décrit une photographie du
14 août 2026. Certaines sections sont désormais dépassées, car la V1 CRM et les
rendez-vous ont été développés depuis. Préserver ce fichier tant qu'une mise à
jour dédiée n'a pas été validée.

Le worktree contient actuellement un fichier non suivi :
`docs/demarrage-cremona-maracuja-cms.md`. Ne pas l'écraser ni l'inclure par
accident dans un commit.

### 3.2 Maracuja CMS Starter

- Chemin : `/Users/ivocorreiademelo/Sites/maracuja-cms-starter`
- Branche : `main`
- État de référence : commit `5f5b824 Add reusable social links`
- Branche locale en avance de quatre commits sur `origin/main`
- Laravel `^13.8`, Filament `^4.0`, PHP `^8.3`
- Worktree propre au moment de l'audit
- Rôle : source versionnée des sites autonomes et implémentation générique
  mono-site de la correspondance.

Éléments déjà présents :

- `Contacts` ;
- `Inquiries` ;
- `Conversations` ;
- `conversation_messages` ;
- formulaire public ;
- notifications Laravel Mail ;
- statuts de conversation et de livraison ;
- identifiant externe de message ;
- administration Filament et tests.

Le lien de réponse actuel utilise `mailto:`. Il doit être remplacé ou complété
par une véritable réponse enregistrée et envoyée depuis l'administration.

### 3.3 Marcos Túlio Advocacia

- Chemin : `/Users/ivocorreiademelo/Sites/marcos-tulio-advocacia`
- Branche : `main`
- État applicatif de référence :
  `b10a1c4 Configure production mail workflow`
- Laravel `^13.8`, Filament `^4.0`, PHP `^8.3`
- Rôle : premier pilote réel de la version autonome.

Production :

- domaine : `https://marcostulioadvocacia.com.br` ;
- alias SSH : `marcos-tulio-admin` ;
- application :
  `/home/u424344637/domains/marcostulioadvocacia.com.br/app` ;
- webroot :
  `/home/u424344637/domains/marcostulioadvocacia.com.br/public_html` ;
- PHP Hostinger : 8.4 ;
- MariaDB avec préfixe `avocat_` ;
- boîte : `contato@marcostulioadvocacia.com.br` ;
- SMTP : `smtp.hostinger.com:465`, SMTPS ;
- IMAP : `imap.hostinger.com:993`, TLS ;
- identifiant email stocké dans le Trousseau macOS sous
  `Marcos Túlio — email contato` ;
- identifiant de connexion au site stocké séparément sous
  `Marcos Túlio — site`.

Ne jamais afficher, journaliser, committer ou coller dans le chat les mots de
passe, clés ou valeurs secrètes.

Le workflow actuellement validé :

- formulaire public enregistré dans l'administration ;
- contact rapproché ;
- alerte au cabinet ;
- confirmation au visiteur ;
- SMTP réel opérationnel ;
- messages en portugais ;
- notification de demande issue du chat ;
- réponse actuelle par `mailto:` uniquement.

Le worktree Marcos contient des changements appartenant à l'utilisateur :

- `app/Providers/AppServiceProvider.php` modifié ;
- `phpunit.xml` modifié ;
- `Informações e logo.docx` non suivi ;
- `docs/recette-agendamento-brevo.md` non suivi ;
- `optimized-videos/` non suivi ;
- `public/images/brand/tmp/` non suivi.

Ne pas les modifier, supprimer, ajouter ou committer sans demande explicite.

### 3.4 Nouveau Maracuja CMS multi-site

- Chemin : `/Users/ivocorreiademelo/Sites/maracuja-cms`
- Branche : `main`
- État de référence :
  `8bb9a18 Initialize Maracuja CMS multi-site foundation`
- Laravel `^13.17`, Filament `^4.0`, PHP `^8.3`
- Worktree propre au moment de l'audit
- Rôle : application publique multi-site future.

Il ne contient actuellement que la fondation : sites, domaines, modules,
contexte par domaine et isolation `site_id`. Il ne faut pas y copier les
modèles CRM du starter.

## 4. Propriété des données

| Donnée | Site autonome / Marcos | Cremona | Maracuja CMS multi-site |
| --- | --- | --- | --- |
| Contenus et médias publics | Local | Non | Oui |
| Formulaire public | Local | Non | Oui |
| Contact produit par le formulaire | Local | Oui | Transmission seulement |
| Demande entrante | Local | Oui | File technique seulement |
| Conversation client | Local | Oui | Non |
| Messages email | Local | Oui | Non |
| Notes internes | Local | Oui | Non |
| Rendez-vous métier | Local si module actif | Oui | Interface publique seulement |
| Secrets IMAP/SMTP | Environnement privé | Intégration chiffrée par organisation | Non |

« Local » signifie ici : l'installation autonome est sa propre source de
vérité. Cela ne constitue pas une copie de Cremona, puisque cette installation
n'est pas connectée à Cremona.

## 5. Modèle fonctionnel cible

### 5.1 Conversation

Une conversation représente un fil cohérent avec un contact. Elle possède au
minimum :

- un contact ou une personne ;
- éventuellement une demande entrante ;
- un canal initial ;
- un sujet normalisé ;
- un statut ;
- un responsable ;
- les dates du premier et du dernier message ;
- une référence publique non sensible ;
- un état d'archivage.

Une demande et une conversation ne sont pas la même chose :

- la demande porte le traitement métier, la qualification, le statut et le
  résultat ;
- la conversation porte les échanges avec le client ;
- les deux peuvent être liées ;
- une fiche contact agrège demandes, conversations, notes et rendez-vous.

### 5.2 Message

Un message entrant ou sortant doit conserver au minimum :

- direction ;
- canal ;
- expéditeur ;
- destinataires `To`, `Cc`, `Bcc` et `Reply-To` selon le cas ;
- objet ;
- corps texte et éventuellement HTML assaini ;
- `Message-ID` ;
- `In-Reply-To` ;
- `References` ;
- identifiant IMAP du dossier et UID ;
- état de livraison ;
- dates de création, envoi, réception et synchronisation ;
- utilisateur auteur si le message vient de l'administration ;
- empreinte d'idempotence ;
- pièces jointes privées associées.

Ne pas mettre toute cette structure dans un unique champ JSON universel. Les
participants multiples et les pièces jointes méritent des modèles dédiés.

### 5.3 Activité CRM

Les activités CRM restent distinctes des messages :

- demande reçue ;
- lecture ;
- changement de statut ;
- attribution ;
- rattachement d'un contact ;
- note interne ;
- email envoyé ou reçu comme événement de projection.

Le contenu complet de l'email appartient au message, pas à l'activité.

## 6. Règles de synchronisation email

### 6.1 Dossiers concernés

Synchroniser initialement :

- `INBOX` ;
- le dossier réel des messages envoyés détecté sur le serveur.

Ne pas importer automatiquement :

- Spam/Junk ;
- Trash/Deleted ;
- Drafts ;
- newsletters et notifications système explicitement exclues.

### 6.2 Rattachement d'un fil

Ordre recommandé :

1. `In-Reply-To` correspondant à un `Message-ID` connu ;
2. élément de `References` correspondant à un message connu ;
3. identifiant externe déjà connu ;
4. participants, sujet normalisé et fenêtre temporelle ;
5. proposition de rattachement manuel si plusieurs résultats sont possibles ;
6. nouvelle conversation si aucun rattachement fiable n'est possible.

Ne jamais fusionner silencieusement deux dossiers ouverts uniquement parce
qu'ils utilisent la même adresse email.

### 6.3 Idempotence IMAP

Conserver par boîte et dossier :

- `UIDVALIDITY` ;
- dernier UID traité ;
- identifiant du message ;
- empreinte de secours.

Relancer deux fois une synchronisation ne doit créer aucun doublon.

### 6.4 Messages envoyés hors administration

Le dossier `Sent` doit être synchronisé afin qu'une réponse envoyée depuis le
webmail, le téléphone ou un client email apparaisse dans la chronologie.

Un email envoyé depuis l'administration doit :

1. être enregistré comme message en préparation ;
2. être envoyé en file d'attente ;
3. recevoir son `Message-ID` ;
4. passer à l'état accepté ou échoué ;
5. être rapproché de la copie du dossier `Sent` sans duplication.

SMTP « accepté » ne signifie pas « livré ». Ne pas afficher un statut trompeur.

### 6.5 Formulaire public

Pour les installations autonomes :

```text
Formulaire
→ contact
→ demande
→ conversation
→ message initial
→ alerte au cabinet
→ confirmation éventuelle au visiteur
```

L'alerte technique envoyée au cabinet ne doit pas être réimportée comme un
message du client.

Pour Maracuja CMS multi-site :

```text
Formulaire
→ outbox technique locale
→ API Cremona idempotente
→ contact/demande/conversation/message dans Cremona
→ accusé de transmission
→ purge selon politique de rétention
```

## 7. Sécurité et confidentialité

- Cremona : credentials dans `OrganizationIntegration`, chiffrés et isolés par
  `organization_id`.
- Site autonome : secrets dans `.env`, jamais dans les réglages éditables par
  le client.
- Permissions distinctes pour lire, répondre, rattacher, supprimer et
  configurer une boîte.
- Marcos peut gérer ses contacts et correspondances, mais ne doit pas accéder
  aux secrets ni aux réglages techniques globaux.
- Pièces jointes dans un stockage privé, jamais dans `public/`.
- Validation du type et de la taille des pièces jointes.
- HTML des emails assaini avant affichage.
- Aucun chargement automatique d'image distante de suivi.
- Journal d'audit pour la configuration, le rattachement manuel et les actions
  destructrices.
- Politique explicite de rétention, export et suppression.
- Verrou et limitation par boîte pour éviter deux synchronisations concurrentes.
- Les opérations réseau passent par une file avec reprise et temporisation.

## 8. Stratégie de partage du code

Ne pas créer immédiatement un gros package Laravel contenant modèles, migrations
et interface Filament. Cremona est multi-organisation alors que le starter et
Marcos sont mono-site ; leurs règles de persistance et de permissions diffèrent.

Procéder ainsi :

1. vocabulaire, comportements et cas de test communs ;
2. implémentation canonique dans Cremona ;
3. adaptation autonome dans le starter ;
4. portage sélectif dans Marcos ;
5. après validation des deux formes, extraction éventuelle d'un petit package
   privé limité aux objets de transport, parsing RFC, normalisation des en-têtes
   et primitives IMAP.

Le package futur ne doit pas connaître `organization_id`, `site_id`, Filament ou
les modèles métier des applications.

## 9. Lots du chantier

### Lot 1 — Spécification et décisions de modèle

- revalider ce document contre le code courant ;
- choisir les noms définitifs des modèles et tables ;
- définir les relations exactes ;
- définir les états de conversation, message et synchronisation ;
- définir la visibilité et les permissions ;
- définir la politique de rétention ;
- produire un ADR concis dans Cremona ;
- ne pas coder avant validation de ces décisions.

### Lot 2 — Domaine Correspondance dans Cremona

- modèles et migrations avec `organization_id` ;
- politiques par rôle ;
- conversations et messages ;
- participants et pièces jointes privées ;
- liens vers personne, entreprise et demande ;
- services de création et rattachement ;
- chronologie dans les fiches CRM ;
- éditeur de réponse avec transport factice ;
- tests métier, Filament et anti-fuite.

### Lot 3 — Connecteur IMAP/SMTP Cremona

- type d'intégration email ;
- formulaire de configuration sans réexposer les secrets ;
- test de connexion ;
- synchronisation `INBOX` et `Sent` ;
- threading et idempotence ;
- envoi asynchrone ;
- santé de la connexion ;
- erreurs visibles et relance ;
- commandes planifiées ;
- tests avec transport factice et fixtures RFC réalistes.

### Lot 4 — Version autonome dans Maracuja CMS Starter

- adapter le modèle au mono-site ;
- prolonger prudemment `Conversations` au lieu de créer un deuxième système
  concurrent ;
- relier les formulaires aux conversations ;
- réponse intégrée ;
- IMAP/SMTP par environnement ;
- permissions génériques ;
- commandes de synchronisation et diagnostic ;
- documentation d'installation ;
- tests de non-régression de tous les modules concernés.

### Lot 5 — Pilote Marcos Túlio

- comparer le starter et les personnalisations Marcos avant chaque portage ;
- porter uniquement les commits nécessaires ;
- préserver le portugais et les champs avocat ;
- préserver `client_manager` et les restrictions existantes ;
- sauvegarder la base avant migration ;
- configurer la boîte Hostinger existante sans changer son mot de passe ;
- installer la tâche planifiée Hostinger ;
- tester le formulaire, l'admin, `INBOX`, `Sent`, le webmail et le téléphone ;
- vérifier le responsive et les autres fonctions avant livraison ;
- commit local intentionnel et déploiement Hostinger après validation.

### Lot 6 — Contrat du nouveau Maracuja CMS

- définir l'outbox de transmission ;
- appeler l'API Cremona avec idempotence ;
- gérer les indisponibilités sans perdre un formulaire ;
- journaliser le statut technique sans recopier le CRM ;
- tests avec au moins deux sites et deux organisations ;
- aucun couplage direct aux tables ou modèles Cremona.

### Lot 7 — Stabilisation et extraction éventuelle

- comparer les deux implémentations réelles ;
- supprimer les divergences de comportement ;
- extraire uniquement le noyau transport/parsing si le gain est démontré ;
- documenter la compatibilité et les migrations ;
- ajouter supervision, sauvegarde et procédure de restauration.

## 10. Critères d'acceptation globaux

Le chantier n'est pas terminé tant que les scénarios suivants ne sont pas
automatisés ou vérifiés :

1. un formulaire crée contact, demande, conversation et message initial ;
2. le professionnel voit la demande et le fil ;
3. il répond depuis l'administration et le client reçoit l'email ;
4. le client répond et le message revient dans le même fil ;
5. une réponse depuis le webmail ou le téléphone remonte depuis `Sent` ;
6. une nouvelle adresse entrante peut créer un contact et une demande selon la
   politique choisie ;
7. plusieurs dossiers du même contact ne sont pas fusionnés abusivement ;
8. un rattachement ambigu est présenté à un humain ;
9. un rattachement erroné peut être corrigé ;
10. une synchronisation rejouée reste idempotente ;
11. une erreur SMTP ou IMAP est visible et relançable ;
12. les notifications techniques ne créent pas de faux échanges ;
13. les messages envoyés dans l'admin ne sont pas dupliqués lors de la relève de
    `Sent` ;
14. les pièces jointes restent privées ;
15. la suppression respecte la politique de rétention ;
16. un utilisateur non autorisé ne voit ni message ni secret ;
17. deux organisations Cremona ne peuvent jamais voir ou rattacher leurs
    données respectives ;
18. Marcos continue à fonctionner sans Cremona ;
19. le nouveau Maracuja CMS ne devient pas une source CRM concurrente ;
20. les fonctions actuelles de formulaire, chat, rendez-vous et notifications
    ne régressent pas.

## 11. Hors périmètre initial

Ne pas retarder le socle pour :

- campagnes marketing ;
- scoring commercial ;
- prévisions de vente ;
- rédaction IA des réponses ;
- suivi des ouvertures et clics ;
- téléphonie ;
- synchronisation WhatsApp complète ;
- reproduction de tous les dossiers et réglages d'un webmail ;
- SSO entre Marcos et Cremona ;
- migration du site Marcos vers Maracuja CMS multi-site.

Les signatures, modèles de réponse, rappels et pièces jointes sont utiles, mais
doivent suivre le fonctionnement fiable du fil texte bidirectionnel.

## 12. Méthode de travail obligatoire

Pour chaque lot :

1. inspecter l'état réel du ou des dépôts ;
2. vérifier les worktrees avant toute modification ;
3. distinguer les faits, les décisions validées et les propositions ;
4. signaler les inconnues qui changent réellement le produit ;
5. présenter les décisions irréversibles avant de coder ;
6. modifier un périmètre limité ;
7. utiliser des migrations additives et réversibles ;
8. tester proportionnellement au risque ;
9. préserver les changements de l'utilisateur ;
10. committer uniquement les fichiers du lot ;
11. ne jamais déployer ou migrer Marcos implicitement ;
12. documenter le résultat et les écarts avant le lot suivant.

Ne pas utiliser SQLite. Les projets sont conçus et testés avec MySQL/MariaDB.

Ne jamais utiliser `migrate:fresh`, `db:wipe`, `git reset --hard` ou une commande
destructive équivalente sur les données et dépôts existants.

## 13. Première tâche du nouveau chat

Commencer uniquement par le lot 1.

Le nouveau chat doit :

1. lire entièrement ce document ;
2. vérifier en lecture seule l'état courant des quatre dépôts ;
3. lire les migrations et modèles directement concernés ;
4. confronter la proposition de modèle au CRM V1 déjà présent ;
5. produire un ADR proposé comprenant :
   - noms des modèles et tables ;
   - relations ;
   - états ;
   - propriété des données ;
   - règles de threading ;
   - règles de synchronisation ;
   - sécurité et rétention ;
   - stratégie de migration ;
6. présenter les décisions qui nécessitent une validation ;
7. ne créer aucun modèle, migration, service ou interface avant cette
   validation.

## 14. Message court pour ouvrir le nouveau chat

```text
Lis entièrement le fichier :
/Users/ivocorreiademelo/Sites/cremona/docs/chantier-correspondance-client.md

Il contient les décisions validées, l'audit des quatre dépôts et le chantier de
correspondance client centralisée. Respecte l'autonomie du site Marcos Túlio et
ne recrée pas le CRM V1 de Cremona.

Commence uniquement par le lot 1 et produis l'ADR proposé. Travaille d'abord en
lecture seule et ne code rien avant de me présenter les décisions de modèle à
valider.
```

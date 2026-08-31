# Démarrage du chantier Cremona + Maracuja CMS

> Document de passation pour reprendre le chantier dans un nouveau chat.
> État constaté le 14 août 2026.

## 1. Mission

Construire un ensemble professionnel capable de servir à terme une centaine de
clients, notamment des luthiers, sans maintenir cent copies divergentes du même
code.

La décision validée est de construire deux applications Laravel multi-clients :

1. **Cremona**, pour la gestion des entreprises et de leur activité ;
2. **Maracuja CMS**, pour leurs sites publics.

Les deux applications possèdent des bases MySQL séparées et communiquent par une
API sécurisée.

```text
Cremona
Gestion multi-client
Base MySQL Cremona
        ↕ API sécurisée
Maracuja CMS
Sites multi-clients
Base MySQL Maracuja CMS
```

Une organisation Cremona peut être associée à un ou plusieurs sites Maracuja
CMS. Le cas initial sera généralement une organisation et un site, mais le
schéma ne doit pas imposer cette limite.

## 2. Vocabulaire validé

Ces noms doivent rester stables dans les prochaines discussions.

| Terme | Définition |
| --- | --- |
| **Cremona** | Application SaaS multi-organisation consacrée à la gestion. |
| **Noyau Cremona** | Organisations, utilisateurs, membres, rôles, permissions, abonnements et activation des modules. Ce n’est pas une application séparée. |
| **Module commun Cremona** | Capacité transversale : CRM, rendez-vous, tâches, documents, devis ou marketing. |
| **Module métier Cremona** | Capacité propre à une profession, par exemple instruments et interventions pour les luthiers. |
| **Maracuja CMS** | Nouvelle application multi-site consacrée aux contenus et aux sites publics. |
| **Module commun Maracuja CMS** | Pages, actualités, articles, médias, galerie, SEO, formulaires publics, etc. |
| **Module propre à un site** | Extension publique réellement spécifique à un projet. |
| **Maracuja CMS Starter** | Dépôt existant, aujourd’hui mono-site. Il sert de source à auditer et de référence pour les sites déjà livrés. Ce n’est pas l’architecture cible pour cent clients. |
| **Site client** | Site logique dans Maracuja CMS, avec son domaine, ses contenus, son thème, ses réglages et ses modules activés. |
| **Service externe** | OpenAI, Brevo, Stripe, Google Ads, Meta Ads ou autre prestataire connecté. |

Ne pas réintroduire comme noms de produits ou d’applications distinctes :

- Cremona Platform ;
- Cremona Applications ;
- Cremona Gestion ;
- Cremona Publishing ;
- runtime public.

S’il faut parler d’une responsabilité interne, employer simplement `noyau
Cremona`, `module Cremona`, `Maracuja CMS` ou `site public`.

## 3. Positionnement des deux applications

### Cremona

Cremona est l’outil de gestion complet. Il doit contenir :

- organisations, membres, rôles et permissions ;
- CRM : personnes, entreprises, demandes et suivi ;
- rendez-vous ;
- tâches et échéances ;
- conversations et qualification assistée par IA ;
- documents privés ;
- devis et propositions commerciales ;
- relation client, segments et campagnes ;
- marketing et attribution ;
- modules métier, en commençant par le pack Luthier.

### Maracuja CMS

Maracuja CMS administre et affiche les sites publics. Il doit contenir :

- sites et domaines ;
- paramètres et identité du site ;
- thèmes ;
- pages et micro-contenus ;
- actualités et articles ;
- médias et galeries publics ;
- navigation et SEO ;
- formulaires et interfaces publiques ;
- modules publics propres à certains sites.

### Services externes

Les prestataires externes restent responsables de leurs fonctions :

- OpenAI : traitement par IA ;
- Brevo : envoi d’e-mails et éventuellement rendez-vous ;
- Stripe ou équivalent : paiements et abonnements ;
- Google Ads et Meta Ads : diffusion et facturation publicitaire.

Cremona peut configurer, orchestrer et mesurer ces services, mais ne doit pas les
réimplémenter.

## 4. Propriété préliminaire des données

Cette matrice sert de point de départ. Elle doit être vérifiée et validée avant
la conception des API.

| Donnée ou fonction | Responsable cible |
| --- | --- |
| Organisations, utilisateurs, rôles et droits | Cremona |
| Activation des modules et abonnement | Cremona |
| Contacts, entreprises et demandes entrantes | Cremona |
| Rendez-vous, tâches et échéances | Cremona |
| Conversations, messages et qualification | Cremona |
| Documents privés | Cremona |
| Devis et propositions commerciales | Cremona |
| Campagnes, origine des contacts et résultats | Cremona |
| Instruments, dossiers juridiques ou chantiers | Module métier Cremona |
| Sites, domaines et thèmes | Maracuja CMS |
| Pages, textes, actualités et articles | Maracuja CMS |
| Médias et galeries publics | Maracuja CMS |
| Navigation et SEO | Maracuja CMS |
| Définition et affichage d’un formulaire public | Maracuja CMS |
| Demande produite par un formulaire | Cremona |
| Donnée métier publiée, par exemple un instrument | Source dans Cremona, exposition contrôlée dans Maracuja CMS |

Règles :

- aucune application ne lit directement la base de l’autre ;
- une donnée possède un seul système responsable ;
- les copies nécessaires à l’affichage sont des projections contrôlées, pas une
  seconde source de vérité ;
- les médias publics et les documents privés doivent rester séparés ;
- les formulaires publics ne doivent pas stocker durablement les données métier
  sensibles dans Maracuja CMS.

## 5. Modèle prévu pour cent clients

Le modèle cible n’est pas une copie Laravel par client.

### Cremona

- une application partagée ;
- une base MySQL partagée au départ ;
- une ligne `organization_id` sur toutes les données appartenant à un client ;
- isolation fermée par défaut ;
- modules activés par organisation.

### Maracuja CMS

- une application partagée ;
- une base MySQL partagée au départ ;
- une ligne `site_id` sur les contenus appartenant à un site ;
- résolution du site par son domaine ;
- thème, paramètres et modules activés par site ;
- un déploiement commun pour corriger ou faire évoluer tous les sites.

Le sur-mesure reste possible par thème et par module isolé. Une particularité ne
doit jamais être ajoutée au noyau sous forme de condition globale portant le nom
d’un client.

## 6. Situation actuelle

### Dépôt Cremona

Chemin : `/Users/ivocorreiademelo/Sites/cremona`

État constaté :

- Laravel `^13.17` ;
- PHP `^8.4.1` ;
- Filament `^4.0` ;
- MySQL uniquement ;
- branche `main` propre ;
- premier commit : `e79d7eb Initialize Cremona SaaS foundation`.

Fondation présente :

- `Organization` ;
- `OrganizationMembership` ;
- `OrganizationModule` ;
- rôles initiaux ;
- `OrganizationContext` ;
- global scope organisation ;
- trait `BelongsToOrganization` ;
- middleware de sélection de l’organisation ;
- tests d’accès et d’isolation entre organisations.

Cremona ne possède encore aucun module métier complet.

### Dépôt Maracuja CMS Starter

Chemin : `/Users/ivocorreiademelo/Sites/maracuja-cms-starter`

État constaté :

- application Laravel/Filament mono-site ;
- une installation prévue par client ;
- branche `main` propre mais quatre commits en avance sur `origin/main` ;
- modules activés actuellement par configuration et variables d’environnement.

Modules ou briques présents :

- `SiteSettings` ;
- `Pages` ;
- `ContentSlots` ;
- `Notices` ;
- `News` ;
- `Articles` ;
- `Media` ;
- `Gallery` ;
- `ContactForm` ;
- `Inquiries` ;
- `Contacts` ;
- `Conversations` ;
- `Appointments` ;
- `Audience` ;
- `Events` ;
- `Venues`.

`Campaigns` apparaît dans la configuration mais n’est pas un module implémenté.

Ces modules ne doivent pas être copiés en bloc. Chacun doit être classé :

- conserver dans Maracuja CMS ;
- adapter pour Cremona ;
- partager uniquement sous forme de contrat ;
- remplacer ;
- abandonner.

### Site Marcos Túlio

Chemin : `/Users/ivocorreiademelo/Sites/marcos-tulio-advocacia`

Décision validée : le livrer dans sa structure autonome actuelle. Ne pas
l’embarquer maintenant dans la transformation multi-client. Il pourra devenir
un cas de migration ultérieur, une fois les deux nouveaux socles stabilisés.

Son worktree contient actuellement des modifications et fichiers non suivis.
Ne pas le modifier dans ce chantier sans demande explicite.

## 7. Stratégie de dépôt à valider

Recommandation :

- conserver `maracuja-cms-starter` pour la maintenance des sites autonomes déjà
  créés et comme source de référence ;
- créer un nouveau dépôt/dossier `maracuja-cms` pour l’application multi-site ;
- reprendre sélectivement les briques validées du starter ;
- éviter de transformer brutalement le starter, ce qui compliquerait la
  maintenance de Marcos Túlio, Ivo Incidit et des autres sites existants.

Cette recommandation doit être validée avant de créer le nouveau dossier.

## 8. Plan de travail

### Étape 1 — Cadrage et audit, sans code

- comparer Cremona et le starter ;
- dresser la liste exacte des modules et modèles ;
- attribuer chaque donnée à Cremona ou Maracuja CMS ;
- repérer les concepts concurrents, notamment contacts et demandes ;
- décider ce qui est repris, adapté, remplacé ou abandonné ;
- valider la stratégie du nouveau dépôt `maracuja-cms` ;
- définir le premier parcours vertical.

### Étape 2 — Consolider le noyau Cremona

- organisations et membres ;
- rôles et permissions fines ;
- contexte organisation ;
- activation des modules ;
- super-administration ;
- journal d’audit ;
- secrets d’intégration chiffrés ;
- tests anti-fuite.

### Étape 3 — Premier CRM transversal

- personnes ;
- entreprises ;
- coordonnées ;
- demandes entrantes ;
- origine et canal ;
- responsable ;
- statut ;
- notes internes ;
- consentements ;
- historique.

Le CRM ne doit pas adopter le vocabulaire d’une profession particulière.

### Étape 4 — Fondation multi-site de Maracuja CMS

- sites ;
- domaines ;
- contexte du site actif ;
- isolation par `site_id` ;
- thèmes et paramètres ;
- modules par site ;
- comptes et accès à l’administration ;
- premières briques éditoriales ;
- rendu de plusieurs domaines par une même application.

### Étape 5 — Identité et API

- correspondance organisation/site ;
- stratégie d’authentification entre applications ;
- éventuelle connexion unique pour le client ;
- droits minimaux par site ;
- requêtes authentifiées ;
- idempotence ;
- limitation de débit ;
- journalisation et reprise sur erreur ;
- versionnement de l’API.

### Étape 6 — Première chaîne complète

```text
Visiteur
→ formulaire Maracuja CMS
→ demande créée dans Cremona
→ notification
→ prise en charge
→ création ou rapprochement du contact
→ suivi CRM
```

Ce parcours est le premier jalon utilisable. Il doit fonctionner avec au moins
deux organisations et deux sites de test sans fuite de données.

### Étape 7 — Modules communs prioritaires

À ajouter progressivement, après validation du parcours précédent :

1. rendez-vous ;
2. tâches et échéances ;
3. conversations et qualification IA ;
4. documents privés ;
5. devis ;
6. relation client et Brevo ;
7. marketing et attribution publicitaire.

### Étape 8 — Premier pack Luthier

Périmètre à cadrer avec de vrais besoins :

- musiciens et propriétaires ;
- instruments ;
- archets ;
- interventions et restaurations ;
- historique ;
- photos et documents privés ;
- relations avec rendez-vous et devis ;
- publication contrôlée de certaines informations sur le site.

Le module Archets d’Ivo Incidit est une référence fonctionnelle, pas un code à
copier sans adaptation multi-organisation.

### Étape 9 — Industrialisation

- création guidée d’une organisation et de son site ;
- domaines et certificats HTTPS ;
- déploiements automatisés ;
- sauvegardes et restauration testée ;
- files d’attente et tâches planifiées ;
- stockage des médias ;
- supervision et alertes ;
- environnements de test et de préproduction ;
- export, portabilité et suppression des données ;
- conformité RGPD ;
- quotas et mesure des consommations externes ;
- facturation.

### Étape 10 — Pilotes

- utiliser d’abord des organisations et sites de démonstration ;
- intégrer un premier luthier pilote ;
- corriger le modèle à partir des usages réels ;
- migrer éventuellement Ivo Incidit puis Marcos Túlio seulement après
  stabilisation ;
- ne construire les packs Avocat et Artisan qu’après validation du socle.

## 9. Premier MVP attendu

Le premier MVP ne doit pas chercher à contenir tous les modules. Il doit prouver
les points suivants :

1. deux organisations coexistent sans fuite dans Cremona ;
2. deux sites et deux domaines coexistent sans fuite dans Maracuja CMS ;
3. chaque site possède son identité et son contenu ;
4. un formulaire public crée une demande dans la bonne organisation Cremona ;
5. le client voit et traite cette demande dans Cremona ;
6. l’opération est journalisée et résiste aux doublons ;
7. une mise à jour du code peut bénéficier aux deux clients sans recopier les
   applications.

## 10. Principes techniques obligatoires

- Utiliser MySQL. Ne pas introduire SQLite, même pour les tests.
- Commencer par deux monolithes Laravel modulaires, pas par des microservices.
- Fermer l’accès aux données par défaut en l’absence d’organisation ou de site
  actif.
- Ajouter des tests anti-fuite pour chaque nouveau modèle multi-client.
- Utiliser des contraintes de base composites lorsque l’unicité dépend du
  client.
- Ne jamais placer des données sensibles dans des médias publics.
- Chiffrer les secrets d’intégration et permettre leur révocation.
- Traiter en file d’attente les opérations lentes ou externes.
- Concevoir les appels API pour être rejouables sans créer de doublons.
- Ne pas utiliser un champ JSON universel à la place de vrais modèles métier.
- Ne pas créer un module `Products` générique pour absorber tous les catalogues.
- Conserver le vocabulaire réel de chaque métier dans son module métier.
- Les capacités communes peuvent recevoir, par pack actif, des intitulés, une
  navigation, des raccourcis et des écrans adaptés ; ces adaptations ne changent
  ni leur modèle canonique ni leurs règles d'isolation.
- Ne jamais coder le nom d'un métier, d'un client ou d'un objet métier comme
  condition dans le noyau. Les exemples de vocabulaire servent au cadrage, pas
  de valeurs applicatives.
- Préserver les changements existants dans tous les worktrees.
- Ne pas déployer, migrer un site existant ou modifier Marcos Túlio sans demande
  explicite.

## 11. Méthode de collaboration demandée

Le propriétaire du projet souhaite être guidé point par point et comprendre les
décisions. Il ne veut ni acquiescement automatique ni architecture improvisée.

Pour chaque étape :

1. inspecter l’existant ;
2. expliquer le constat avec des faits ;
3. distinguer ce qui est validé de ce qui est proposé ;
4. signaler les inconnues ;
5. demander une décision si plusieurs choix changent réellement le produit ;
6. ne coder qu’après validation du périmètre ;
7. implémenter une étape limitée ;
8. tester ;
9. présenter le résultat avant de poursuivre.

Éviter de renommer les composants au fil de la discussion. Si un changement de
nom est nécessaire, présenter explicitement l’ancien nom, le nouveau nom et la
raison, puis attendre validation.

## 12. Travail à effectuer dans le nouveau chat

Commencer uniquement par **l’étape 1 : cadrage et audit**.

Lire intégralement ce document, puis inspecter en lecture seule :

- `/Users/ivocorreiademelo/Sites/cremona` ;
- `/Users/ivocorreiademelo/Sites/maracuja-cms-starter`.

Produire ensuite trois tableaux concis :

1. modules existants et destination proposée ;
2. propriété des données entre Cremona et Maracuja CMS ;
3. écarts entre l’existant et le premier MVP.

Le rapport doit également confirmer ou contester, avec justification, la
création d’un nouveau dépôt `maracuja-cms`.

Ne créer aucun fichier, modèle, migration ou dépôt supplémentaire pendant cet
audit. Attendre la validation explicite avant de commencer l’implémentation.

## 13. Message court pour ouvrir le nouveau chat

```text
Lis entièrement le fichier
/Users/ivocorreiademelo/Sites/cremona/docs/demarrage-cremona-maracuja-cms.md.

Il contient les décisions validées, l’architecture cible, l’état actuel et la
méthode de travail. Commence uniquement par l’étape 1, en lecture seule. Produis
les trois tableaux demandés et ne code rien avant ma validation.
```

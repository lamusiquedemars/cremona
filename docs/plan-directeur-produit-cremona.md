# Plan directeur produit — écosystème Maracuja

> **Référence active — mise à jour le 4 novembre 2026.** Ce plan remplace les
> priorités dispersées entre Cremona et les sites. Les anciens comptes rendus
> décrivent leur contexte historique, mais ne valent pas procédure active.

## 1. Intention

Maracuja vend des sites simples à administrer et, lorsque l'activité le
justifie, un espace de gestion métier intégré dans l'expérience du client. Le
client ne voit pas les noms internes « Cremona », « Site Core » ou « Control ».

Le produit ne crée jamais deux backoffices pour une même donnée : chaque objet
a un propriétaire, un cycle de vie et, si nécessaire, une projection lecture
seule vers l'autre application.

## 2. Architecture cible

```text
Maracuja Site Core ── modules versionnés réutilisables
          │
          ├── Maracuja Site ── une application dédiée par client
          │       pages, contenus, médias éditoriaux, SEO, formulaires publics
          │
          ├── Cremona ── organisations, relation client, activité commerciale
          │       et packs métier
          │
          └── Maracuja Control ── inventaire, santé et exploitation du parc
```

- **Maracuja Site Core** fournit les capacités techniques partagées, sans
  héberger de données client.
- **Maracuja Site** reste une instance par client, avec sa base, ses fichiers,
  son domaine et son thème.
- **Cremona** est multi-organisations et porte les données d'activité : CRM,
  devis, documents métier et objets propres aux packs.
- **Maracuja Control** opère le parc ; il n'est ni un CMS ni un CRM.

L'ancien essai multi-site est retiré. Toute documentation qui le décrit comme
un produit actif doit être corrigée ou marquée comme archive.

## 3. Propriété des données

| Domaine | Propriétaire | Rôle de l'autre application |
| --- | --- | --- |
| Pages, navigation, SEO, actualités, galeries éditoriales | Maracuja Site | Cremona peut afficher un état ou un lien, jamais les éditer |
| Formulaire public, validation, anti-spam, accusé de réception | Maracuja Site | transmet une demande fiable si le site est connecté |
| Trace d'envoi et outbox | Maracuja Site | preuve technique limitée, jamais seconde boîte de réception |
| Clients, demandes, e-mails directs, correspondances, tâches, rendez-vous, devis | Cremona | le site peut ouvrir la gestion ou afficher un résumé non éditable |
| Instruments, locations, stock, prestations et dossiers atelier | Cremona / pack Luthier | projection publique contrôlée vers le site |
| Archets Atelier Ivo | à décider après cartographie métier | ne pas créer deux catalogues avant cette décision |
| Médias d'un objet métier | Cremona pour l'original métier | le site reçoit une rendition publique autorisée |
| Documents privés | Cremona | aucun accès public par défaut |
| Consentement marketing, listes et campagnes | Maracuja Site + Audience / Brevo | Cremona reçoit seulement les signaux nécessaires et consentis |
| Facturation et encaissement bancaire | outil comptable ou bancaire choisi | Cremona suit l'état utile, sans seconde comptabilité ni données SEPA |

Une projection porte un identifiant de source, une version, une empreinte et
un état de publication. Elle est rejouable et idempotente, mais n'est jamais
une copie modifiable librement.

## 4. Deux offres de site

| Offre | Maracuja Site | Cremona |
| --- | --- | --- |
| Site vitrine autonome | contenus, médias, SEO, formulaires et gestion locale adaptée | absent |
| Site connecté à la gestion | mêmes capacités publiques, plus bridge et lien de gestion | relation client, activité commerciale et modules métier |

Dans le profil connecté, le site garde le formulaire public, mais Cremona
devient propriétaire du traitement de la demande. Il ne doit pas rester deux
listes de demandes actives.

## 5. État réellement livré

| Capacité | État | Limite connue |
| --- | --- | --- |
| CRM, clients unifiés, demandes, correspondances, tâches, rendez-vous | livré | les règles de rapprochement à partir d'emails, d'appels et de rencontres doivent être éprouvées |
| Devis, dossiers atelier et PDF | livré | confirmer sur un dossier réel les références, mentions et documents |
| Pack Luthier : instruments, stock, prestations, locations, assurance et contrats | livré | recette réelle Contempo à faire ; pas de banque, Dynamics, SEPA ou signature qualifiée |
| Tableau de bord et alertes de location | livré | ajustement par l'usage réel |
| Utilisateurs, profils et récupération de mot de passe | livré pour Maracuja | délégation des membres à un client non conçue |
| Bridge Site → Cremona | déployé et versionné | recette formelle à deux organisations encore à faire |
| Projection publique d'instruments Contempo | pilote livré | contrat de projection, médias et exploitation à stabiliser |
| Site Core : Foundation, Media, Gallery, Publication, Events, Contact | extrait et adopté sur le site pilote | adoption client par client incomplète |
| Audience / Brevo | packages livrés | données historiques et recette complète par site à faire |
| Google Ads | module Cremona actif | GA4 et Search Console non connectés |

## 6. Backlog unique et ordre de réalisation

### P0 — décisions qui précèdent toute extension

1. **Cartographie Atelier Ivo.** Décrire les trois parcours réels : demande
   d'archet, essai, proposition/vente. Pour chacun, choisir le propriétaire de
   la donnée et les projections utiles.
2. **Architecture SSO et backoffice unifié.** Décider avant code : identité de
   référence, invitation, passage Site ↔ Cremona, retour, révocation, sessions,
   droits et apparence. Le bridge actuel n'est pas du SSO. Aucun sous-domaine,
   iframe ou partage de session ne doit être choisi par commodité.
3. **Registre produit.** Faire converger groupes de navigation, modules,
   dépendances, droits et libellés éditables vers une définition canonique.
4. **Nettoyage documentaire.** Remplacer les références actives à l'ancien
   produit multi-site par Maracuja Site / Site Core ou marquer le document
   comme archive.

### P1 — valider le produit déjà construit

1. **Recette relation client Atelier.** Formulaire → bridge → demande Cremona,
   email direct, appel, tâche, rendez-vous, réponse et devis. Définir les
   gestes quotidiens avant toute automatisation de rapprochement.
2. **Recette commerciale Atelier.** Créer un vrai dossier atelier et un devis ;
   confirmer numérotation, PDF, identité légale et suivi.
3. **Recette Location Contempo.** Contrat, assurance facultative, accord,
   préparation, remise, encaissement manuel, restitution et action bancaire
   interne. Corriger seulement les règles mises en défaut par le réel.
4. **Recette du bridge sur deux organisations.** Succès, doublon, panne,
   reprise, jeton révoqué, référence de site refusée et absence de fuite.
5. **Qualité transversale.** Corriger les quatre échecs préexistants de la
   suite Cremona avant d'en faire une garde de livraison.

### P2 — industrialiser Site et exploitation

1. **Adoption Site Core par site.** Contrat de présentation, migrations de
   données réversibles puis adoption Composer : Atelier, Un Violon, Contempo
   et Marcos, sans copie de dossiers.
2. **Connecteur Site Core.** Remplacer progressivement les bridges propres aux
   sites après la recette P1 ; rendre l'état de transmission observable.
3. **Catalogues métier.** Formaliser la projection Cremona → Contempo ; créer
   le pack Archets seulement après le cadrage Atelier.
4. **Audience / Brevo.** Réparer les liens historiques, puis valider campagne,
   consentement, désinscription, bounce et rejeu webhook sur un pilote.
5. **Maracuja Control.** Inventaire des installations, versions, modules,
   sauvegardes testées, santé, certificats, cron, files, webhooks et alertes.

### P3 — capacités à engager après les fondations

- véritable **Agenda** : agrégation de rendez-vous, tâches datées, essais,
  appels et rappels, sans doublon ;
- **Présence en ligne** : vue de pilotage des sites et contenus, sans second
  CMS ;
- **Résultats** : audit comparatif Ads, GA4, Search Console et attribution
  commerciale avant tout écran ;
- **IA d'assistance** : résumé, qualification ou brouillon éditable seulement,
  avec validation humaine, isolation et traçabilité ;
- délégation limitée de la gestion des membres aux administrateurs client.

## 7. Règles de livraison

1. Partir d'un cas réel, puis cartographier données, droits, écrans,
   intégrations, tâches planifiées et tests.
2. Fixer le propriétaire de chaque donnée avant de créer une synchronisation.
3. Toute migration est additive et contrôlée ; aucun historique n'est supprimé
   par une bascule.
4. Un site ne reçoit jamais une copie de dossier : une capacité réutilisable
   devient un package Site Core ou un module Cremona versionné.
5. Une intégration externe est explicite, auditée et séparée des menus métier.
6. Une capacité ne devient vendable qu'après recette réelle, procédure de
   reprise et preuve de sécurité adaptée.

## 8. Documents de référence

- [Blueprint des sites autonomes et connectés](blueprint-sites-connectes.md)
- [Contrat du bridge Site ↔ Cremona](contrat-bridge-site-cremona.md)
- `maracuja-site-core/docs/adr/001-topologie-produit.md`
- `maracuja-site-core/docs/architecture/propriete-des-donnees.md`
- `maracuja-site-core/docs/architecture/roadmap-migration.md`

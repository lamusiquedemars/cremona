# Flux métier de référence — Atelier Ivo

> Statut : cadrage fondé sur le site et les règles actuellement publiées ; décisions ouvertes à valider avec Ivo avant construction du module métier.
>
> Dernière mise à jour : 8 septembre 2026.

## 1. Sources observées

Le cadrage part des capacités réelles du site Atelier Ivo :

- le formulaire public demande un email et un message, avec nom, téléphone et sujet selon la configuration ;
- la transmission à Cremona contient le contact, le sujet, le message, le site source et l’attribution publicitaire ;
- le catalogue `Arcus` possède un code stable, un statut, un prix, une famille, un instrument, des caractéristiques de jeu et des médias ;
- les statuts actuels d’un archet sont `Disponible`, `En essai`, `Indisponible` et `Vendu` ;
- l’essai peut avoir lieu sur rendez-vous à Lyon ou, après échange, par expédition ;
- le site annonce actuellement 14 jours pour un essai à distance ;
- une commande sur mesure peut demander un acompte d’environ un tiers et annonce un délai indicatif de fabrication de une à deux semaines.

Ces éléments décrivent l’usage constaté. Les durées, montants et conditions juridiques restent des règles administrables ou des données de document, jamais des constantes codées dans le noyau.

## 2. Frontière des responsabilités

### Situation actuelle à préserver

- Le site Atelier reste autonome à l’exécution et conserve son catalogue `Arcus` actuel.
- Une indisponibilité de Cremona ne bloque ni le formulaire ni l’administration du site.
- Cremona reçoit les demandes et gère la relation client, les correspondances, tâches, rendez-vous, devis et documents privés.
- Aucun changement de source de vérité n’est réalisé sans contrat de synchronisation, inventaire et plan de reprise.

### Cible validée pour Atelier Ivo

- Le catalogue `Arcus` du CMS reste propriétaire des archets et de leur
  publication. Il n'est pas déplacé dans Cremona sans décision ultérieure,
  inventaire et migration.
- Cremona est propriétaire des demandes, contacts, correspondances, tâches,
  rendez-vous, devis et documents privés, quelle que soit leur origine.
- Lorsqu'un flux métier a besoin d'un archet, il conserve une référence stable
  au catalogue Arcus et un snapshot commercial utile ; il ne réplique pas un
  second catalogue éditable.
- Le site ne reçoit jamais les notes internes, coordonnées privées, conditions
  négociées, documents, paiements ou historique client.
- Les identifiants techniques ne contiennent ni le nom d’Atelier Ivo ni un
  vocabulaire imposé aux autres métiers.

## 3. Socle commun aux trois flux

Chaque parcours commence par une `IncomingRequest`, puis utilise les objets communs existants selon le besoin :

```text
Demande
  → contact rapproché
  → correspondance
  → qualification métier vérifiée
  → tâche, rendez-vous ou devis contextualisé
  → résultat explicite
```

Informations utiles à extraire ou demander sans les rendre toutes obligatoires :

- instrument joué : violon, alto ou violoncelle ;
- pratique, niveau ou contexte musical ;
- besoin et sensations recherchées ;
- référence d’un ou plusieurs archets repérés ;
- localisation et préférence pour Lyon ou un essai à distance ;
- délai ou contrainte particulière.

Le message original reste immuable. Toute qualification proposée depuis son texte doit être vérifiée par une personne avant enregistrement comme donnée métier.

## 4. Flux 1 — Demande et choix d’un archet

### Besoin

Un musicien cherche un conseil, a repéré un archet ou ne sait pas encore lequel essayer.

### Parcours minimal

1. La demande arrive du site ou est créée après un autre contact.
2. Le contact est créé ou rapproché sans doublon.
3. Ivo vérifie l’instrument, la pratique, le besoin et les références déjà repérées.
4. La correspondance conserve les questions et réponses.
5. Une tâche de relance est créée si une information manque.
6. La demande aboutit à une sélection conseillée, un essai, une commande, une vente ou une clôture motivée.

### Données métier candidates

- références d’archets envisagées ;
- instrument du musicien dans ce contexte, sans supposer qu’un contact n’en joue qu’un ;
- critères recherchés et points de comparaison ;
- sélection proposée et motif ;
- résultat de la demande.

### Plus petit périmètre utile

Conserver la demande, la correspondance et les tâches existantes ; ajouter une
référence vérifiée au code stable Arcus et le snapshot utile au devis. Ne pas
créer une colonne texte libre qui simule durablement cette relation ni un second
catalogue éditable dans Cremona.

## 5. Flux 2 — Essai d’un ou plusieurs archets

### Besoin

Comparer un ou plusieurs archets avec l’instrument du musicien, à Lyon ou à distance, sans obligation d’achat.

### Parcours à Lyon

1. Qualifier la demande et préparer une sélection.
2. Réserver un créneau et le lieu.
3. Associer les archets prévus à l’essai.
4. Pendant ou après le rendez-vous, noter le retour utile et la décision.
5. Libérer les archets non retenus ; poursuivre vers devis ou commande pour l’archet retenu.

### Parcours à distance

1. Valider la sélection, les coordonnées d’expédition et les conditions applicables.
2. Définir les dates d’envoi, de réception attendue, de fin d’essai et de retour.
3. Conserver les documents privés et références de transport nécessaires.
4. Créer des rappels explicites pour réception, fin d’essai et retour.
5. Enregistrer l’issue : retour, prolongation validée, achat ou commande dérivée.

### Pourquoi un objet métier est nécessaire

Un essai n’est pas seulement un rendez-vous : il peut contenir plusieurs archets, une réservation de disponibilité, une période, une expédition, un retour et un résultat. La cible devra donc prévoir un objet métier dédié, par exemple `BowTrial`, avec des lignes `BowTrialItem`. Le nom définitif appartient au module Luthier ; il ne doit pas entrer dans le noyau commun.

### Règles de cohérence

- Un archet marqué `En essai` doit être relié à un essai actif identifiable.
- Un rendez-vous annulé ne libère pas implicitement un archet expédié.
- Une tâche de rappel et une échéance d’essai peuvent représenter la même obligation ; l’interface doit éviter de les afficher deux fois comme deux événements distincts.
- La disponibilité publique est modifiée dans Arcus/CMS par une action explicite.
  Une future intégration ne pourra proposer cette modification qu'avec une
  confirmation et une trace ; elle ne doit pas créer deux propriétaires.

## 6. Flux 3 — Proposition, vente ou commande

### Vente d’un archet disponible

1. Partir de la demande ou de l’essai et du contact vérifié.
2. Créer un devis lié à l’archet concerné.
3. Envoyer le devis et suivre sa validité.
4. Enregistrer acceptation ou refus sans confondre acceptation et paiement.
5. À confirmation de la vente, conserver la date, le prix final, les conditions, les documents et la garantie applicables.
6. Marquer l’archet vendu dans Arcus/CMS, après confirmation du résultat
   commercial ; Cremona conserve son lien et son historique, sans devenir une
   seconde source de disponibilité.

### Commande sur mesure

1. Formaliser le besoin et les caractéristiques convenues.
2. Émettre une proposition ou un devis versionné.
3. Enregistrer l’acompte demandé, dû et reçu sans stocker de donnée bancaire sensible.
4. Suivre la fabrication avec des jalons métier distincts des tâches personnelles.
5. Organiser validation, livraison et solde.
6. Créer l’archet canonique et décider séparément de sa publication comme réalisation.

### Limite du devis actuel

Le devis couvre la proposition commerciale, mais pas à lui seul la réservation d’un archet, l’état d’une commande, les paiements, la livraison, la garantie ou la propriété du catalogue. Ces responsabilités doivent rester séparées, même si l’interface les présente dans une chronologie cohérente.

## 7. Premier flux vertical recommandé

Commencer par **l’essai d’archet** : il relie une demande réelle, un contact, plusieurs archets, un rendez-vous ou une expédition, des tâches et un résultat. Il permet de valider la frontière entre noyau commun, module Luthier et projection publique avant d’introduire le cycle plus sensible de commande et paiement.

Preuve de fin proposée :

- créer un essai depuis une demande ;
- sélectionner un ou plusieurs archets disponibles ;
- empêcher une réservation concurrente incohérente ;
- suivre Lyon ou distance sans doublon d’échéance ;
- relier correspondances, tâches et rendez-vous ;
- conclure par retour, achat, commande ou abandon motivé ;
- projeter explicitement la disponibilité publique sans rendre le site dépendant de Cremona.

## 8. Décisions à valider avant schéma

1. Un archet peut-il participer simultanément à plusieurs essais planifiés, ou toute réservation future bloque-t-elle les autres ?
2. Quels états distinguer entre sélectionné, réservé, remis au musicien, expédié, reçu, à retourner et retourné ?
3. Les 14 jours commencent-ils à l’expédition ou à la réception, et qui peut prolonger la période ?
4. Quelles références de transport sont nécessaires et combien de temps doivent-elles être conservées ?
5. Une vente exige-t-elle toujours un devis, et quel événement confirme réellement la vente ?
6. Comment suivre acompte et solde : simple état manuel, échéances, ou connexion future à un outil comptable ?
7. À quel moment un archet sur mesure devient-il une réalisation publiable distincte de la commande privée ?
8. Quelles données du catalogue actuel sont métier, publiques, internes ou historiques ?

Tant que ces décisions ne sont pas confirmées, aucune migration de catalogue ni automatisation de disponibilité ne doit être lancée.

# Pack Luthier — pilote Contempo

> Statut : socle livré dans Cremona et projection publique Contempo active ;
> validation des parcours réels à poursuivre. Ni import Dynamics ni connecteur
> Pennylane ne sont planifiés à ce stade.
>
> Le pack vit dans Cremona. Le CMS Contempo indépendant ne reçoit qu'une
> projection publique contrôlée.

## But

Contempo doit pouvoir traiter dans une seule chronologie une demande reçue du
site, un email direct, un appel ou une visite : qualifier le besoin, planifier
le travail, utiliser des articles de stock, produire un devis puis suivre
location, réparation ou vente. Le CRM Dynamics existant n’est pas une
dépendance de la cible et ne doit pas recevoir de connecteur.

## Objets et propriétaire

| Objet | Sens | Propriétaire |
| --- | --- | --- |
| `InstrumentAsset` | un violon, alto, violoncelle ou archet physique identifié individuellement ; vendu, loué, à l'atelier ou archivé | Pack Luthier / Cremona |
| `StockItem` | référence stockable : cordes, mentonnière, colophane, pièce d'atelier | Pack Luthier / Cremona |
| `ServiceDefinition` | prestation réutilisable : reméchage, réglage, ajustement, entretien | Pack Luthier / Cremona |
| `WorkshopOrder` | dossier d'intervention sur un instrument confié par un client | Pack Luthier / Cremona |
| `RentalContract` | mise à disposition d'un instrument, période, état initial/final, échéances | Pack Luthier / Cremona |
| `Quote` et lignes | proposition commerciale versionnée ; lignes créées depuis les objets précédents | noyau Cremona |
| fiche et médias publics | sélection présentable au public | CMS Contempo, projection depuis Cremona |

Il n'existe pas de table universelle `products`. Un instrument individuel n'a
ni le cycle ni les contraintes d'une corde ; une prestation n'est pas du stock.

## États minimaux

Un `InstrumentAsset` porte un statut opérationnel exclusif : `available`,
`reserved`, `rented`, `in_workshop`, `sold`, `archived`. Les réservations,
locations et dossiers atelier portent chacun leurs propres dates et historique ;
le statut synthétique n'efface jamais ces événements.

Un article de stock porte une quantité disponible, un seuil d'alerte et des
mouvements immuables (`receipt`, `consumption`, `sale`, `adjustment`, `return`).
La quantité ne se modifie pas directement depuis un devis brouillon.

Un dossier atelier passe de `received` à `diagnosed`, `awaiting_approval`,
`scheduled`, `in_progress`, `ready`, `returned` ou `cancelled`. Les tâches sont
liées au dossier mais restent des actions humaines ; elles ne changent pas un
état métier sans action explicite.

## Devis : une ligne issue d'un objet, jamais une tâche brute

La bibliothèque commerciale contient des modèles de lignes de devis :
description, taux de TVA, prix indicatif, unité, conditions et référence
Pennylane éventuelle. Elle alimente les devis pour éviter de réécrire les mêmes
prestations, sans devenir une source de vérité du stock.

Une personne peut choisir « Ajouter au devis » depuis :

- une `ServiceDefinition` ;
- un `StockItem` avec quantité choisie ;
- un `InstrumentAsset` vendu ou loué ;
- une étape chiffrable d'un `WorkshopOrder`.

Une `CrmTask` peut proposer cette action seulement lorsqu'elle est explicitement
qualifiée comme facturable. « Appeler un fournisseur de cordes » reste une tâche
interne et ne peut pas générer une ligne par accident. La ligne conserve un
snapshot de libellé, prix et taxe : modifier ultérieurement le tarif catalogue
ne réécrit jamais un devis déjà envoyé.

Une intégration Pennylane pourra être étudiée ultérieurement si elle est
simple, indépendante de Giovanni et apporte un bénéfice concret. Elle ne fait
pas partie du socle actuel ; Cremona reste pour l’instant propriétaire du devis
et de son PDF.

## Trois parcours pilotes

### Réparation / reméchage

`Demande ou email → WorkshopOrder → diagnostic → devis de prestations/pièces
→ accord → tâches atelier → prêt à rendre → retour`.

Les pièces consommées sortent du stock uniquement au moment validé par
l'atelier. Le client peut refuser le devis sans effacer le diagnostic ni les
échanges.

### Location

`Demande → instrument disponible → réservation → RentalContract → remise →
échéances → retour/prolongation/vente`.

L'instrument est bloqué par le contrat actif. Un éventuel consommable ou service
ajouté au contrat devient une ligne distincte ; il ne change pas l'identité de
l'instrument.

### Vente d'accessoire ou d'instrument

`Demande ou vente comptoir → devis → validation → mouvement de stock ou statut
sold`.

Le passage à `sold` dépend du résultat commercial confirmé, pas de la simple
création d'un devis.

## Projection vers le CMS Contempo

La projection est à sens unique : Cremona publie les instruments autorisés,
leur statut public, prix/mention de prix, texte et médias explicitement
sélectionnés. Elle est active pour le catalogue public Contempo. Le CMS gère
la présentation, le SEO et la mise en page mais ne modifie ni stock ni
disponibilité métier.

Le formulaire du CMS sera basculé en mode connecté : il ne créera plus une
seconde boîte `Inquiries`, mais un événement dans Cremona. La relève IMAP des
emails directs est disponible, mais son intégration dans la file de traitement
des demandes doit d’abord être terminée au niveau du produit Relation client.

## État de construction et reste à faire

Livré :

1. socle commercial : modèles de lignes, provenance d’une ligne et références
   automatiques de devis ;
2. `ServiceDefinition`, `StockItem`, mouvements de stock, `InstrumentAsset`,
   `WorkshopOrder` et location ;
3. projection publique des instruments de Cremona vers Contempo.

À valider ou construire :

1. un parcours réel de réparation/reméchage, puis les sorties de location et
   de vente, sans inventer de règles avant les usages de Giovanni ;
2. la consolidation globale des emails directs et demandes dans Cremona ;
3. le bridge des demandes du formulaire Contempo vers Cremona, pour supprimer
   la seconde file locale seulement après validation ;
4. la décision Pennylane, uniquement si son intégration est autonome et
   réellement utile. Aucun import Dynamics n’est prévu.

Chaque étape ajoute ses migrations, politiques d'organisation, tests
d'isolation, journal d'activité et procédure de reprise. Aucun import Dynamics
ni bascule de site n'est déclenché par la livraison du schéma.

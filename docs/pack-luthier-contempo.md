# Pack Luthier — pilote Contempo

> Statut : modèle cible avant migration Dynamics ou import de stock.
>
> Le pack vit dans Cremona. Le CMS Contempo indépendant ne reçoit qu'une
> projection publique contrôlée.

## But

Contempo doit pouvoir traiter dans une seule chronologie une demande reçue du
site, un email direct, un appel ou une visite : qualifier le besoin, planifier
le travail, utiliser des articles de stock, produire un devis, l'envoyer à
Pennylane puis suivre location, réparation ou vente. Le CRM Dynamics existant
reste une source de reprise, pas une dépendance de la cible.

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

Après validation commerciale, Cremona transmet le devis à Pennylane et conserve
la référence externe, le statut de synchronisation et le PDF/lien. Pennylane
reste propriétaire du document émis, de la facture et du paiement.

## Trois parcours pilotes

### Réparation / reméchage

`Demande ou email → WorkshopOrder → diagnostic → devis de prestations/pièces
→ accord → tâches atelier → prêt à rendre → facture/Pennylane → retour`.

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
sold → Pennylane`.

Le passage à `sold` dépend du résultat commercial confirmé, pas de la simple
création d'un devis.

## Projection vers le CMS Contempo

La projection est à sens unique pour la première version : Cremona publie les
instruments ou catégories autorisés, leur statut public, prix/mention de prix,
texte et médias explicitement sélectionnés. Le CMS gère leur présentation,
SEO et mise en page mais ne modifie ni stock ni disponibilité métier.

Le formulaire du CMS est ensuite basculé en mode connecté : il ne crée plus une
seconde boîte `Inquiries`, il crée un événement dans Cremona. Les emails directs
sont relevés par l'IMAP de l'organisation Cremona et apparaissent dans la même
file commerciale.

## Ordre de construction

1. Socle commercial : modèles de lignes, provenance d'une ligne, référence et
   statut Pennylane.
2. `ServiceDefinition`, `StockItem` et mouvements de stock.
3. `InstrumentAsset` et `WorkshopOrder`, avec une intervention complète.
4. `RentalContract`, blocage de disponibilité et échéances.
5. Projection publique CMS et bridge Contempo.
6. Inventaire et import Dynamics idempotent, en lecture seule d'abord, avec
   rapprochement manuel des conflits.

Chaque étape ajoute ses migrations, politiques d'organisation, tests
d'isolation, journal d'activité et procédure de reprise. Aucun import Dynamics
ni bascule de site n'est déclenché par la livraison du schéma.

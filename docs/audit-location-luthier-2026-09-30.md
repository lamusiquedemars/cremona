# Audit — domaine Location Luthier

> Décision produit du 30 septembre 2026. Audit de code réalisé avant toute
> migration ou évolution fonctionnelle.

## 1. Architecture existante pertinente

| Élément | Fichier / table | Rôle et relations constatées |
| --- | --- | --- |
| Instrument | `app/Models/InstrumentAsset.php` / `instrument_assets` | Bien physique isolé par `organization_id`. Il possède `family`, `status`, les indicateurs séparés `available_for_sale` et `available_for_rental`, ainsi que deux montants indicatifs. Relation `rentals()`. |
| Location | `app/Models/Rental.php` / `rentals` | Racine actuelle d'une location : une référence, un instrument, un contact, un statut, début, retour prévu, restitution effective, loyer, dépôt et notes. Relations `instrument()` et `person()`. |
| Gestionnaire de location | `app/Services/RentalManager.php` | Démarre une location dans une transaction, fait passer l'instrument à `Rented`, puis le remet à `Available` lors de la restitution. |
| Écran de location | `app/Filament/Resources/Rentals/RentalResource.php`, `Pages/EditRental.php` | Création/édition et deux actions : **Démarrer la location** et **Enregistrer le retour**. |
| Document privé | `app/Models/PrivateDocument.php`, `private_documents`, `private_document_links` | Fichier privé versionné, empreinte SHA-256, auteur et rattachements polymorphes. Le whitelist actuel ne permet pas de le rattacher à `Rental` ni à `InstrumentAsset`. |
| Devis / dossier atelier | `app/Models/Quote.php`, `WorkshopOrder.php` | Dossier atelier → devis par `workshop_order_id`. Ce flux est distinct d'une location. |
| Stock | `StockItem`, `StockMovement`, `StockManager` | Articles consommables, quantité et mouvements : il ne représente pas le parc d'instruments. |
| Audit | `OrganizationAuditLog`, `AuditLogger` | Journal immuable avec acteur, IP et user-agent. Il est employé pour correspondance et intégrations, pas pour les étapes de location, de signature ou de restitution. |

Toutes ces données utilisent le trait `BelongsToOrganization` : le cloisonnement
par organisation est donc déjà le bon socle du futur domaine.

## 2. État fonctionnel constaté

| Domaine | État | Preuve et limite |
| --- | --- | --- |
| Instruments | ✅ | `InstrumentAsset` porte le parc, la famille, la provenance et la disponibilité. |
| Vente / location d'un instrument | 🟡 | Les deux indicateurs existent et sont indépendants. Un instrument exclusivement louable est déjà possible (`available_for_sale = false`). Il n'existe pas de note commerciale spécifique à la vente/location. |
| Catégories / types | 🟡 | `family` est une liste Filament fixe (`violon`, `alto`, `violoncelle`, etc.) ; aucune entité de catégorie administrable n'existe. |
| Tarification de location | 🟡 | `suggested_rental_amount` est aujourd'hui un montant sur chaque instrument, copié manuellement dans `Rental::unit_amount`. Aucun tarif par famille, période ou date d'effet. |
| Locations | 🟡 | `Rental`, `RentalStatus` et `RentalManager` existent. Les statuts sont brouillon, active, restituée, annulée. Il n'y a ni contrat, ni durée indéterminée explicitée, ni historique d'événements. |
| Retour théorique / effectif | ✅ | `expected_return_on` et `returned_on` sont bien distincts. Le service ne termine jamais une location parce que la date prévue est dépassée. |
| Assurance | ❌ | Aucun modèle, migration, réglage, palier, valeur assurée ou relation assurance-location trouvé. |
| Contrat de location | ❌ | `Rental` n'est pas un contrat : il n'a ni parties figées, ni conditions, ni document, ni version, ni acceptation. |
| PDF | 🟡 | `QuotePdfRenderer` génère un PDF de devis avec Dompdf. Aucun PDF de location ni archive figée de PDF généré. |
| Signature / acceptation électronique | ❌ | L'accord d'un devis est une action interne `QuoteWorkflowManager::markAccepted()`. Aucun lien public, jeton de signature, signature, preuve d'acceptation ou horodatage de signature n'existe. |
| Documents privés | ✅ | Téléversement privé, contrôle d'accès, versionnement et hash existent via `PrivateDocumentManager`. |
| Restitution | 🟡 | La restitution modifie le statut et la date. Elle ne collecte ni constat, ni observation structurée, ni photo privée, ni document de fin. |
| Mandats SEPA / IBAN / RUM | ❌ | Aucun modèle, champ, migration ou service trouvé. |
| Prélèvements | ❌ | Aucun échéancier, statut de prélèvement, action à arrêter ou relation avec une location. |
| Import bancaire / export comptable | ❌ | Aucun import CSV/XLSX ni export comptable trouvé. |
| Stock | ✅ | `StockItem`, `StockMovement` et `StockManager` gèrent entrées, consommations, ventes, retours et corrections. |
| Devis | ✅ | Devis, lignes, PDF, envoi interne et acceptation interne existent. |
| Dossier atelier | ✅ | Workflow explicite reçu → diagnostic → attente d'accord → planifié → en cours → prêt → restitué. |
| E-mail / notifications | 🟡 | IMAP/SMTP et notifications Filament existent ; aucun envoi de contrat, relance de location ou notification de restitution n'est câblé. |
| Photos / fichiers | 🟡 | Les photos d'instrument sont des chemins JSON sur le disque **public** ; documents privés séparés disponibles, sans compression automatique ni politique de conservation. |
| Audit / historique | 🟡 | Infrastructure d'audit existante mais aucun événement de location n'y est enregistré et aucune interface de journal général n'existe. |

## 3. Réutilisable sans doublon

- `Rental` conserve sa référence publique, son lien à l'instrument et au client,
  et les dates déjà enregistrées. Il ne faut pas créer un second objet
  « contrat » concurrent qui romprait ces liens.
- `RentalManager` apporte déjà les transactions et verrous qui empêchent de
  louer deux fois le même instrument disponible ; il doit rester le point de
  passage du cycle de vie.
- `InstrumentAsset` distingue déjà la capacité commerciale (vente/location) de
  la disponibilité actuelle. Cette séparation est juste et doit être conservée.
- `PrivateDocument`/`PrivateDocumentManager` fournissent stockage privé,
  versionnement, hash, auteur et téléchargement autorisé. Il faudra seulement
  ouvrir explicitement les rattachements au domaine Location.
- `OrganizationAuditLog`/`AuditLogger` peuvent conserver les événements qui
  comptent : activation, restitution, constat, arrêt de prélèvement confirmé.
- `QuotePdfRenderer`, `QuoteDocumentProfileManager` et les coordonnées légales
  sont réutilisables comme fondation de rendu, mais ne constituent pas encore
  un contrat de location.
- Les rôles, permissions, contexte d'organisation et `StockManager` sont déjà
  des fondations transversales suffisantes.

## 4. Écarts et incohérences à traiter avant extension

1. `Rental` est un suivi opérationnel minimal ; l'étendre directement avec les
   champs d'assurance, RUM, prélèvement, signature, résiliation et photos
   rendrait une table monolithique et difficile à auditer.
2. La famille est une valeur fixe de formulaire, pas une catégorie gérée par
   l'organisation. Elle ne peut donc pas porter proprement une grille de prix.
3. Le tarif est seulement indicatif, par instrument, sans période ni instantané
   contractuel ; toute modification ultérieure peut brouiller l'historique.
4. Les photos actuelles sont publiques ; elles ne conviennent pas à un constat
   de retour ou à une pièce justificative privée.
5. Les documents privés ne peuvent pas encore être liés à une location, un
   instrument ou un dossier atelier.
6. Aucun objet actuel ne permet de représenter plusieurs mandats pour un même
   client, ni de savoir lequel doit être arrêté lors d'une restitution.
7. Aucun mécanisme de signature interne ne satisfait aujourd'hui aux besoins de
   preuve d'un contrat. Une simple action admin « accepté » ne doit pas être
   présentée comme une signature électronique.
8. Le devis accepté ne fait pas changer immédiatement l'état du dossier atelier
   : `markAccepted()` ne met à jour que `Quote`. Le dossier reste
   `AwaitingApproval` jusqu'à l'action manuelle **Planifier l'intervention** ;
   elle fonctionne car `WorkshopOrderWorkflowManager::schedule()` contrôle le
   statut du devis, mais l'interface ne reflète pas encore l'accord enregistré.

## 5. Décision et ordre de conception

### Choix retenu : un domaine Location dédié, enraciné dans `Rental`

Le choix n'est pas de remplacer `Rental` par un nouvel objet `Contract`, ni de
lui ajouter sans limite tous les besoins possibles. `Rental` devient la racine
du domaine Location — le contrat opérationnel — et conserve les références et
historiques existants. Des objets enfants dédiés ne sont introduits que pour
des réalités autonomes : grille tarifaire, couverture d'assurance, mandat de
prélèvement, restitution, document ou événement.

Cette option évite deux erreurs : dupliquer la location existante, ou créer une
table unique qui mélange bien, tarif, preuve contractuelle, assurance et
banque. Elle ne décide pas que l'assurance, la signature interne, le SEPA ou
l'export comptable doivent être réalisés : chacun reste conditionné à un
besoin validé avec Giovanni.

Ordre de conception, avant toute migration :

1. formaliser le cycle de vie de `Rental` et la restitution, avec les seules
   étapes réellement nécessaires ;
2. concevoir une grille de location administrable : famille, une ou plusieurs
   tailles, gamme configurable et tarif mensuel. L'instrument porte sa famille,
   sa taille et sa gamme ; Cremona lui attribue automatiquement l'unique grille
   correspondante, avec montant figé dans la location au démarrage ;
3. décider si une exception de prix par instrument est autorisée et, si oui,
   la rendre explicite plutôt que silencieuse ;
4. rattacher documents privés, constats et éventuellement photos privées à la
   location ; **livré :** les documents privés peuvent désormais être liés à
   un instrument ou à une location, avec stockage privé, versionnement et
   contrôle d'organisation existants ;
5. corriger séparément la cohérence devis accepté ↔ dossier atelier ;
6. seulement après observation du travail réel, décider d'un module assurance,
   puis d'un suivi manuel de mandat/prélèvement ;
7. laisser signature qualifiée, automatisation bancaire et import/export
   comptable hors du premier lot.

## 6. Évolution validée — parc supérieur à 100 instruments

Une étiquette libre liée manuellement à chaque instrument ne permettrait pas
d'importer ni de maintenir un parc important. Le terme « catégorie » désigne
donc désormais dans l'interface une **grille de location** : une règle de prix,
et non une taxonomie générale du parc.

| Entité | Données structurées | Rôle |
| --- | --- | --- |
| Instrument | famille, taille, gamme de location, mode automatique ou tarif exceptionnel | Décrit le bien physique. Une taille ne reste plus seulement une caractéristique libre. |
| Gamme de location | libellé actif par organisation | Vocabulaire métier configurable, par exemple Étude, Avancé, Professionnel. Aucun prix ici. |
| Grille de location | libellé, famille, tailles cochées, gamme, loyer mensuel, active | Décrit une règle tarifaire ; une combinaison famille + taille + gamme ne peut être couverte que par une seule grille active. |
| Location | montant mensuel recopié à sa création | Conserve le prix réellement appliqué, même après modification de la grille. |

La relation instrument → grille est calculée lors de l'enregistrement de la
fiche. Un instrument exceptionnel peut conserver sa qualification mais recevoir
un tarif mensuel dérogatoire explicite. Il ne faut pas le placer dans une grille
inexacte pour contourner le modèle.

Les tailles sont une liste contrôlée par famille (fractions pour violon,
violoncelle et contrebasse ; pouces pour alto), avec une valeur non standard.
Cela couvre notamment les altos dont les tailles ne suivent pas les fractions.
Les caractéristiques libres restent disponibles pour la fiche et le site, mais
ne déterminent plus le tarif.

Avant l'import initial, l'organisation configure ses gammes puis ses grilles.
L'import ne crée aucune grille implicite : toute ligne sans famille, taille,
gamme ou grille univoque doit être signalée et corrigée.

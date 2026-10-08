# Plan Location — contrats, signature et prélèvements

> Référence de travail partagée — mise à jour le 7 octobre 2026.
>
> Ce document est la source de vérité du chantier. Il est versionné avec Cremona :
> le consulter et le mettre à jour sur n'importe quel poste après synchronisation Git.

## Objectif

Faire de `Rental` le dossier opérationnel d'une location : génération de contrats
depuis des données structurées, archive PDF figée, signature traçable, restitution
documentée et suivi manuel de ce qui doit être prélevé.

`Rental` reste la racine métier. Aucun second objet concurrent ne porte le cycle
de vie de la location.

## Principes de conception

- Les contrats Word de référence ne sont pas stockés dans Git : ils peuvent contenir
  des données sensibles. Seules leur structure métier et les clauses validées sont
  reprises dans les modèles documentaires.
- Un PDF envoyé à signature est immuable : snapshot métier, version du modèle,
  SHA-256 et archive privée.
- Une acceptation admin n'est pas une signature électronique.
- Le retour physique clôt la disponibilité de l'instrument indépendamment de
  l'acceptation ultérieure de l'attestation ; la dette éventuelle reste séparée.
- Contrat de location, assurance, mandat SEPA et prélèvement bancaire sont des
  réalités distinctes.
- Cremona est le référentiel de ce qui devrait être prélevé. La banque reste
  l'outil d'exécution tant qu'aucune intégration n'est décidée.

## État constaté

### Déjà livré

- Location, instrument, client, statut, dates et loyer mensuel figé.
- Grilles de prix par famille, taille et gamme ; tarif exceptionnel explicite.
- Démarrage transactionnel de la location et restitution datée, commentée et
  auditée ; l'instrument redevient disponible au retour.
- Documents privés versionnés, hashés et liés à une location ou un instrument.
- Profil légal d'organisation, snapshots documentaires et moteur PDF de devis,
  réutilisables comme fondation.
- SMTP, file Laravel et tâches CRM disponibles comme fondations transversales.
- Registre `RentalDocument` livré : type métier, version, snapshot, référence au
  PDF privé, hash contrôlé et protection contre la modification d'un document généré.

### Non livré

- Contrat de location généré, PDF archivé et version de clauses.
- Demande de signature, lien personnel, expiration, preuve d'acceptation et
  emails transactionnels.
- Contrat/couverture d'assurance.
- Restitution structurée (accessoires, frais, photos privées) et attestation.
- Payeur, mandat SEPA, montant attendu, confirmation manuelle bancaire et tableau
  de contrôle.

## Modèle cible minimal

| Élément | Rôle |
| --- | --- |
| `RentalDocument` | Enfant de `Rental` : type (`rental_contract`, `insurance_contract`, `return_certificate`), statut, snapshot, version de modèle et PDF privé. |
| `PrivateDocument` | Archive binaire privée existante : fichier, version, SHA-256 et droits d'accès. |
| `SignatureRequest` | Demande d'acceptation : destinataire figé, hash du jeton, expiration, statut et documents concernés. |
| `SignatureEvent` | Chronologie de preuve : création, envoi, ouverture, consultation, acceptation, refus, expiration ou annulation. |
| `RentalReturn` | Retour physique : date, accessoires, observations, frais, documents de preuve et attestation. |
| `PaymentMandate` | Mandat du payeur, distinct de la location ; données bancaires seulement si leur stockage est validé. |
| `MandateRentalAllocation` | Part mensuelle attendue par location/assurance, afin de calculer le total par mandat. |

`OrganizationAuditLog` complète ces éléments ; il ne remplace pas l'historique
de signature propre au document.

## Workflow retenu

1. Giovanni prépare la location et vérifie les données obligatoires.
2. Cremona fige les parties, l'instrument, les montants, les clauses et le modèle.
3. Cremona génère le PDF, calcule son hash, l'archive en privé, puis crée la
   demande de signature.
4. Le client reçoit un lien personnel à durée limitée. L'acceptation est atomique
   et produit les preuves nécessaires.
5. Le contrat est signé automatiquement ; la remise physique de l'instrument reste
   une action explicite, qui active la location.
6. Au retour, Giovanni enregistre immédiatement le retour physique. L'attestation
   peut ensuite être acceptée par le client sans bloquer la disponibilité réelle.
7. Toute variation de locations/assurances recalcule le montant attendu et crée,
   si besoin, une tâche manuelle à réaliser dans Crédit Agricole.

Pour location et assurance émises ensemble, une demande unique peut contenir les
deux PDF et leurs hash. Les documents restent indépendants, ce qui permet une
demande séparée lorsque l'assurance est ajoutée plus tard.

## Plan de livraison

| Phase | Objectif | État |
| --- | --- | --- |
| 0. Décisions métier | Niveau de signature, modèle juridique, signataire/payeur, justificatifs. | À valider |
| 1. Fondation documentaire | `RentalDocument`, snapshots, PDF privé hashé et immuable. | Livré et déployé — registre et intégrité ; rendu PDF en phase 2 |
| 2. Contrat de location | Rendu depuis données structurées et émission contrôlée. | Non commencé |
| 3. Signature Cremona | Lien, jeton hashé, expiration, preuves, invitation et confirmation. | Non commencé |
| 4. Assurance | Contrat/couverture avec moteur documentaire commun. | Non commencé |
| 5. Restitution | Constat, accessoires, frais, photos privées et attestation. | Non commencé |
| 6. Prélèvements | Payeur, mandat, montant attendu et confirmation manuelle. | Non commencé |
| 7. Tableau de contrôle | Actions à faire et vue des écarts de prélèvement. | Non commencé |
| 8. Banque/comptabilité | Import/export ultérieur uniquement. | Hors périmètre |

## Sécurité et preuves

- Ne jamais stocker un jeton de signature en clair : conserver seulement son hash.
- Refuser les liens invalides, expirés, annulés ou déjà consommés.
- Verrouiller l'acceptation en base contre double clic et requêtes concurrentes.
- Conserver le texte exact du consentement, l'instant, le document/hash, la version,
  l'identité déclarée et, après validation juridique, IP et user-agent.
- Interdire le remplacement ou la suppression fonctionnelle d'un PDF signé.
- Conserver contrats, justificatifs et photos de restitution uniquement sur stockage
  privé ; ne pas réutiliser les photos publiques du catalogue instrument.
- Ne pas écrire IBAN, RUM, jeton ou justificatif dans les logs.

## Reprise des locations existantes

- Ne jamais demander une nouvelle signature pour un contrat déjà signé sur papier
  ou via Docaposte/Maileva.
- Les archiver comme `historical_external` lorsqu'un scan/PDF existe.
- Marquer explicitement les dossiers anciens sans document numérique, sans les
  rendre invalides.
- Renseigner assurance, mandat et prélèvement attendu progressivement, après revue
  humaine.

## Décisions à obtenir avant l'émission et la signature réelles

1. Signature probatoire interne Cremona ou maintien/intégration de Docaposte-Maileva ?
2. Quels justificatifs sont obligatoires en V1 ?
3. Le locataire, le signataire et le payeur peuvent-ils être différents ?
4. Quelles clauses deviennent administrables par organisation après validation juridique ?
5. L'assurance est-elle systématiquement proposée ou réellement optionnelle ?

## Journal d'avancement

| Date | État | Décision / livraison | Suite |
| --- | --- | --- | --- |
| 2026-10-06 | Audit terminé | Modèles de contrats analysés, architecture Cremona confrontée à la cible, plan validable rédigé. Aucun code métier ajouté. | Valider les cinq décisions ci-dessus. |
| 2026-10-07 | Phase 1 livrée | Registre `RentalDocument`, versionnement, snapshots, lien au document privé hashé, protection d'intégrité et tests ciblés. | Phase 2 : produire le PDF du contrat à partir des données structurées. |
| 2026-10-08 | Phase 1 déployée | Migration `rental_documents` et caches de production activés sur LWS, confirmation de bon déroulement reçue. | Phase 2 : produire le PDF du contrat à partir des données structurées. |

## Règle de mise à jour partagée

À la fin d'un lot, mettre à jour dans ce fichier : la phase, l'état, les décisions
prises, les migrations/tests réellement livrés et la prochaine étape. Commiter ce
fichier avec le lot correspondant, puis pousser la branche. Sur l'autre Mac,
faire `git pull` avant toute modification et résoudre les éventuels écarts avant
de poursuivre.

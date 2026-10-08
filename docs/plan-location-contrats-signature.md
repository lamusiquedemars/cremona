# Plan Location — contrats, acceptation et archivage

> Référence de travail partagée — mise à jour le 8 octobre 2026.
>
> Ce document est la source de vérité du chantier. Il est versionné avec Cremona :
> le consulter et le mettre à jour sur n'importe quel poste après synchronisation Git.

## Objectif

Faire de `Rental` le dossier opérationnel d'une location : génération de contrats
depuis des données structurées, archive PDF figée, acceptation interne traçable et
restitution documentée.

`Rental` reste la racine métier. Aucun second objet concurrent ne porte le cycle
de vie de la location.

## Principes de conception

- Les contrats Word de référence ne sont pas stockés dans Git : ils peuvent contenir
  des données sensibles. Seules leur structure métier et les clauses validées sont
  reprises dans les modèles documentaires.
- Un PDF envoyé à signature est immuable : snapshot métier, version du modèle,
  SHA-256 et archive privée.
- L’acceptation est réalisée dans Cremona, par un lien personnel et une preuve
  horodatée ; elle ne doit jamais être présentée comme une signature électronique
  qualifiée ou comme un service Docaposte.
- Le retour physique clôt la disponibilité de l'instrument indépendamment de
  l'acceptation ultérieure de l'attestation.
- Un mandat SEPA éventuellement utilisé par l’atelier reste un document privé
  joint au dossier : Cremona ne stocke ni IBAN ni RUM et ne transmet aucun ordre
  de prélèvement à une banque.

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

### Livré dans le lot en cours de publication

- Contrat de location et contrat d’assurance générés depuis la fiche location,
  archivés en PDF privé, hashés et versionnés.
- Demande d’acceptation interne par e-mail : jeton hashé, lien personnel de
  quatorze jours, consultation, consentement explicite, identité saisie,
  horodatage et preuve immuable.
- Restitution structurée : accessoires, état, frais éventuels et attestation PDF
  versionnée. Les photos ou justificatifs restent des documents privés rattachés
  à la location, sans duplication de stockage.
- Un mandat SEPA déjà signé peut être ajouté dans **Documents** et rattaché à la
  location ; aucune donnée bancaire structurée n’est créée.

## Modèle cible minimal

| Élément | Rôle |
| --- | --- |
| `RentalDocument` | Enfant de `Rental` : type (`rental_contract`, `insurance_contract`, `return_certificate`), statut, snapshot, version de modèle et PDF privé. |
| `PrivateDocument` | Archive binaire privée existante : fichier, version, SHA-256 et droits d'accès. |
| `SignatureRequest` | Demande d’acceptation interne : destinataire figé, hash du jeton, expiration, statut et documents concernés. |
| `SignatureEvent` | Chronologie de preuve : création, envoi, ouverture, consultation, acceptation, refus, expiration ou annulation. |
| `RentalReturn` | Retour physique : date, accessoires, observations, frais, documents de preuve et attestation. |

`OrganizationAuditLog` complète ces éléments ; il ne remplace pas l'historique
de signature propre au document.

## Workflow retenu

1. Giovanni prépare la location et vérifie les données obligatoires.
2. Cremona fige les parties, l'instrument, les montants, les clauses et le modèle.
3. Cremona génère le PDF, calcule son hash, l'archive en privé, puis crée la
   demande d’acceptation interne.
4. Le client reçoit un lien personnel à durée limitée. L'acceptation est atomique
   et produit les preuves nécessaires.
5. Le contrat est signé automatiquement ; la remise physique de l'instrument reste
   une action explicite, qui active la location.
6. Au retour, Giovanni enregistre immédiatement le retour physique. L'attestation
   peut ensuite être acceptée par le client sans bloquer la disponibilité réelle.
Pour location et assurance émises ensemble, une demande unique peut contenir les
deux PDF et leurs hash. Les documents restent indépendants, ce qui permet une
demande séparée lorsque l'assurance est ajoutée plus tard.

## Plan de livraison

| Phase | Objectif | État |
| --- | --- | --- |
| 0. Décisions métier | Acceptation interne, modèle juridique, signataire, justificatifs. | Décision actée le 8 octobre |
| 1. Fondation documentaire | `RentalDocument`, snapshots, PDF privé hashé et immuable. | Livré et déployé — registre et intégrité ; rendu PDF en phase 2 |
| 2. Contrat de location | Rendu depuis données structurées et émission contrôlée. | Livré — en attente de migration production |
| 3. Acceptation Cremona | Lien, jeton hashé, expiration, preuves, invitation et confirmation. | Livré — en attente de migration production |
| 4. Assurance | Contrat/couverture avec moteur documentaire commun. | Livré — en attente de migration production |
| 5. Restitution | Constat, accessoires, frais, photos privées et attestation. | Livré — en attente de migration production |
| 6. Mandat SEPA documentaire | Dépôt privé facultatif d’un mandat déjà signé, sans données bancaires structurées. | Livré via Documents privés |
| 7. Banque / prélèvements | Exécution, connecteur, import et export bancaire. | Hors périmètre |

## Sécurité et preuves

- Ne jamais stocker un jeton d’acceptation en clair : conserver seulement son hash.
- Refuser les liens invalides, expirés, annulés ou déjà consommés.
- Verrouiller l'acceptation en base contre double clic et requêtes concurrentes.
- Conserver le texte exact du consentement, l'instant, le document/hash, la version,
  l'identité déclarée et, après validation juridique, IP et user-agent.
- Interdire le remplacement ou la suppression fonctionnelle d'un PDF signé.
- Conserver contrats, justificatifs et photos de restitution uniquement sur stockage
  privé ; ne pas réutiliser les photos publiques du catalogue instrument.
- Ne pas écrire IBAN, RUM, jeton ou justificatif dans les logs.

## Reprise des locations existantes

- Ne jamais demander une nouvelle acceptation pour un contrat déjà signé sur papier
  ou via un prestataire externe.
- Les archiver comme `historical_external` lorsqu'un scan/PDF existe.
- Marquer explicitement les dossiers anciens sans document numérique, sans les
  rendre invalides.
- Archiver progressivement assurance et mandat papier/extérieur après revue humaine.

## Décisions à obtenir avant l’émission réelle

1. Quels justificatifs sont obligatoires en V1 ?
2. Le locataire et le signataire peuvent-ils être différents ?
3. Quelles clauses deviennent administrables par organisation après validation juridique ?
4. L'assurance est-elle systématiquement proposée ou réellement optionnelle ?

## Journal d'avancement

| Date | État | Décision / livraison | Suite |
| --- | --- | --- | --- |
| 2026-10-06 | Audit terminé | Modèles de contrats analysés, architecture Cremona confrontée à la cible, plan validable rédigé. Aucun code métier ajouté. | Valider les cinq décisions ci-dessus. |
| 2026-10-07 | Phase 1 livrée | Registre `RentalDocument`, versionnement, snapshots, lien au document privé hashé, protection d'intégrité et tests ciblés. | Phase 2 : produire le PDF du contrat à partir des données structurées. |
| 2026-10-08 | Phase 1 déployée | Migration `rental_documents` et caches de production activés sur LWS, confirmation de bon déroulement reçue. | Phase 2 : produire le PDF du contrat à partir des données structurées. |
| 2026-10-08 | Références métier retrouvées | Les modèles source `contrat location Contempo.docx` et `contrat assurance Contempo.docx` ont été relus hors Git. Ils confirment : location à durée indéterminée avec engagement initial de trois mois, paiement mensuel, assurance distincte et optionnelle, justificatifs et mandat SEPA séparés, signature Docaposte historique. | Générer et archiver les deux PDF ; ne pas prétendre remplacer Docaposte tant qu’un choix de signature n’est pas acté. |
| 2026-10-08 | Décision produit | Cremona réalisera une acceptation interne traçable. Aucun connecteur bancaire, ordre de prélèvement, IBAN ou RUM ne sera développé ; un mandat existant pourra seulement être archivé comme document privé. | Phase 3 : parcours d’acceptation interne. |
| 2026-10-08 | Lot location complet | Génération location, assurance et attestation de restitution ; acceptation interne par e-mail ; constat structuré de retour. Les fichiers sont prêts à être déployés avec trois migrations additives. | Exécuter migrations et caches LWS, puis valider un parcours réel sans données fictives. |

## Règle de mise à jour partagée

À la fin d'un lot, mettre à jour dans ce fichier : la phase, l'état, les décisions
prises, les migrations/tests réellement livrés et la prochaine étape. Commiter ce
fichier avec le lot correspondant, puis pousser la branche. Sur l'autre Mac,
faire `git pull` avant toute modification et résoudre les éventuels écarts avant
de poursuivre.

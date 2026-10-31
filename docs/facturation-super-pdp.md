# Facturation et Super PDP

## Chantier solution compatible — périmètre et backlog

> Cadrage du 31 octobre 2026. Objectif : proposer les fonctions de facturation
> électronique de Cremona en conformité avec leur périmètre et réunir les
> preuves permettant l'usage du logo DGFiP. Atelier Ivo Incidit est déjà
> raccordé à Super PDP selon confirmation d'Ivo ; ce raccordement est un point
> de départ pour la recette, pas une preuve de conformité de Cremona. Les notes
> historiques ci-dessous ne constituent pas une vérification de l'état réel.

### Statut des informations

- **Réglementaire vérifié** : une solution non immatriculée doit passer par une
  plateforme agréée pour les échanges réglementaires. Les critères de la
  [charte DGFiP du 30 juillet 2025](https://www.impots.gouv.fr/sites/default/files/media/1_metier/2_professionnel/EV/2_gestion/290_facturation_electronique/fe_charte-utilisation-logotype-solution-compatible.pdf)
  s'apprécient selon les fonctions proposées ; le logo ne constitue pas une
  immatriculation ni une certification individuelle. La réception concerne les
  entreprises assujetties dès le 1er septembre 2026 ; l'émission et l'e-reporting
  des PME et microentreprises à partir du 1er septembre 2027 selon leur situation
  ([calendrier DGFiP](https://www.impots.gouv.fr/professionnel/questions/partir-de-quand-suis-je-concerne-par-la-reforme-de-la-facturation)).
- **Prestataire confirmé** : Super PDP figure sur la
  [liste DGFiP des opérateurs ayant réussi les tests d'interopérabilité](https://www.impots.gouv.fr/sites/default/files/media/1_metier/2_professionnel/EV/2_gestion/290_facturation_electronique/listes_plateformes_agreees/liste_pa_attente_rapport_audit.pdf).
  Son [API](https://www.superpdp.tech/openapi/) documente une soumission
  asynchrone de CII, UBL ou Factur-X ; une acceptation initiale ne garantit pas
  la livraison. Statut et version à revérifier avant mise en service.
- **Existant dans le code** : brouillons, numérotation et snapshots, PDF privé,
  paiements, journal, génération et validation CII, transmission et consultation
  manuelles. Ces éléments ne démontrent pas les scénarios complets en production.
- **Décision produit** : viser émission, réception et e-reporting, avec Super PDP
  comme premier adaptateur, sans recréer une comptabilité générale. Les données
  métier et originaux restent exportables. Les scénarios non validés ne doivent
  pas être présentés comme pris en charge.
- **Questions ouvertes** : contrat complet de l'API en vigueur, réception et
  conversions effectives des formats, interprétation de la charte en cas de
  conversion déléguée, habilitation multi-organisations, idempotence, recherche
  après timeout, webhooks, quotas et réversibilité contractuelle. Ne pas déduire
  ces garanties de la seule présence d'un identifiant externe.

### Contrat Super PDP documenté — revue du 31 octobre 2026

Source : [documentation OpenAPI Super PDP](https://www.superpdp.tech/openapi/),
version indexée `1.36.0.beta`, notes du 30 septembre 2026. Cette revue utilise le
contenu indexé consultable : elle ne remplace pas le téléchargement et l'examen
de la spécification complète ni une recette de la version déployée.

| Sujet | Documentation du prestataire | Conséquence pour Cremona |
| --- | --- | --- |
| Multi-organisations | La documentation indique OAuth 2.1 `authorization code` pour un modèle multi-tenant ; l'enrôlement avec usurpation d'entreprise est réservé aux comptables autorisés. | L'accès serveur actuel par organisation ne suffit pas à établir le contrat d'onboarding des clients Cremona. Confirmer le parcours et les droits sans modifier le raccordement d'Atelier. |
| Liste des factures | `GET /v1.beta/invoices` liste entrantes et sortantes, filtrables par sens et période ; pagination par identifiant, 100 par défaut et 1 000 maximum. | Ajouter une synchronisation par organisation et curseur, reprise et rapprochement ; le code actuel ne récupère pas les entrantes. |
| Fichier d'origine | L'API propose `GET /v1.beta/invoices/{id}` avec des formats et indique que l'ancienne route `/download` est dépréciée. | Vérifier la valeur exacte du paramètre de format : l'instruction de remplacement contient une coquille (`orginal`). Conserver le fichier original réellement reçu et ses métadonnées. |
| Validation et émission | `POST /v1.beta/validation_reports` valide ; `POST /v1.beta/invoices` accepte CII, UBL ou Factur-X et met l'envoi en file. | Distinguer validation, remise à la PA et livraison ; conserver le rapport, le fichier et l'identifiant retourné. La longueur maximale de `external_id` est présentée comme 36 à un endroit et 64 à un autre : rester à 36 au plus jusqu'à clarification. |
| Événements | `GET /v1.beta/invoice_events` est paginé avec `starting_after_id` et `has_after` ; les événements ne forment pas une machine à états exclusive. L'API distingue notamment `api:invalid`, `fr:210` refus, `fr:213` rejet et erreurs PPF. | Le code actuel ne lit qu'une page et réduit plusieurs événements à un seul statut. Conserver chaque événement, paginer et distinguer incident technique, refus, rejet et retours fiscaux ; ne pas assimiler `fr:210` à tous les rejets. |
| Encaissements | L'API accepte la création d'événements, dont `fr:212` ; sans détail, le montant pris en compte est le total de la facture. | Ne jamais envoyer ce statut pour un paiement partiel sans montant explicite ; vérifier les règles fiscales et les montants avant transmission. |
| E-reporting | L'API documente transactions et paiements B2C, factures et paiements B2B international, aperçu et déclarations. Elle indique agréger puis transmettre selon le régime TVA configuré dans la PA. | Synchroniser et contrôler le régime et l'option TVA sur les débits ; rapprocher données, aperçu et retours. La documentation indique aussi une alimentation automatique à partir de factures B2C : éviter toute seconde création manuelle des mêmes transactions. Confirmer les cas exacts avant implémentation. |
| Sandbox | `GET /v1.beta/invoices/generate_test_invoice` produit des données fictives dans différents formats, sans les enregistrer lors de la génération. | Utiliser exclusivement un compte sandbox pour les factures de test ; distinguer génération du document et éventuelle soumission. |

**Points non établis** : unicité ou idempotence de `external_id`, mécanisme
garanti de recherche après une réponse perdue, délais et politique de reprise
des webhooks, conservation contractuelle et extraction exhaustive, valeurs
effectives des limites et portée des conversions. L'API étant en version bêta,
conserver la version examinée et vérifier toute évolution avant développement.

### Audit P0 du parcours existant — contrôles à réaliser

Ces constats proviennent de la lecture du code, sans appel réel ni modification
de l'intégration d'Atelier. Les risques sont à reproduire avec des données
fictives avant correction ; les comportements distants restent à confirmer.

**Lot de sécurisation en cours** : l'intention de transmission est persistée
avant l'appel ; un résultat incertain bloque la relance et demande une
vérification. La revalidation est bloquée après le début de transmission et
chaque génération utilise un chemin distinct. Tests locaux ciblés ; restent la
réconciliation distante, les reprises opérationnelles et la recette sandbox.

| Priorité | Constat vérifié | Critère d'acceptation de la correction |
| --- | --- | --- |
| P0 — résultat incertain | `SuperPdpTransmissionManager::submit` effectue l'appel réseau dans une transaction ; l'exception est relancée après mise à jour de l'erreur, ce qui annule aussi cette mise à jour et l'identifiant enregistré dans la transaction. Si la PA a accepté l'envoi avant une coupure, l'état local ne le prouve pas. | Persister l'intention avant l'appel, distinguer échec certain et résultat inconnu ; empêcher toute relance automatique tant que l'état distant n'est pas réconcilié. Tester réponse perdue après acceptation, panne avant envoi, redémarrage et actions concurrentes. Ne revendiquer aucune garantie d'idempotence non confirmée. |
| P0 — revalidation | L'action de validation reste visible sur toute facture non brouillon ; `generateAndValidate` remplace le statut de la dernière transmission par `validated` ou `validation_failed`, même après soumission. | Interdire ou versionner la revalidation après remise selon une règle explicite ; une facture transmise ne peut pas redevenir simplement « validée » ni ouvrir une nouvelle transmission par accident. Tester validations répétées avant et après soumission. |
| P0 — fichier de preuve | La génération utilise le même chemin XML à chaque appel, crée une nouvelle entrée documentaire et supprime ce chemin dans le gestionnaire d'erreur. Une répétition ou une erreur pourrait donc écraser ou supprimer une version précédemment référencée. | Stocker des versions immuables ou réutiliser une version identique ; toute entrée conserve les octets correspondant à son empreinte. Tester répétition, panne de stockage et restauration, sans effacer un document préexistant. |
| P0 — règle fiscale | Le code classe le client uniquement selon `company_id` et applique l'article 293 B à toute ligne à taux zéro. | Séparer identité, pays, nature de l'opération et motif de TVA ; bloquer les cas non qualifiés. Tester B2B français, B2B étranger, B2C, franchise et un autre cas à taux zéro. |
| P0 — journal | Le rapport est actuellement stocké dans les métadonnées de transmission ; la documentation historique évoque un rapport en document privé. La synchronisation écrase la liste des événements par la première page reçue. | Choisir une conservation vérifiable du rapport complet ; stocker et dédupliquer tous les événements et pièces utiles, paginer et garder l'historique. Tester pages multiples, rediffusion et retours hors ordre. |
| P1 — montants figés | Le mapping électronique utilise `balance_amount` au moment de la génération ; les paiements peuvent modifier ce solde après émission. | Définir avec la règle fiscale le traitement des acomptes et paiements ; garantir la cohérence du document structuré, du PDF émis et des preuves. Tester paiement avant et après génération, ainsi que paiements partiels. |
| P1 — expérience de suivi | L'interface affiche un message indiquant que la connexion reste en attente après émission, indépendamment de l'état réel ; un envoi à la PA renseigne aussi `sent_at`. | Afficher séparément connexion, remise à la PA, envoi, réception et refus selon les preuves disponibles ; ne pas affirmer qu'une facture est livrée avant le retour correspondant. |

Les tests actuels du client HTTP utilisent `Http::fake` ; ils vérifient la
forme d'une requête et une réponse simulée, pas une garantie d'idempotence de
Super PDP ni une recette réseau. Ajouter des tests significatifs au moment des
corrections, puis une recette sandbox séparée.

### Matrice initiale de conformité

| Exigence ou contrôle | État dans Cremona | Travail et preuve attendus |
| --- | --- | --- |
| Raccordement à une PA | Atelier raccordé selon Ivo ; code de connexion par organisation. | Vérifier le contrat et les habilitations de chaque organisation ; conserver la preuve de raccordement, sans secrets. |
| Émission et formats du socle | Conversion, validation CII et soumission codées ; couverture des formats et scénarios non démontrée. | Contrôler données, formats et conversion ; comparer source, document transmis et retours sur jeux représentatifs. |
| Avoirs et corrections | Type et lien d'origine existent ; cycle réglementaire incomplet. | Vérifier référence d'origine, rejet, correction et transmission sans modifier l'original. |
| Réception si proposée | Aucun parcours complet identifié. | Récupérer, consulter et conserver les factures et événements ; éprouver les formats convenus et les doublons. |
| E-reporting si proposé | Paiements locaux et classification B2B/B2C partielle ; aucune chaîne complète démontrée. | Qualifier transactions et paiements, échéances, retours et corrections ; rapprocher les données et accusés. |
| Conservation | Documents privés et politique existent ; archivage complet non démontré. | Conserver originaux, preuves, intégrité et lisibilité ; tester export et restauration. Le [BOFiP](https://bofip.impots.gouv.fr/bofip/645-PGP.html/identifiant%3DBOI-CF-COM-10-10-30-20250903) précise notamment la conservation fiscale des originaux électroniques pendant six ans ; vérifier les autres durées applicables. |
| Logo et communication | Critères non démontrés. | Dossier de preuves par fonction, contrôle de la charte, revue du périmètre et veille avant affichage ; aucun agrément revendiqué pour Cremona. |

### Backlog et critères d'acceptation

1. **P0 — Terminer le cadrage et confirmer le contrat Super PDP.** Relever la
   version exacte de la spécification et les règles fiscales applicables ;
   confirmer par écrit formats, réception, e-reporting, OAuth multi-organisations,
   limites, déduplication, reprise et export. Acceptation : matrice ci-dessus
   complétée par fonction et cas, questions attribuées et périmètre approuvé.
2. **P0 — Sécuriser l'émission.** Qualifier B2B français, B2B international,
   B2C et hors champ ; corriger les catégories et motifs TVA, les données
   obligatoires et les calculs ; rendre la reprise après résultat incertain
   vérifiable ; suivre les événements et rejets. Acceptation : documents et
   statuts rapprochés en sandbox, y compris réponse perdue et double tentative.
3. **P1 — Avoirs, corrections et acomptes.** Réutiliser les liens existants,
   compléter références, règles fiscales et transmission. Acceptation : cycle
   complet d'acompte, facture, rejet ou avoir traçable sans altérer l'original.
4. **P1 — Réception.** Récupérer et consulter factures et événements, gérer
   formats, doublons et erreurs. Acceptation : documents entrants représentatifs
   récupérés avec originaux et preuves, puis restaurés après export.
5. **P1 — E-reporting.** Couvrir transactions et paiements concernés selon
   nature, régime et calendrier, avec retours et corrections. Acceptation :
   rapprochement des opérations, déclarations et accusés sans double déclaration.
6. **P1 — Conservation et réversibilité.** Étendre les mécanismes documentaires
   existants aux originaux et preuves, sauvegardes, droits, export et restauration.
   Acceptation : export complet d'une organisation fictive et restauration avec
   vérification d'intégrité et de lisibilité.
7. **P2 — Échange comptable.** Définir avec le cabinet le canal et la prévention
   des doublons ; tester export ou copie Pennylane sans seconde comptabilité.
   Acceptation : rapprochement contrôlé d'un lot de ventes et d'achats.
8. **Jalon logo — Recette et communication.** Exécuter les tests par fonction,
   y compris cas limites, isolation, pannes et reprise ; conserver les résultats,
   versions et décisions ; revoir la charte et le périmètre public. Usage du
   logo seulement après validation de toutes les fonctions effectivement
   proposées et des conditions de la charte. Aucun logo intégré à ce stade.

Les tests utilisent la sandbox et des données fictives ; toute facture de
production doit correspondre à une opération réelle. Les scénarios de lutherie,
location, occasion et TVA particulière sont ouverts seulement après validation
des règles correspondantes. Le planning reste à estimer après le cadrage P0.

> État de référence — 30 octobre 2026. Cette section prévaut sur les notes
> historiques qui suivent.

## Pilote Ivo Correia : état actuel

Le connecteur **Super PDP production** est configuré par organisation avec des
identifiants serveur chiffrés. Une facture émise est immuable, reçoit son
numéro définitif, son PDF privé et une ligne de transmission réglementaire.

Le parcours disponible est volontairement contrôlé :

1. émettre une **vraie** facture (ne jamais créer de facture de test, son
   numéro est définitif) ;
2. utiliser **Valider le document électronique** : Cremona produit les données
   EN 16931, demande à Super PDP un CII, puis enregistre le CII et le rapport
   de validation en documents privés ; cette étape n'envoie rien ;
3. relire la facture, le client et le journal ;
4. seulement si la validation est positive, confirmer **Transmettre à Super
   PDP** ;
5. utiliser **Actualiser les statuts Super PDP** pour relever les événements
   de traitement.

L'envoi reste toujours manuel et confirmé. L'identifiant externe est stable
pour éviter les doublons lors d'une nouvelle tentative. Les statuts affichés
incluent notamment : document validé, remise à Super PDP, envoyée et rejetée.

### Préparation par Ivo avant la première facture réelle

- Vérifier dans le profil légal de l'organisation : raison sociale, SIRET,
  adresse, e-mail, numéro de TVA si assujetti, et instructions de règlement.
- Pour un **client professionnel français**, compléter sa fiche entreprise :
  raison sociale, adresse complète, SIREN ou SIRET et, si disponible, numéro
  de TVA intracommunautaire. Cremona bloque l'envoi B2B sans SIREN/SIRET.
- Pour un **particulier**, renseigner son nom et son adresse complète.
- Créer ensuite une facture correspondant à une opération réelle et la faire
  valider dans Cremona avant toute transmission.

### Prochains développements, hors pilote immédiat

1. Planifier la synchronisation automatique des événements Super PDP, tout en
   conservant l'action manuelle de contrôle.
2. Ajouter l'e-reporting des transactions et paiements selon le régime de TVA.
3. Générer un Factur-X lisible en complément du CII si le besoin de diffusion
   PDF hybride est confirmé.
4. Compléter le cycle des avoirs et factures rectificatives avec leur
   transmission réglementaire et leurs références de facture d'origine.

## Ce qui est disponible

Le module **Facturation** est transverse : il se choisit explicitement dans la configuration de chaque organisation, quel que soit son métier. Il permet de créer un brouillon, de composer ses lignes HT et TVA, puis d’émettre une facture.

À l’émission, Cremona attribue un numéro définitif, fige les coordonnées légales de l’émetteur et du client, calcule les totaux, génère un PDF privé et enregistre les règlements sans réouvrir la facture. Une correction doit passer par un avoir.

Chaque PDF doit devenir un document privé enregistré directement sur Object
Storage Scaleway, accessible seulement aux personnes autorisées dans
l’organisation. Le stockage Scaleway est configuré ; le disque applicatif et
la migration contrôlée des documents existants font partie du P0 documentaire.

## Configuration par organisation

Dans **Administration de l’organisation → Réglages de facturation**, l’administrateur définit notamment :

- le préfixe et le prochain numéro de facture ;
- la devise, le taux de TVA proposé et le régime de TVA ;
- les échéances, instructions de paiement et mentions de retard ;
- si la facturation de location est manuelle ou désactivée ;
- le canal réglementaire (Super PDP par défaut) ;
- une adresse de copie Pennylane facultative pour le comptable.

Ces réglages ne sont pas propres au métier de lutherie. Ils constituent le socle réutilisable d’une autre organisation et d’un autre métier.

## Super PDP et Pennylane

Super PDP est le canal réglementaire unique prévu par défaut. Lorsqu’une facture est émise, Cremona crée une ligne dans le journal de transmission :

- **En attente de configuration** tant que le compte Super PDP n’est pas connecté ;
- **Prête à transmettre** dès que le connecteur de l’organisation est activé.

Cette étape ne prétend pas envoyer une facture : aucun compte ni jeton Super PDP n’est encore configuré dans Cremona. Le connecteur OAuth/API et ses retours de statut feront l’objet du lot d’intégration, après ouverture de l’environnement Super PDP.

## Préparation sandbox (Burger Queen)

La production Cremona dispose des variables de l’application OAuth **sandbox** créée pour Burger Queen. Elles servent exclusivement aux essais techniques et ne sont ni versionnées ni affichées dans l’interface. Lors du passage de Burger Queen vers son entreprise Super PDP réelle, ces identifiants sandbox devront être remplacés par le `client_id` et le `client_secret` de l’application OAuth de production. Tricatel disposera de sa propre application OAuth, car Super PDP rattache chaque application à l’entreprise sélectionnée.

Pennylane reste une copie facultative destinée au comptable (par e-mail d’import des factures de vente) ; ce n’est pas une seconde transmission réglementaire et aucune API Pennylane payante n’est requise.

## Plan de configuration visible

Les intégrations doivent être administrables depuis **Configuration de
l’organisation**, au même endroit que les connecteurs Google Ads ou Brevo. La
configuration visible explique ce qui est actif et pour quelle organisation ;
elle ne révèle jamais un secret, un jeton ni une clé d’accès.

| Élément | Portée | Emplacement et règle |
| --- | --- | --- |
| Scaleway Object Storage | Infrastructure Cremona partagée | Une fiche d’état globale décrit le stockage privé et sa disponibilité. Chaque organisation voit, dans sa configuration, que ses documents privés y sont conservés. Les clés restent seulement dans l’environnement serveur. |
| Documents privés | Organisation | La configuration de l’organisation indique l’activation, les droits d’accès et le principe de conservation des PDF, contrats et pièces jointes. |
| Super PDP | Organisation | La configuration montre le canal choisi, l’environnement (sandbox ou production), l’entreprise Super PDP associée et le dernier contrôle de connexion. Les identifiants sont chiffrés et masqués. |
| Google Ads, Brevo et autres connecteurs | Organisation | Chaque connecteur conserve sa propre fiche, son état, son dernier contrôle et ses droits, sans mélanger les données d’une autre organisation. |

Pour le pilote Contempo, **Burger Queen est uniquement l’entreprise fictive de
la sandbox Super PDP** : ce n’est ni une organisation Cremona ni un choix à
faire par l’utilisateur. Cremona utilisera les identifiants serveur de cette
application pour ses contrôles et transmissions de test ; aucune autorisation
OAuth manuelle ne sera demandée au quotidien.

Le contrôle de connexion utilise OAuth `client_credentials` depuis le serveur :
il ne redirige personne vers Super PDP et ne conserve pas de jeton utilisateur.
Dans la configuration de l’organisation, le bouton **Vérifier la connexion
Super PDP** est seulement un diagnostic ponctuel. Il vérifie le jeton serveur
et l’état de l’entreprise sandbox ; il ne crée ni ne transmet une facture.

### Lot à réaliser

1. Préparer le stockage chiffré des identifiants propres à chaque organisation
   pour la production, afin de remplacer Burger Queen sans développement
   supplémentaire lorsque Contempo aura sa véritable entreprise Super PDP.

## Limites du premier lot

Une facture peut être créée manuellement ou depuis un devis accepté : les lignes
et le client sont alors repris une seule fois dans un brouillon. La facturation
d’échéance de location reste volontairement manuelle ou désactivée selon
l’organisation ; elle ne doit pas être automatisée avant validation métier.

L’envoi effectif et le suivi des retours Super PDP restent le prochain lot : il
nécessite les accès de la véritable entreprise émettrice. La sandbox Burger
Queen sert à valider la connexion technique, jamais à produire une facture
légale de Contempo.

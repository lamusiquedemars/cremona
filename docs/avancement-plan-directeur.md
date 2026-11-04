# Avancement du plan directeur Cremona

> Dernière mise à jour : 1er novembre 2026.
>
> Ce document suit les livraisons du [plan directeur produit](plan-directeur-produit-cremona.md). Il ne remplace pas les critères de fin du plan.

## Carte des responsabilités — Cremona et Site Core

Le backlog est organisé par capacité et non par application. Une donnée a un
propriétaire ; l'autre application peut en recevoir un lien, un état ou une
projection en lecture seule, mais jamais une seconde fiche éditable.

| Capacité | Propriétaire | Rôle de l'autre application | Décision produit |
| --- | --- | --- | --- |
| Pages, contenus, SEO, médias publics, formulaires et anti-spam | **Site Core** | Cremona reçoit uniquement l'événement métier d'un formulaire connecté | Le site reste disponible même si Cremona est indisponible. |
| Contact commercial, demande, correspondance, tâche, rendez-vous, devis et document privé | **Cremona** | Site Core peut ouvrir l'espace de gestion, sans recopier le CRM | Une correspondance n'est transformée en demande que par une action explicite. |
| Consentement marketing, audience, segments et campagnes | **Site Core + Brevo** | Cremona peut proposer une invitation à s'inscrire ; il ne peut jamais inscrire quelqu'un sans consentement | Un contact CRM et un abonné marketing sont deux choses distinctes. |
| Catalogue éditorial autonome (ex. archets Arcus) | **Site Core** | Cremona conserve une référence stable et un snapshot commercial si un suivi le nécessite | Pas de deuxième catalogue éditable dans Cremona. |
| Parc, stock, prestations et tarifs opérationnels (ex. Contempo) | **Pack métier Cremona** | Site Core affiche la sélection expressément publiée, avec ses médias et son SEO | La publication est une projection contrôlée, jamais une édition côté site. |
| Facture légale, transmission réglementaire et paiement | **Super PDP / outil comptable** | Cremona prépare l'acte commercial et conserve numéro, statut, PDF/lien et solde utile | Une seule facture légale ; pas de comptabilité parallèle. |
| Documents de travail et preuves privées | **Cremona / Scaleway privé** | Aucun accès public ; le site ne reçoit qu'un document public explicitement publié | Les fichiers sont hors des releases et téléchargés par lien authentifié. |

### Règle catalogue à appliquer aux prochains sites

Avant de construire un catalogue, répondre à quatre questions : quel objet est
vendu ou présenté, qui modifie son prix et sa disponibilité, faut-il gérer un
stock ou un cycle opérationnel, et où se fait sa publication publique ?

- Si la réponse est éditoriale (fiche, récit, images, SEO, prix indicatif), le
  catalogue vit dans **Site Core**.
- Si la réponse engage une opération (unité physique, stock, location,
  réservation, intervention, tarif applicable), il vit dans le **pack métier
  Cremona**.
- Un même objet ne peut avoir qu'un propriétaire. Une référence stable et un
  snapshot sont préférés à une synchronisation bidirectionnelle.
- Le noyau Cremona ne créera pas de `Product` universel : un instrument, une
  pièce de stock, une prestation et une réalisation publiée n'ont pas le même
  cycle de vie.

## Avancement

### A. Navigation produit

- Adaptation par organisation des groupes, libellés et capacités visibles livrée.
- Groupes canoniques `Relation client` et `Acquisition` utilisés par les capacités déjà disponibles.
- Aucun groupe vide `Offre`, `Présence en ligne`, `Résultats` ou `Référencement` n’est affiché.
- Les créations, modifications et consultations des dossiers métier utilisent
  désormais des titres et fils d’Ariane orientés vers l’objet réel :
  `Nouveau devis`, `Modifier · D260001`, ou simplement la référence du dossier
  consulté. Les intitulés génériques de type « Afficher … » ne servent plus de
  titre métier.
- Une demande provenant de `maracuja-site` affiche désormais « Formulaire du
  site ». L’identifiant technique est conservé en base pour le bridge, sans
  être imposé à l’utilisateur.
- L'entrée **Clients** est livrée : une seule liste recherche les personnes et
  les entreprises, avec des vues Particuliers et Professionnels. Les modèles,
  droits, URLs et fiches existants sont conservés ; Contacts et Entreprises ne
  sont plus deux entrées de menu concurrentes.

État : **en cours**. Le registre de capacités n’est pas encore la source unique.
Le lot transversal des titres et fils d’Ariane est livré ; restent les libellés
spécifiques de certains réglages d’administration et le contrôle des écrans
rarement utilisés.

### B. Accueil et priorités quotidiennes

- Lot B1 livré en production par le commit `dfb2183` : synthèse des demandes, correspondances, tâches, rendez-vous, devis et campagnes.
- Les calculs du jour utilisent le fuseau de l’organisation.
- Les cartes locales indiquent directement la file qu’elles ouvrent, sans répéter l’heure d’accès ni le fuseau ; les campagnes indiquent la dernière synchronisation Google Ads.
- Chaque indicateur ouvre une file filtrée correspondant au contexte.
- Lot B2 livré : les listes détaillées restent entièrement cliquables et leurs descriptions indiquent naturellement ce qu’elles contiennent.

État : **en cours**.

### C. Agenda

- Rendez-vous manuels et Brevo disponibles. Les rendez-vous synchronisés Brevo
  restent en lecture seule ; les rendez-vous, appels et dépôts saisis dans
  Cremona peuvent être créés et modifiés par l’équipe.
- Une vue **Agenda** hebdomadaire réunit rendez-vous, appels, tâches datées,
  dossiers atelier planifiés et retours de location. Chaque bloc ouvre la fiche
  d’origine ; aucune copie d’événement n’est créée.
- La navigation conserve aussi `Rendez-vous` pour la liste détaillée et ses
  filtres ; `Agenda` est la vue de travail transversale.

État : **première version livrée**. La connexion bidirectionnelle à un agenda
personnel Google ou Outlook n’est pas incluse : elle nécessitera un connecteur
et des règles de conflit explicites.

### D. Offre

- Les trois parcours Atelier Ivo sont cadrés dans [Flux métier Atelier Ivo](flux-metier-atelier-ivo.md).
- Le profil de site autonome et le profil de site connecté, ainsi que leur
  frontière CMS/Cremona, sont validés dans le
  [Blueprint — sites autonomes et gestion connectée](blueprint-sites-connectes.md).
- Une demande site reste captée par le CMS mais est traitée dans Cremona pour
  un site connecté. Le rapprochement des emails directs, appels et rencontres
  dans une même file reste à terminer.
- Atelier Ivo conserve Arcus/CMS comme propriétaire de son catalogue ; Contempo
  est le pilote du futur pack Luthier propriétaire de son parc et de son stock.
- Le contrat du bridge CMS ↔ Cremona est écrit : outbox, idempotence,
  authentification, reprise et bascule sans suppression des historiques.
- Le bridge est livré sur le CMS instance : le mode connecté ne crée pas de
  demande locale, tente la transmission immédiatement et conserve une outbox
  chiffrée relançable par planificateur. Le pilote Atelier Ivo applique le même
  principe en production.
- Aucun catalogue universel n’a été ajouté au noyau.

État : **bridge en cours de validation ; cadrage métier en cours**.

### E. Devis et documents

- Les coordonnées et mentions légales de l’organisation sont configurables
  séparément des réglages propres aux devis.
- Le document de devis contient les données de l’émetteur et du destinataire,
  les lignes, les totaux, les conditions, les mentions légales et le bloc
  d’accord ; les notes internes n’y figurent pas.
- Le rendu PDF a été corrigé pour toujours retrouver le contexte de
  l’organisation, y compris après un changement de contexte dans Cremona.
- Les nouveaux devis reçoivent une référence annuelle par organisation au
  format `DAANNNN` ; les références antérieures ne sont pas modifiées.
- La migration du compteur et les caches ont été activés en production le
  16 septembre. Le téléchargement d’un devis existant a été confirmé en
  production.
- Le dossier atelier et son devis partagent désormais un même numéro annuel,
  par exemple `AT260001` / `D260001`. Les anciennes références sont conservées
  et restent recherchables ; les références et URL modernes existantes ne sont
  pas réécrites.
- L’acceptation d’un devis lié place le dossier à « Accord reçu — à planifier »
  sans planifier l’intervention ni perdre la date de l’accord. Les deux écrans
  permettent d’ouvrir le document lié. Le lot `61f2eec` a été activé sur LWS
  le 14 octobre, avec ses migrations additives et la reconstruction des caches.

État : **opérationnel, à confirmer lors de la prochaine création réelle de
devis lié à un dossier atelier**. Cette création contrôlera les deux références
automatiquement attribuées ; aucune donnée commerciale fictive ne doit être
créée pour ce test.

### F. Pack Luthier — pilote Contempo

- Les instruments, prestations atelier, articles de stock, dossiers atelier,
  locations et lignes de devis réutilisables sont disponibles dans Cremona.
- Les instruments gérés dans Cremona peuvent être publiés à sens unique sur le
  catalogue public Contempo ; le CMS conserve la mise en page et le SEO.
- Le pack ne contient ni connecteur Dynamics ni intégration Pennylane :
  Dynamics n’est pas une cible et Pennylane sera évalué séparément.

État : **socle livré, validation métier en cours**. Les parcours réels de
réparation/reméchage, location et vente restent à éprouver avec Giovanni avant
de définir des automatisations ou des règles supplémentaires.

La décision de conception Location est désormais prise : `Rental` reste la
racine existante mais devient le domaine Location dédié, sans table contrat
parallèle ni accumulation prématurée de champs assurance, signature ou banque.
Le détail de l'audit et de la séquence de conception est conservé dans
[Audit — domaine Location Luthier](audit-location-luthier-2026-09-30.md).

Le premier lot de fondation est livré : grilles de location administrables
(famille, tailles, gamme et loyer mensuel), qualification structurée de
l'instrument et tarif exceptionnel explicite. Les tarifs de grille sont
désormais versionnés avec une date d'effet : à cette date, le montant est
répercuté à toutes les locations liées à la grille, présentes et futures. Les
documents déjà générés gardent leur propre snapshot historique. Les grilles
actives ne peuvent pas se chevaucher. La restitution est datée, commentée et
tracée.
L'acceptation d'un devis fait aussi désormais passer le dossier atelier lié à
« Accord reçu — à planifier », avant sa planification.

La frontière produit entre opérations et référentiels est maintenant
documentée, sans déplacement d'interface à ce stade : gammes et grilles de
location, prestations atelier et modèles de propositions sont des
référentiels métier ; instruments, dossiers atelier, locations et stock
restent des opérations. Ces référentiels ne doivent pas être absorbés par la
Configuration globale de l'organisation.

Cette frontière est désormais appliquée dans les accès : les réglages de
l'organisation, modèles commerciaux et tarifs de location/assurance ne sont
plus ouverts par défaut aux membres côté client, y compris au propriétaire de
l'organisation. Trois délégations explicites et indépendantes permettent de
confier ponctuellement la configuration, les modèles commerciaux ou les tarifs
de location/assurance. Les ressources concernées vivent dans la rubrique
secondaire **Référentiels métier** et restent protégées côté serveur.

Les photos d’instruments sont maintenant traçables : chaque ajout, retrait,
modification de légende ou changement de visibilité inscrit un événement avec
l’auteur et l’horodatage. Le journal reste réservé à l’administration
plateforme, tandis que les fichiers restent hors des releases dans le stockage
partagé protégé par le script de publication.

La fondation documentaire des futurs contrats est désormais livrée : chaque
`RentalDocument` est rattaché à une location et à un document privé, conserve un
snapshot, une version de modèle et le SHA-256 du PDF, et ne peut plus être
modifié une fois généré. Ce registre ne génère pas encore le contrat ni sa
signature ; ces capacités restent les lots suivants, consignés dans le
[Plan Location — contrats, signature et prélèvements](plan-location-contrats-signature.md).
Le lot a été déployé sur LWS le 8 octobre 2026 après application de sa migration
et reconstruction des caches.

Le cycle opérationnel de Location est désormais livré : **Dossier en préparation,
Dossier envoyé, Dossier accepté, Instrument remis, Instrument restitué**.
L'accord fait passer automatiquement le dossier à l'étape 3 ; remise et
restitution restent des confirmations physiques. Les actions d'encaissement
manuelles sont créées à la remise et à la restitution, remontent au tableau de
bord, et ne constituent pas un connecteur bancaire. Les confirmations client
d'accord, de remise et de restitution sont également livrées.

### G. Correspondances et boîtes email

- IMAP/SMTP teste une boîte, relève ses messages et permet de répondre depuis
  Cremona. La boîte Atelier Ivo est relevée automatiquement toutes les quinze
  minutes, avec reprise incrémentale par UID IMAP.
- Les formulaires des sites connectés créent une demande, une conversation et
  un contact CRM. Les anciennes notifications email de formulaire sont aussi
  reconnues et normalisées lorsqu'elles portent la signature connue du site.
- Un email direct reste une **correspondance**. Il peut être rapproché d'un
  contact existant ou devenir une demande par une action volontaire : cela
  évite de fabriquer des prospects à partir du spam ou d'un simple échange.
- La file **À traiter** rassemble les demandes, réponses attendues, tâches et
  échéances proches. L'agenda affiche les éléments temporels sans les dupliquer.
- Les réponses conservent un sujet utile, un historique lisible et un éditeur
  riche limité à une mise en forme sûre. Les messages entrants sont rendus de
  façon sûre : aucun CSS, script, pixel ou ressource distante ne s'exécute.

État : **opérationnel pour le pilote**. Le prochain lot ne consiste pas à
interpréter le CSS d'un expéditeur : il conservera et rendra du HTML sémantique
sanitisé avec les styles de Cremona, et gardera le texte compact actuel comme
solution de repli pour les messages historiques ou malformés.

## Reste à faire prioritaire

| Priorité | Fonctionnalité | État | Implémentation attendue |
| --- | --- | --- | --- |
| P0 | Documents privés sur Scaleway | Livré | Le disque `scaleway-private`, le téléversement direct et les téléchargements authentifiés sont actifs en production. Aucun bucket ni fichier ne devient public. |
| P0 | Migration documentaire sécurisée | Livré | L’inventaire, la copie SHA-256, la vérification de taille, la reprise idempotente et le journal sont actifs. Les 4 entrées historiques sans fichier source sont explicitement exclues : aucun document n’était officiel, donc aucune donnée n’est inventée ni basculée. |
| P0 | Politique documentaire | Livré | Les catégories, durées initiales, droits par type et rattachements obligatoires sont configurables dans chaque organisation. Aucun archivage ni effacement automatique : le lot suivant devra traiter la revue à échéance. |
| P0 | Configuration des intégrations | Livré | Scaleway, Super PDP, Google Ads et Brevo sont présentés dans **Configuration de l’organisation**. Super PDP utilise le flux serveur automatique ; la sandbox Burger Queen de Contempo est validée `verified` en production. Les secrets restent côté serveur. |
| P1 | Contrats de location | Livré | Contrat PDF versionné et figé, envoi d’un lien personnel, consultation des documents, acceptation nominative horodatée avec preuve, puis attestation de restitution sont opérationnels. Il s’agit d’une acceptation tracée, non d’une signature électronique qualifiée. |
| P1 | Relation client unifiée | Livré | La file « À traiter » réunit les demandes du site, les messages nécessitant une réponse, les tâches ouvertes et les rendez-vous/appels planifiés en retard ou dans les sept jours. Un email importé ne devient une demande qu’à l’action explicite de l’équipe ; il conserve ainsi son historique sans créer de faux prospect. Les rendez-vous plus éloignés restent dans l’agenda. |
| P1 | Catalogue : contrat de propriété et publication | À faire | Formaliser, dans Site Core et Cremona, le choix du propriétaire par catalogue, la référence stable, le snapshot, l'état de publication et la reprise. Commencer par un cas Site Core autonome (Arcus) et un cas métier (Contempo) ; aucun catalogue universel ni synchronisation bidirectionnelle. |
| P1 | Messagerie : HTML sémantique sûr et pièces jointes | À faire | Conserver la source privée lorsque disponible, produire un HTML strictement sanitisé (paragraphes, listes, liens, tableaux simples) et le rendre avec le CSS de Cremona. Ajouter l'inventaire et le téléchargement authentifié des pièces jointes ; ne jamais charger de pixel, CSS ou script distant. |
| P1 | Audience et consentement : parcours public + pont CRM | À faire | Site Core possède l'inscription, la preuve de consentement, les segments et Brevo. Depuis Cremona, une action explicite invite un contact à s’inscrire sur un site choisi ; la confirmation publique seule crée le destinataire. Projeter ensuite un état marketing minimal en lecture seule dans le CRM, sans import automatique, sans édition des segments ni réinscription après désabonnement. |
| P1 | Bridge site ↔ Cremona : recette multi-site | Partiel | L'outbox authentifiée, idempotente et rejouable est livrée. Valider deux sites et deux organisations réelles, y compris panne/reprise et visibilité des échecs ; basculer Contempo seulement après reprise contrôlée de son historique. |
| P1 | Messagerie client synchronisée : qualification | Partiel | La relève automatique est active sur le pilote. Reste le rapprochement assisté d'un contact, puis la création proposée — jamais automatique — d'une tâche, d'un rendez-vous ou d'une demande à partir d'un email direct. |
| P1 | Contrats de location : usage réel | Livré | Contrat PDF versionné, lien personnel, acceptation horodatée et attestation de restitution sont livrés. À confirmer sur des locations réelles ; la signature électronique qualifiée et les prélèvements sont hors périmètre. |
| P1–P2 | Facturation électronique compatible | Partiel | Socle transverse et connecteur Super PDP présents ; la sandbox est validée. Restent les accès de l'entreprise réelle, qualification fiscale, émission fiable, annulation/correction, réception, e-reporting, conservation et preuves. Périmètre : [facturation et Super PDP](facturation-super-pdp.md#chantier-solution-compatible--périmètre-et-backlog). |
| P2 | Présence en ligne et contenus | À faire | Dans Cremona, afficher les sites rattachés, leur état et des liens utiles, sans transformer Cremona en CMS. Les contenus et SEO restent édités dans Site Core. |
| P2 | Administration commerciale | À faire | Écran par organisation : coordonnées émetteur, mentions, CGV, conditions, validité, aperçus, droits et réglages de numérotation. Les règles de devis actuelles sont opérationnelles. |
| P2 | Agenda réel | Partiel | Les vues semaine et mois réunissent rendez-vous/appels, rappels, dossiers atelier planifiés et retours de location. Les essais métier, les propositions depuis email et une éventuelle synchronisation Google/Outlook restent des lots distincts avec règles de conflit explicites. |
| P2 | Validation métier Contempo | À faire | Tester les parcours réels de réparation, location et vente avant toute automatisation, SEPA ou connecteur bancaire. |

## Dette de contrôle connue

La suite globale comporte quatre échecs préexistants, reproductibles isolément, dans les tests de campagne Google Ads et de correspondance. Ils ne sont pas causés par le lot B1, mais doivent être corrigés avant de pouvoir exiger une suite globale entièrement verte comme garde de livraison.

### Devis et administration des numérotations — 14 octobre 2026

Le correctif PDF, la numérotation annuelle des devis et la numérotation
partagée dossier atelier / devis sont actifs en production. Le chantier restant
concerne l’administration documentaire réutilisable et la confirmation d’une
première paire de références créée en situation réelle.


## Complément produit — offre artisans

Ces horizons commerciaux complètent les priorités techniques ci-dessus sans
les remplacer : les P0 existants, la validation Contempo et les travaux métier
engagés conservent leur importance. Les durées sont des ordres de grandeur en
jours ouvrés, à confirmer après recette. Les fonctions déjà livrées restent
identifiées dans le suivi historique ; les points ci-dessous décrivent le reste.

### P1 commercial — fiabilité et simplicité du parcours

- **Bridge site → gestion — amélioration et recette** (complète la ligne P1
  « Bridge site ↔ Cremona » ci-dessus ; 3–8 j, partagé avec Site Core). Résultat :
  une demande de formulaire arrive une seule fois dans la bonne organisation,
  avec une installation reproductible. Acceptation : deux sites et deux
  organisations réelles ou pilotes isolées ; refus d'un jeton ou d'une référence
  non autorisés ; test de doublon, panne, reprise et visibilité des échecs et
  de la réception ; procédure de configuration et contrôle après installation.
  Dépendances : connecteur Site Core, accès et sites pilotes. La reprise
  d'historique et la bascule Contempo restent des décisions distinctes.
- **CRM essentiel — correction et recette** (complète « Relation client
  unifiée » et « Messagerie client synchronisée » ; 2–5 j hors évolutions de
  messagerie déjà suivies). Résultat : un artisan non technicien suit un prospect
  ou client depuis une demande du site ou une saisie manuelle jusqu'aux actions
  suivantes. Acceptation : parcours testé avec recherche, historique, notes,
  document, tâche et rendez-vous, droits et organisation corrects ; corrections
  nécessaires identifiées et effectuées ; absence d'obligation de connecter une
  boîte e-mail. Dépendances : utilisateur pilote, modules CRM et agenda ; bridge
  uniquement pour le parcours provenant du site.
- **Accès et navigation — amélioration** (complète le chantier A ; 2–5 j après
  inventaire des mécanismes existants). Résultat : accès lisible aux outils
  activés et identité de l'entreprise respectée. Acceptation : un utilisateur
  pilote trouve le site et la gestion avec des libellés cohérents, ne voit pas
  de module désactivé et comprend les connexions nécessaires ; droits vérifiés
  côté serveur. Dépendances : configuration de l'organisation et coordination
  avec le site. Évaluer les accès existants avant toute décision ; ni fusion des
  applications ni SSO ne sont des prérequis de cette tâche.

### P2 commercial — prestations et devis

- **Prestations et dossiers transverses — étude puis amélioration éventuelle**
  (complète le cadrage Offre et le pack luthier ; étude 2–4 j, réalisation à
  estimer après cas pilote). Résultat : déterminer le plus petit socle adapté à
  plusieurs métiers pour description, prix et informations utiles, liens avec
  client et demande, dossier ou intervention, statuts et reprise en devis si
  utile. Acceptation de l'étude : au moins un parcours hors lutherie documenté,
  choix de réutilisation et limites des modèles existants, critères et estimation
  de réalisation. Une éventuelle livraison doit tester le parcours et préserver
  intégralement les spécialisations et usages Contempo. Dépendances : artisan
  pilote, CRM et règles de devis ; aucune refonte universelle présupposée.
- **Devis — correction et recette** (complète les chantiers E et
  « Administration commerciale » ; 2–5 j hors développements révélés par la
  recette). Résultat : devis simple à produire et suivre à partir d'un client
  et, si pertinent, de prestations existantes. Acceptation : création, lignes,
  totaux, numérotation, mentions et conditions, PDF, suivi et droits vérifiés
  sur un cas réel ou pilote ; adaptations du modèle et corrections consignées.
  Dépendances : paramètres et validation des mentions par l'entreprise. La
  facturation électronique n'est pas une condition de vente des devis.

### P3 commercial — extensions conditionnelles

- **Facturation / Super PDP — chantier prioritaire en cours** : les lots P0 à
  P2 et le jalon d'usage du logo sont suivis dans
  [Facturation et Super PDP](facturation-super-pdp.md#chantier-solution-compatible--périmètre-et-backlog).
  Atelier Ivo Incidit est déjà raccordé selon confirmation d'Ivo ; la connexion
  seule ne démontre pas la compatibilité des fonctions. Estimation à établir
  après confirmation des contrats techniques et du périmètre réglementaire.
- **Sites existants, dont WordPress — étude d'intégration** (nouveau ; 1–3 j
  d'étude, réalisation à estimer). Résultat : déterminer comment utiliser l'API
  de demandes depuis un site existant sans imposer sa refonte. Acceptation : cas
  commercial identifié, contrat, authentification, isolation, idempotence,
  reprise et exploitation décrits ; décision explicite avant développement.
  Dépendances : formulaire et environnement du site choisi. Aucun connecteur
  générique complexe n'est engagé par cette entrée.

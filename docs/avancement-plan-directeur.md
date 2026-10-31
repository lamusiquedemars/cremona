# Avancement du plan directeur Cremona

> Dernière mise à jour : 31 octobre 2026.
>
> Ce document suit les livraisons du [plan directeur produit](plan-directeur-produit-cremona.md). Il ne remplace pas les critères de fin du plan.

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
- Une demande provenant de `maracuja-cms` affiche désormais « Formulaire du
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

- IMAP/SMTP permet de tester une boîte, relever ses messages et envoyer des
  réponses depuis Cremona.
- La relève actuelle est une capture d’historique en lecture seule : elle lit
  les 50 messages récents d’INBOX et, si configuré, des Envoyés.
- Elle ne suit ni la suppression, ni l’archivage, ni l’état lu d’un message
  dans la boîte distante. Supprimer un email ne doit donc ni effacer ni clore
  une demande ou une correspondance Cremona.
- Un email direct importé crée une correspondance ; il ne crée pas encore une
  demande liée dans la file de traitement commune.

État : **partiel**. La connexion technique ne suffit pas encore à offrir la
file unique attendue pour la relation client.

## Reste à faire prioritaire

| Priorité | Fonctionnalité | État | Implémentation attendue |
| --- | --- | --- | --- |
| P0 | Documents privés sur Scaleway | Livré | Le disque `scaleway-private`, le téléversement direct et les téléchargements authentifiés sont actifs en production. Aucun bucket ni fichier ne devient public. |
| P0 | Migration documentaire sécurisée | Livré | L’inventaire, la copie SHA-256, la vérification de taille, la reprise idempotente et le journal sont actifs. Les 4 entrées historiques sans fichier source sont explicitement exclues : aucun document n’était officiel, donc aucune donnée n’est inventée ni basculée. |
| P0 | Politique documentaire | Livré | Les catégories, durées initiales, droits par type et rattachements obligatoires sont configurables dans chaque organisation. Aucun archivage ni effacement automatique : le lot suivant devra traiter la revue à échéance. |
| P0 | Configuration des intégrations | Livré | Scaleway, Super PDP, Google Ads et Brevo sont présentés dans **Configuration de l’organisation**. Super PDP utilise le flux serveur automatique ; la sandbox Burger Queen de Contempo est validée `verified` en production. Les secrets restent côté serveur. |
| P1 | Contrats de location | Livré | Contrat PDF versionné et figé, envoi d’un lien personnel, consultation des documents, acceptation nominative horodatée avec preuve, puis attestation de restitution sont opérationnels. Il s’agit d’une acceptation tracée, non d’une signature électronique qualifiée. |
| P1 | Relation client unifiée | Livré | La file « À traiter » réunit les demandes du site, les messages nécessitant une réponse, les tâches ouvertes et les rendez-vous/appels planifiés en retard ou dans les sept jours. Un email importé ne devient une demande qu’à l’action explicite de l’équipe ; il conserve ainsi son historique sans créer de faux prospect. Les rendez-vous plus éloignés restent dans l’agenda. |
| P1 | Messagerie client synchronisée | Partiel | La boîte réelle Atelier Ivo est relevée automatiquement toutes les 15 minutes. La relève est incrémentale par UID IMAP, reprend par lots et détecte une réinitialisation distante sans créer de doublons. Restent le rapprochement avec le client, la qualification explicite et la création proposée de tâches ou rendez-vous. L’e-mail alimentera la file et l’agenda ; il ne créera pas une seconde interface à surveiller. |
| P1 | Bridge site ↔ Cremona | Partiel | Le contrat et l’outbox existent : soumission idempotente, authentifiée et rejouable. Valider deux organisations réelles, puis basculer Contempo sans supprimer son historique local. |
| P0–P2 | Facturation électronique compatible | Partiel | Socle transverse et connecteur Super PDP présents ; Atelier Ivo Incidit est déjà raccordé selon confirmation d’Ivo. Restent la qualification fiscale, la fiabilité de l’émission, les corrections, la réception, l’e-reporting, la conservation et les preuves de compatibilité. Périmètre, dépendances et critères : [facturation et Super PDP](facturation-super-pdp.md#chantier-solution-compatible--périmètre-et-backlog). |
| P2 | Administration commerciale | À faire | Écran par organisation : coordonnées émetteur, mentions, CGV, conditions, validité, aperçus, droits et réglages de numérotation. Les règles de devis actuelles sont opérationnelles. |
| P2 | Agenda réel | Partiel | Les vues semaine et mois réunissent les sources internes déjà fiables : rendez-vous/appels, rappels, dossiers atelier planifiés et retours de location. Les essais métier, l’alimentation par la messagerie synchronisée et une éventuelle synchronisation Google/Outlook restent des lots distincts, après validation des règles de conflit. |
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

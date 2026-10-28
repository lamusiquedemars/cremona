# Avancement du plan directeur Cremona

> Dernière mise à jour : 28 octobre 2026.
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
- La file **À traiter** est livrée : elle réunit, sans les fusionner, les
  demandes ouvertes, les correspondances auxquelles une réponse est attendue et
  les tâches ouvertes. Chaque ligne ouvre son objet source ; elle n’introduit ni
  nouveau modèle CRM ni doublon de données.

État : **en cours**. Le registre de modules, les rubriques globales et la
présentation éditable fonctionnent mais ne partagent pas encore une source de
définition unique. Les réglages d’administration peu fréquents restent à
auditer.

### B. Accueil et priorités quotidiennes

- Lot B1 livré en production par le commit `dfb2183` : synthèse des demandes, correspondances, tâches, rendez-vous, devis et campagnes.
- Les calculs du jour utilisent le fuseau de l’organisation.
- Les cartes locales indiquent directement la file qu’elles ouvrent, sans répéter l’heure d’accès ni le fuseau ; les campagnes indiquent la dernière synchronisation Google Ads.
- Chaque indicateur ouvre une file filtrée correspondant au contexte.
- Lot B2 livré : les listes détaillées restent entièrement cliquables et leurs descriptions indiquent naturellement ce qu’elles contiennent.

État : **en cours**.

### C. Agenda

- Rendez-vous manuels et Brevo disponibles.
- La navigation conserve le nom `Rendez-vous` conformément au plan.

État : **non commencé** pour l’agrégation Agenda multi-source.

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

État : **bridge en cours de validation ; cadrage métier en cours**. La prochaine
preuve attendue est une soumission réelle sur deux organisations sans fuite ni
doublon de reprise.

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
l'instrument, tarif exceptionnel explicite et montant recopié dans chaque
nouvelle location sans réécrire l'historique. Les grilles actives ne peuvent pas
se chevaucher. La restitution est datée, commentée et tracée.
L'acceptation d'un devis fait aussi désormais passer le dossier atelier lié à
« Accord reçu — à planifier », avant sa planification.

La frontière produit entre opérations et référentiels est maintenant
documentée, sans déplacement d'interface à ce stade : gammes et grilles de
location, prestations atelier et modèles de propositions sont des
référentiels métier ; instruments, dossiers atelier, locations et stock
restent des opérations. Ces référentiels ne doivent pas être absorbés par la
Configuration globale de l'organisation.

Les contrats de location, d’assurance et l’attestation de restitution sont
désormais générés, archivés comme documents privés, hashés et versionnés. Leur
acceptation par lien personnel est interne et traçable ; elle ne prétend pas
être une signature électronique qualifiée. Le détail reste consigné dans le
[Plan Location — contrats, signature et prélèvements](plan-location-contrats-signature.md).

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
- Un email direct importé crée une correspondance ; il n’est pas transformé
  automatiquement en demande. La file **À traiter** l’affiche lorsqu’une
  réponse est attendue, avec les demandes ouvertes et les tâches ouvertes.
- La configuration de boîte affiche désormais son dernier état de relève, afin
  de rendre visible ce que Cremona a réellement importé et quand.

État : **partiel mais utilisable**. La file de travail existe ; restent les
règles de rapprochement et de qualification entre emails directs, appels,
rencontres et demandes du site.

### H. Documents privés et stockage objet

- Les documents métier restent stockés localement par défaut, sans migration ni
  copie automatique des historiques.
- Le disque privé Scaleway est livré comme option explicite par environnement,
  réservée aux documents confidentiels de Cremona. Les médias éditoriaux et
  galeries du CMS restent dans leur site propriétaire.
- Cremona diffuse les documents via sa route authentifiée après contrôle des
  droits ; le bucket ne devient pas public.

État : **livré, activation de production à planifier séparément** avec un dépôt
et téléchargement de contrôle non confidentiel.

### I. Utilisateurs et accès

- L’administration plateforme présente désormais les comptes avec leur état,
  leurs organisations accessibles et leur dernière connexion.
- Le profil est défini par organisation : Propriétaire, Administrateur,
  Collaborateur ou Lecture seule. Il peut hériter de tous les modules actifs
  de l’organisation, ou être limité à une liste explicite.
- Cette liste est appliquée au menu, à l’ouverture des ressources et aux
  actions de création, modification et suppression ; elle n’est pas seulement
  informative.
- Un compte peut être suspendu sans effacer son historique. Création de compte,
  oubli et renouvellement du mot de passe utilisent un lien personnel par
  e-mail ; le mot de passe n’est pas communiqué par un administrateur.

État : **livré pour l’administration centrale**. La délégation de la gestion
des membres à un administrateur client n’est pas engagée : elle doit être
conçue séparément avec son périmètre de sécurité.

## Reste à faire prioritaire

1. Valider le produit Relation client avec la boîte Atelier Ivo : rendre l’état
   de relève compréhensible et définir, à partir de cas réels, le rapprochement
   des emails directs, demandes du site, appels et rencontres dans la file À
   traiter. La suppression distante ne devra jamais fermer une demande.
2. Confirmer, lors de la prochaine création réelle d’un dossier atelier avec
   devis, la paire de références `AT…` / `D…` partagée ; conserver ce résultat
   dans le journal de livraison.
3. Construire l’administration documentaire par organisation : réglages de
   numérotation, aperçu, droits, coordonnées, conditions et mentions ; voir
   [Numérotation et administration commerciale](numerotation-et-administration-commerciale.md).
4. Valider avec Giovanni un dossier de location réel de bout en bout : documents,
   accord, remise, encaissement manuel, restitution et arrêt/ajustement de
   l'encaissement. Corriger ensuite uniquement les règles révélées par ce test ;
   aucun connecteur Dynamics, bancaire ou SEPA n'est engagé.
5. Vérifier le bridge CMS ↔ Cremona avec deux organisations et une soumission
   réelle, sans fuite ni doublon sur reprise ; basculer ensuite Atelier Ivo,
   puis Contempo, sans supprimer les historiques locaux.
6. Auditer les sources de rendez-vous et tâches avant de commencer l’Agenda.
7. Consolider l’architecture de présentation : faire converger le registre de
   modules, les rubriques globales et les libellés éditables, puis poursuivre
   l’audit des écrans d’administration peu fréquents.
8. Après validation d’usage, concevoir la délégation limitée de la gestion des
   membres à l’administrateur d’une organisation, sans accès à la plateforme ni
   aux autres organisations.

## Dette de contrôle connue

La suite globale comporte quatre échecs préexistants, reproductibles isolément, dans les tests de campagne Google Ads et de correspondance. Ils ne sont pas causés par le lot B1, mais doivent être corrigés avant de pouvoir exiger une suite globale entièrement verte comme garde de livraison.

### Devis et administration des numérotations — 14 octobre 2026

Le correctif PDF, la numérotation annuelle des devis et la numérotation
partagée dossier atelier / devis sont actifs en production. Le chantier restant
concerne l’administration documentaire réutilisable et la confirmation d’une
première paire de références créée en situation réelle.

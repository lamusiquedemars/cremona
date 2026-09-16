# Avancement du plan directeur Cremona

> Dernière mise à jour : 16 septembre 2026.
>
> Ce document suit les livraisons du [plan directeur produit](plan-directeur-produit-cremona.md). Il ne remplace pas les critères de fin du plan.

## Avancement

### A. Navigation produit

- Adaptation par organisation des groupes, libellés et capacités visibles livrée.
- Groupes canoniques `Relation client` et `Acquisition` utilisés par les capacités déjà disponibles.
- Aucun groupe vide `Offre`, `Présence en ligne`, `Résultats` ou `Référencement` n’est affiché.

État : **en cours**. Le registre de capacités n’est pas encore la source unique,
et des libellés techniques ou en CamelCase subsistent dans des pages, actions,
fils d’Ariane et statuts. Leur remplacement cohérent est une priorité produit,
pas un simple travail de traduction.

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

État : **opérationnel, à confirmer lors de la prochaine création réelle de
devis**. Cette création contrôlera la référence automatiquement attribuée ;
aucune donnée commerciale fictive ne doit être créée pour ce test.

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

1. Rendre l’interface entièrement métier : audit des libellés, CamelCase,
   titres, fils d’Ariane, statuts et actions ; corriger par familles d’écrans,
   sans masquer un mot technique isolé ni créer de nouveaux doublons.
2. Terminer le produit Relation client : auditer la boîte Atelier Ivo, rendre
   l’état de relève compréhensible et réunir emails directs, demandes du site,
   appels et rencontres dans une file de travail cohérente. La suppression
   distante ne devra jamais fermer une demande automatiquement.
3. Confirmer la numérotation lors de la prochaine création réelle de devis,
   puis conserver ce résultat dans le journal de livraison.
4. Construire l’administration documentaire par organisation : réglages de
   numérotation, aperçu, droits, coordonnées, conditions et mentions ; voir
   [Numérotation et administration commerciale](numerotation-et-administration-commerciale.md).
5. Attendre les retours de Giovanni sur réparation/reméchage, location et vente
   avant de compléter le pack Luthier ou d’ajouter des automatisations.
6. Vérifier le bridge CMS ↔ Cremona avec deux organisations et une soumission
   réelle, sans fuite ni doublon sur reprise ; basculer ensuite Atelier Ivo,
   puis Contempo, sans supprimer les historiques locaux.
7. Auditer les sources de rendez-vous et tâches avant de commencer l’Agenda.

## Dette de contrôle connue

La suite globale comporte quatre échecs préexistants, reproductibles isolément, dans les tests de campagne Google Ads et de correspondance. Ils ne sont pas causés par le lot B1, mais doivent être corrigés avant de pouvoir exiger une suite globale entièrement verte comme garde de livraison.

### Devis et administration des numérotations — 16 septembre 2026

Le correctif PDF et la numérotation des nouveaux devis sont actifs en
production. Le chantier restant concerne l’administration documentaire
réutilisable ; la numérotation des dossiers reste un lot distinct.

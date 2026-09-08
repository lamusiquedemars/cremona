# Avancement du plan directeur Cremona

> Dernière mise à jour : 8 septembre 2026.
>
> Ce document suit les livraisons du [plan directeur produit](plan-directeur-produit-cremona.md). Il ne remplace pas les critères de fin du plan.

## Avancement

### A. Navigation produit

- Adaptation par organisation des groupes, libellés et capacités visibles livrée.
- Groupes canoniques `Relation client` et `Acquisition` utilisés par les capacités déjà disponibles.
- Aucun groupe vide `Offre`, `Présence en ligne`, `Résultats` ou `Référencement` n’est affiché.

État : **en cours**. Le registre de capacités n’est pas encore la source unique et les ressources techniques ne sont pas encore toutes réunies sous `Paramètres`.

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
  un site connecté ; les emails directs, demandes téléphoniques et rencontres
  y rejoignent la même file.
- Atelier Ivo conserve Arcus/CMS comme propriétaire de son catalogue ; Contempo
  est le pilote du futur pack Luthier propriétaire de son parc et de son stock.
- Le contrat du bridge CMS ↔ Cremona est écrit : outbox, idempotence,
  authentification, reprise et bascule sans suppression des historiques.
- Aucun catalogue universel n’a été ajouté au noyau.

État : **cadrage en cours**.

## Reste à faire prioritaire

1. Implémenter et tester le premier lot du bridge CMS ↔ Cremona avec deux
   organisations, dont un formulaire site, sans fuite
   de données ni doublon sur reprise.
2. Basculer le traitement des demandes d’Atelier Ivo, sans supprimer Arcus ni
   les historiques locaux avant vérification.
3. Cadrer le socle commercial de Cremona : bibliothèque de lignes de devis,
   références externes et statut Pennylane.
4. Modéliser le pack Luthier pour Contempo avant tout import Dynamics ou stock.
5. Auditer les sources de rendez-vous et tâches avant de commencer l’Agenda.

## Dette de contrôle connue

La suite globale comporte quatre échecs préexistants, reproductibles isolément, dans les tests de campagne Google Ads et de correspondance. Ils ne sont pas causés par le lot B1, mais doivent être corrigés avant de pouvoir exiger une suite globale entièrement verte comme garde de livraison.

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
- Aucun catalogue universel n’a été ajouté au noyau.

État : **cadrage en cours**.

## Reste à faire prioritaire

1. Terminer B2 et observer l’accueil avec les données réelles d’Atelier Ivo.
2. Valider avec Ivo les décisions ouvertes du cadrage : réservation d’archets, essai à distance, acompte, paiement et propriété future du catalogue.
3. Choisir le premier flux vertical complet : recommandation actuelle, **essai d’archet**.
4. Concevoir le module métier Luthier et son contrat de projection vers Maracuja CMS avant toute migration du catalogue.
5. Auditer les sources de rendez-vous et tâches avant de commencer l’Agenda.
6. Reprendre le registre canonique de capacités et terminer le chantier A.

## Dette de contrôle connue

La suite globale comporte quatre échecs préexistants, reproductibles isolément, dans les tests de campagne Google Ads et de correspondance. Ils ne sont pas causés par le lot B1, mais doivent être corrigés avant de pouvoir exiger une suite globale entièrement verte comme garde de livraison.

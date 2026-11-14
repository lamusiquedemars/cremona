# Pilote campagne — Atelier Ivo Incidit

Ce pilote est la référence de la future capacité Acquisition de Cremona. Il
doit permettre de préparer, créer, activer et suivre une campagne Google Ads
depuis Cremona, sans stocker de secret dans le site Atelier.

## Limites de responsabilité

- **Atelierivoincidit.fr** mesure les actions consenties, conserve la demande
  et transmet son attribution à Cremona.
- **Cremona** conserve le brouillon, les versions, les identifiants externes,
  les métriques agrégées et le journal d'audit.
- **Google Ads** reçoit la campagne, diffuse les annonces et gère la
  facturation, l'identité de l'annonceur et ses validations.
- Ivo renseigne les comptes, secrets et paramètres depuis les écrans prévus ;
  aucun compte ou identifiant n'est créé automatiquement.

## Parcours cible

| Étape | Action dans Cremona | Effet extérieur | Confirmation |
|---:|---|---|---|
| 1 | Créer l'organisation et rattacher le site | Aucun | Non requise |
| 2 | Renseigner la connexion Google Ads | Aucun appel Ads | Interface utilisateur |
| 3 | Créer le brouillon de campagne | Aucun | Non requise |
| 4 | Vérifier l'aperçu de publication | Aucun | Non requise |
| 5 | « Créer dans Google Ads en pause » | Crée budget, campagne, groupes, mots-clés et annonces à l'état `PAUSED` | Confirmation explicite |
| 6 | Synchroniser les métriques | Lecture Google Ads seulement | Non requise |
| 7 | « Activer la campagne » | Autorise sa diffusion et sa dépense | Confirmation explicite séparée |

## Données du brouillon Google Search

La fiche Campaign de Cremona devra porter, en plus de son registre commun :

- objectif de conversion : `generate_lead` ;
- URL finale et clé UTM ;
- pays / zone ciblée, langue et calendrier ;
- budget journalier et devise ;
- stratégie d'enchère ;
- groupes d'annonces ;
- mots-clés et mots-clés à exclure ;
- annonces responsives et assets ;
- statut local, statut Google Ads et identifiants externes ;
- aperçu de la demande API qui sera envoyée, sans secrets.

## Garde-fous

1. La publication exige une connexion Ads prête et un brouillon valide.
2. Le premier appel API crée toujours la campagne `PAUSED`.
3. L'activation est impossible sans identifiant Google Ads enregistré et sans
   confirmation explicite dans l'interface.
4. Chaque création, échec, synchronisation et activation est journalisée dans
   `organization_audit_logs`, sans credentials ni données personnelles.
5. Une modification de brouillon après publication crée une version locale ;
   aucune mutation Google Ads n'est silencieuse.

## Preuves de fin du pilote

- le brouillon est visible dans l'espace Atelier de Cremona ;
- la campagne Google Ads créée depuis Cremona est en pause ;
- son identifiant externe est visible dans Cremona ;
- le site produit une attribution sans transmettre de contenu de formulaire à
  Google ;
- `generate_lead` est compté dans Google Ads et rapproché aux demandes dans
  Cremona ;
- l'activation et chaque synchronisation disposent d'une ligne de journal.

# ADR 0002 — Packs métier et présentation adaptée

- Statut : accepté
- Date : 31 août 2026
- Portée : Cremona canonique, puis projections contrôlées vers Maracuja CMS

## Contexte

Cremona doit servir plusieurs métiers sans imposer le langage d'un CRM générique
ni multiplier les applications par client. Les capacités transversales —
contacts, tâches, rendez-vous, documents ou devis — ont besoin d'un modèle
canonique afin de conserver les droits, l'isolation et les intégrations.

Leur présentation doit toutefois parler le langage du métier de l'organisation.
Un exemple illustratif : un pack Luthier peut présenter « Relation client »
comme « Clients et atelier » et une tâche comme « À faire à l'atelier » ; un
pack Avocat peut présenter les mêmes capacités comme « Dossiers et échéances ».
Ces formulations sont des exemples, jamais des valeurs réservées ou codées en
dur.

## Décision

1. L'organisation choisit un pack métier actif. Les capacités communes restent
   des modèles canoniques uniques ; elles ne sont pas dupliquées par profession.
2. Chaque pack fournit une définition centralisée de présentation : libellés,
   groupes de navigation, écrans et raccourcis visibles, ainsi que les modules
   activables par défaut.
3. Les objets propres à une profession appartiennent à un module métier dédié,
   avec son propre vocabulaire, ses règles et ses tests. Le noyau ne crée pas de
   catalogue ou de table `Products` universelle.
4. Une adaptation de présentation ne modifie jamais les règles d'accès,
   l'isolation `organization_id`, les contrats API ou le sens des données.
5. Les exceptions ponctuelles ne sont pas codées au nom d'un client. Lorsqu'une
   variation est durable, elle entre dans la définition du pack ; lorsqu'elle
   porte une donnée métier, elle entre dans son module métier.

## Mise en œuvre prévue

Le noyau introduira un résolveur de pack unique, utilisé par la navigation et
les ressources Filament. Les adaptations éditables seront limitées à des clés
explicitement prévues. Un champ JSON générique ne servira pas à accumuler des
libellés ou des modèles métier arbitraires.

Chaque pack aura des tests : visibilité des capacités, vocabulaire affiché,
droits, et absence de fuite entre organisations utilisant des packs différents.

## Conséquences

- On peut faire évoluer l'expérience d'un métier sans fragmenter le noyau.
- Les exemples utilisés pendant le cadrage restent libres : aucun objet ou nom
  de métier n'est présupposé par Cremona.
- Avant de construire un premier module métier, son vocabulaire et ses objets
  doivent être validés avec le professionnel concerné.

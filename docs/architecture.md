# Architecture de Cremona

## Responsabilité du produit

Cremona est le SaaS métier central de Maracuja Digital. Il ne remplace pas Maracuja CMS Starter, qui reste responsable des sites publics indépendants.

```text
Sites publics Maracuja CMS
        ↓ API sécurisée
Cremona
        ├── noyau commun
        ├── modules transversaux
        └── packs métier
```

## Modèle multi-organisation

Une organisation représente une entreprise cliente. Un utilisateur peut appartenir à plusieurs organisations avec un rôle différent dans chacune.

Les données métier portent obligatoirement `organization_id` et utilisent `BelongsToOrganization`. Le global scope est fermé par défaut : sans `OrganizationContext`, aucune ligne n’est visible. Une création sans organisation active est refusée.

Les opérations de plateforme qui doivent traverser plusieurs organisations doivent retirer explicitement `OrganizationScope`. Cette exception doit rester rare et testée.

## Rôles initiaux

- `owner` : propriétaire de l’espace ;
- `administrator` : gestion courante et membres ;
- `collaborator` : travail métier ;
- `viewer` : lecture seule.

Les permissions fines viendront compléter le rôle lorsque les premiers modules seront intégrés.

## Modules et packs métier

`organization_modules` active une capacité pour une organisation et conserve sa configuration non sensible. Les secrets d’intégration auront un stockage chiffré dédié.

Les premiers packs prévus sont :

- Luthier : musiciens, instruments, interventions, documents ;
- Avocat : clients, dossiers, parties, audiences, échéances et documents privés ;
- Artisan : clients, chantiers, interventions, devis et médias.

Le noyau commun ne doit pas adopter le vocabulaire d’un métier particulier.

### Adaptation de présentation par pack

Chaque capacité commune conserve un modèle et des règles métier stables, mais sa
présentation est résolue par le pack actif de l'organisation : intitulés, groupes
de navigation, écrans visibles, raccourcis et champs propres au métier.

Par exemple, un pack Luthier peut présenter la relation client comme « Clients
et atelier » et une tâche comme « À faire à l'atelier » ou « Relance client ».
Un pack Avocat peut présenter les mêmes capacités sous « Dossiers et échéances ».
Ces exemples illustrent le mécanisme : ils ne constituent ni des noms réservés,
ni des valeurs à coder dans le noyau.

Les objets propres à une profession sont définis dans son module métier, avec
son vocabulaire réel. Le noyau ne crée pas de table catalogue universelle pour
les absorber. Un objet destiné à être publié peut avoir une projection contrôlée
vers Maracuja CMS, sans devenir la source de vérité du site public.

## Règles avant import des modules du CMS

Un module du starter ne peut entrer dans Cremona qu’après :

1. ajout de la propriété organisation ;
2. remplacement des contraintes globales par des contraintes composites ;
3. politiques d’accès par rôle ;
4. tests anti-fuite entre deux organisations ;
5. séparation entre médias publics et documents privés ;
6. traitement asynchrone des opérations lentes.

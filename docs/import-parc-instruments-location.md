# Import initial — parc d’instruments en location

Ce document prépare le futur import contrôlé du parc. Il ne remplace pas une
validation métier et ne crée pas de données automatiquement.

## Prérequis dans Cremona

Avant d'importer les instruments, l'organisation crée ses **gammes de
location**, puis ses **grilles de location**. Chaque grille active définit une
famille, des tailles couvertes, une gamme et un loyer mensuel.

## Colonnes attendues

| Colonne | Obligatoire | Exemple | Règle |
| --- | --- | --- | --- |
| `reference` | oui | `V-024` | Unique dans l'organisation. |
| `intitule` | oui | `Violon Mirecourt d’étude` | Nom de travail de la fiche. |
| `famille` | oui pour une location | `violon` | Violon, alto, violoncelle ou contrebasse. |
| `taille` | oui pour une location | `1/4` | Code accepté pour la famille. |
| `gamme` | oui pour une location | `Étude` | Doit correspondre à une gamme active existante. |
| `luthier_fabricant` | non | `Atelier X` | Information de fiche. |
| `annee` | non | `2018` | Information de fiche. |
| `proprietaire` | non | `owned` | Propriété de l'atelier, dépôt-vente ou confié. |
| `proposer_location` | oui | `oui` | Déclenche la vérification de la grille. |
| `tarif_exceptionnel_mensuel_ht` | non | `85` | À renseigner uniquement pour une dérogation réelle. |

## Contrôles avant création

Chaque ligne doit être prévisualisée avec son résultat : grille trouvée et
loyer proposé, ou erreur précise. Sont bloquants : référence dupliquée, valeur
de famille/taille inconnue, gamme inactive, absence de grille, ou plusieurs
grilles applicables. Un tarif exceptionnel ne contourne pas la qualification de
l'instrument : famille, taille et gamme restent obligatoires.

L'import devra fonctionner en simulation puis en validation explicite. Il ne
mettra jamais à jour ou ne supprimera jamais un instrument existant sans une
clé de rapprochement et un choix d'action visibles.

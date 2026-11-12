# Consignes propres au dépôt Cremona

- Appliquer les consignes globales de travail et de déploiement d’Ivo.

## Coordination Git entre les Macs et GitHub

La référence commune est `maracuja-control/docs/methode-travail-quotidienne.md`
et `maracuja-control/docs/workflow-developpement.md`. Lorsqu'ils sont
disponibles dans le dépôt frère `../maracuja-control`, les lire avant tout
travail de code. Ne pas recréer une méthode concurrente dans ce dépôt.

Avant de coder, de créer une branche ou de changer de branche :

- examiner l'état Git local, les branches locales et distantes, les commits
  récents et les PR éventuelles ;
- identifier le chantier correspondant à la demande métier à partir de son
  contenu et de son historique, jamais du seul nom de branche ;
- reprendre cette branche seulement si la correspondance est certaine et que
  l'état local permet une reprise sans risque ;
- demander au propriétaire de trancher en cas d'ambiguïté, de divergence ou
  de risque pour un travail existant ;
- ne jamais écraser des modifications locales, ni fusionner ou rapprocher
  silencieusement deux développements incompatibles ;
- traiter une branche comme le support d'un chantier, jamais comme la copie
  de travail d'un ordinateur.

Ne jamais fusionner ni déployer sans autorisation explicite. La branche
`feat/demo-maracuja-sso-pilot` est la référence de travail observée pour
Cremona ; ne pas présumer que `main` est la bonne base sans décision de
régularisation.

- Dans chaque compte rendu intermédiaire et chaque livraison finale, inclure explicitement deux rubriques : **Avancement** et **Reste à faire**.
- Le reste à faire doit distinguer les travaux produit prévus des blocages éventuels de la livraison en cours.
- Ne jamais morceler le compte rendu par fichier ou micro-tâche. Pour un chantier
  multi-dépôts, travailler jusqu'à la fin d'un lot cohérent puis restituer un
  état global unique : objectif du lot, éléments effectivement livrés, éléments
  volontairement non commencés ou non livrés, tests, commits/push/déploiements
  et prochain lot. Les messages intermédiaires ne servent qu'à signaler un vrai
  blocage ou une décision produit nécessaire.
- Pour chaque bilan de lot, indiquer explicitement : **Avancement global** avec
  un pourcentage estimé par rapport au programme recadré, **Reste à faire** et
  **Étape suivante**. Le pourcentage porte sur le résultat produit, pas sur le
  nombre de fichiers modifiés.

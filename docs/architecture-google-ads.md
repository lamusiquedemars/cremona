# Architecture Google Ads de Cremona

> Cette architecture complète le runbook canonique
> `maracuja-cms-starter/docs/google-acquisition-runbook.md`. Le Starter décrit
> GA4, GTM, Search Console et le site ; ce document décrit exclusivement le
> rôle d’agence Cremona, ses secrets et ses publications Ads.

## Séparation fonctionnelle

- `Acquisition > Campagnes` contient le travail métier et les actions explicites de publication.
- `Configuration de l’organisation > Publicité` contient le compte Google Ads propre à l’organisation active, son état, son autorisation et la synchronisation en lecture des résultats.

## Infrastructure agence centralisée

L’infrastructure commune Maracuja contient quatre éléments : le developer token,
l’application OAuth (identifiant et secret), l’identifiant MCC et une unique
autorisation OAuth d’agence. Les quatre premiers restent dans le `.env` de
production. L’autorisation de l’agence est stockée chiffrée dans le coffre
plateforme `platform_settings`.

Une organisation ne contient que son identifiant de compte Google Ads. Le
runtime centralisé combine cet identifiant avec les credentials agence côté
serveur, sans recopier un secret dans l’organisation.

La bascule est volontairement en deux temps depuis `Plateforme > Infrastructure
Google Ads` :

1. autoriser une fois le compte agence Maracuja ;
2. activer le mode centralisé, uniquement lorsque l’application OAuth, l’accès
   API Basic/Standard et l’autorisation sont prêts.

Avant l’étape 2, le mode historique reste strictement actif. L’action `Revenir
au mode historique` coupe immédiatement l’usage centralisé sans supprimer ni
écrire de donnée organisationnelle. Les anciens credentials chiffrés peuvent
donc être retirés plus tard, uniquement après une période de vérification
explicite.

## Stockage réellement utilisé

Les données propres au client restent dans `organization_integrations` : `customer_id` et date de dernière synchronisation. La colonne `credentials` est chiffrée par Laravel et le modèle la masque lors de la sérialisation.

Pendant la transition, les anciennes connexions conservent leurs credentials
chiffrés, afin que le retour au mode historique soit possible. Dès que le mode
centralisé est activé, ils ne sont plus lus à l’exécution : seule la valeur
`customer_id` de l’organisation demeure utilisée.

## Incident du 1er septembre 2026 — règle de non-régression

L’incident `DEVELOPER_TOKEN_INVALID` n’était pas un refus OAuth Google. Il a
été causé par une écriture défaillante dans `.env` : le developer token y avait
été remplacé par la valeur littérale `v`, puis recopié dans les intégrations.

Conséquences pour cette architecture :

- aucune migration de centralisation ne copie de developer token, secret OAuth
  ou refresh token vers les organisations ;
- le package crée seulement un coffre plateforme vide et conserve le runtime
  historique tant que la bascule explicite n’a pas eu lieu ;
- l’autorisation centrale est créée par le flux OAuth plateforme, jamais par
  une commande de copie de secret ;
- toute modification future de `.env` est vérifiée par une synchronisation
  lecture seule avant d’activer une campagne.

## Relevé automatique des résultats

La commande `cremona:sync-google-ads` relève les résultats en lecture seule pour
chaque organisation active disposant d'une connexion Google Ads. Elle est
planifiée toutes les heures par Laravel et protégée contre les exécutions qui se
chevauchent. Le cron LWS existant doit continuer à appeler `php artisan
schedule:run` chaque minute.

Chaque succès ou échec est journalisé dans l'audit de l'organisation. Le dernier
incident est aussi visible dans `Configuration de l’organisation > Publicité` ;
les secrets et jetons n'y sont jamais affichés.

## Cycle de publication et activation

Une campagne est toujours créée entièrement en pause : campagne, groupes
d'annonces et annonces. Cette règle empêche toute diffusion tant que le
contrôle humain n'est pas terminé.

L'action `Activer dans Google Ads` applique ensuite cet ordre :

1. relever les groupes et annonces encore en pause dans la campagne concernée ;
2. les passer à `ENABLED` ;
3. passer la campagne à `ENABLED` ;
4. seulement après succès Google, enregistrer le statut `active` dans Cremona.

Une erreur avant la dernière étape laisse la campagne elle-même en pause : elle
ne peut donc pas diffuser partiellement. Le budget, les mots-clés, les annonces
et les dates ne sont pas modifiés par l'activation.

Après toute première activation, contrôler dans Google Ads :

- les groupes et annonces ne doivent pas présenter `AD_GROUPS_PAUSED` ou
  `AD_GROUP_ADS_PAUSED` ;
- `BIDDING_STRATEGY_LEARNING` est normal pendant la phase d'apprentissage ;
- une campagne peut être `SERVING` tout en étant bloquée par des groupes ou des
  annonces en pause : il faut toujours vérifier ces raisons de statut.

## Contrat d’exécution agence

Avant de créer une campagne réelle, Cremona doit disposer d’une fiche client
complète et de ses portes de lancement validées : GTM publié, `generate_lead`
événement clé GA4, lien GA4 vers le compte Ads client, conversion Ads importée,
budget, zone de présence, annonces et mots-clés revus. Ces contrôles restent
visibles dans Google Ads : l’API ne les remplace pas.

Le compte client est la référence de diffusion. Cremona est la référence de
préparation et de pilotage. En cas d’écart (zone inattendue, objectif par
défaut, groupe en pause), l’agent :

1. consigne l’écart dans la fiche client ;
2. consulte l’historique des modifications Google Ads ;
3. corrige uniquement le réglage divergent après accord si la correction a un
   impact de diffusion ou de dépense ;
4. synchronise en lecture seule et conserve la preuve.

Une campagne nouvelle peut commencer avec `Maximize Clicks` afin de recueillir
un premier volume sous budget plafonné. Le passage à `Maximize Conversions`
est une décision commerciale distincte, fondée sur des conversions réelles ;
il n’est jamais appliqué automatiquement par Cremona.

## Règle de sécurité

Une campagne ne peut être synchronisée que lorsque les cinq valeurs propres à
l’organisation sont présentes. Les secrets ne sont jamais affichés dans les
listes ; ils peuvent être remplacés uniquement depuis le formulaire protégé de
l’organisation.

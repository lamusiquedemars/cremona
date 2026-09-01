# Architecture Google Ads de Cremona

## Séparation fonctionnelle

- `Acquisition > Campagnes` contient le travail métier et les actions explicites de publication.
- `Configuration de l’organisation > Publicité` contient le compte Google Ads propre à l’organisation active, son état, son autorisation et la synchronisation en lecture des résultats.

## Stockage réellement utilisé

Les données propres au client restent dans `organization_integrations` : `customer_id` et date de dernière synchronisation. La colonne `credentials` est chiffrée par Laravel et le modèle la masque lors de la sérialisation.

Chaque organisation conserve, chiffrés dans `organization_integrations`, son
`customer_id`, son developer token, ses credentials OAuth, son refresh token et,
si nécessaire, son identifiant de compte gestionnaire. Aucun credential n’est
remplacé par une valeur globale à l’exécution.

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

## Règle de sécurité

Une campagne ne peut être synchronisée que lorsque les cinq valeurs propres à
l’organisation sont présentes. Les secrets ne sont jamais affichés dans les
listes ; ils peuvent être remplacés uniquement depuis le formulaire protégé de
l’organisation.

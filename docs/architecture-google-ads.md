# Architecture Google Ads de Cremona

## Séparation fonctionnelle

- `Acquisition > Campagnes` contient le travail métier et les actions explicites de publication.
- `Configuration de l’organisation > Publicité` contient le compte Google Ads propre à l’organisation active, son état, son autorisation et la synchronisation en lecture des résultats.
- `Plateforme > Infrastructure Google Ads` montre aux seuls administrateurs Maracuja l’état de la configuration centrale, sans rendre les valeurs des secrets.

## Stockage réellement utilisé

Les données propres au client restent dans `organization_integrations` : `customer_id`, autorisation OAuth (`refresh_token`) et date de dernière synchronisation. La colonne `credentials` est chiffrée par Laravel et le modèle la masque lors de la sérialisation.

Les credentials partagés de l’application Maracuja peuvent être centralisés dans la configuration serveur :

- `GOOGLE_ADS_DEVELOPER_TOKEN`
- `GOOGLE_ADS_OAUTH_CLIENT_ID`
- `GOOGLE_ADS_OAUTH_CLIENT_SECRET`
- `GOOGLE_ADS_LOGIN_CUSTOMER_ID` (facultatif)

Ils ont priorité à l’exécution et ne sont jamais copiés vers l’organisation lors d’une nouvelle autorisation OAuth.

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

## Compatibilité et migration restante

Aucune donnée existante n’est supprimée ou déplacée par ce chantier. Si les variables centrales ne sont pas encore configurées, Cremona continue d’utiliser les credentials historiques chiffrés dans chaque organisation. L’écran organisationnel ne permet plus de les lire ni de les modifier.

Une centralisation complète pourra être terminée séparément après sauvegarde et validation de la configuration serveur : configurer les variables centrales, contrôler chaque connexion, puis supprimer uniquement les quatre clés techniques redondantes des coffres organisationnels. Le `customer_id` et le `refresh_token` doivent rester par organisation. Cette suppression n’est volontairement pas automatisée ici.

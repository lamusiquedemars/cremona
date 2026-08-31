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

## Compatibilité et migration restante

Aucune donnée existante n’est supprimée ou déplacée par ce chantier. Si les variables centrales ne sont pas encore configurées, Cremona continue d’utiliser les credentials historiques chiffrés dans chaque organisation. L’écran organisationnel ne permet plus de les lire ni de les modifier.

Une centralisation complète pourra être terminée séparément après sauvegarde et validation de la configuration serveur : configurer les variables centrales, contrôler chaque connexion, puis supprimer uniquement les quatre clés techniques redondantes des coffres organisationnels. Le `customer_id` et le `refresh_token` doivent rester par organisation. Cette suppression n’est volontairement pas automatisée ici.

# Stockage des documents privés

Les galeries et fichiers éditoriaux d'un site Maracuja restent dans le
filesystem de ce site. Cremona ne les déplace pas dans Object Storage.

Les documents métier confidentiels de Cremona — contrats, expertises, pièces
de propriété, documents de location — sont stockés localement par défaut. Une
installation peut choisir le bucket privé Scaleway uniquement pour ce flux.

## Activation explicite

L'application conserve les documents existants sur leur disque historique.
Il n'y a ni migration ni copie automatique lors de l'activation.

Dans l'environnement concerné :

```env
PRIVATE_DOCUMENTS_DISK=scaleway_private
SCW_OBJECT_STORAGE_ACCESS_KEY=…
SCW_OBJECT_STORAGE_SECRET_KEY=…
SCW_OBJECT_STORAGE_REGION=fr-par
SCW_OBJECT_STORAGE_ENDPOINT=https://s3.fr-par.scw.cloud
SCW_PRIVATE_DOCUMENTS_BUCKET=cremona-private-prod
```

Après toute modification, reconstruire le cache de configuration et effectuer
un dépôt puis un téléchargement de contrôle avec un document de test non
confidentiel. Les clés n'entrent jamais dans Git, Control ou les sites clients.

## Accès et sécurité

Le bucket est privé. Cremona vérifie d'abord l'identité de l'utilisateur et ses
droits sur l'organisation, puis diffuse le fichier via la route authentifiée.
Elle ne renvoie pas d'URL publique ou présignée. Les documents restent
versionnés et ne sont pas supprimés par l'application.

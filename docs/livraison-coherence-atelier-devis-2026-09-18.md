# Cohérence atelier / devis — 18 septembre 2026

## Décision et comportement

Décision confirmée par Ivo : un numéro partagé pour le dossier atelier et son devis (`AT260001` / `D260001`). Le compteur est annuel et propre à chaque organisation. Les devis autonomes utilisent le même compteur ; le dossier réserve son numéro dès sa création et le devis le reprend. Le passage au nouvel an ne change pas le numéro d’un dossier existant.

Les anciens identifiants automatiques `AT-…` / `Q-…` sont conservés dans `previous_reference`, affichés et recherchables. Les numéros de devis déjà au format moderne restent inchangés. La reprise s’arrête en cas de plusieurs devis liés à un même dossier. Les URL, fondées sur les identifiants publics, restent stables.

Le client ajouté au devis complète le dossier sans client. Pour les brouillons, un changement de client est répercuté dans les deux sens lorsque le client correspondant était identique ou vide. Un client différent explicitement renseigné et les destinataires des devis déjà envoyés sont préservés. L’accord client passe le dossier à « Accord reçu — à planifier » et conserve la date de cet accord lors de la planification.

## Validation

32 tests ciblés, 183 assertions : parcours atelier, numérotation partagée, changement d’année, non-réutilisation des compteurs supprimés, reprise répétable des références, client, liens entre écrans, documents PDF, stock et accès aux modules. Pint et contrôle de syntaxe du lot serveur réussis.

## Publication

Le serveur a été contrôlé en lecture seule. Il possède le générateur `D + année + compteur`, mais l’ancien lot conservait volontairement les références existantes. L’état des migrations et les données réelles n’ont pas pu être contrôlés par SFTP.

Le lot est préparé dans le répertoire protégé `~/htdocs/cremona.maracujadigital.fr/database/releases/20260918-atelier-devis` (accès HTTP interdit). Il conserve les libellés français propres au serveur et la classe `BusinessEditRecord` utilisée par les formulaires de production. L’application active n’est pas remplacée lors de ce dépôt.

Conformément à `docs/deploiement-lws.md`, Ivo active le lot dans le terminal SSH LWS :

```bash
bash ~/htdocs/cremona.maracujadigital.fr/database/releases/20260918-atelier-devis/install.sh
```

Le script vérifie que les fichiers de production n’ont pas changé depuis l’audit, sauvegarde les fichiers remplacés dans `database/release-backups`, place le site en maintenance, installe les 15 fichiers, reconstruit l’autoload, applique uniquement les trois migrations du lot, reconstruit les caches et rouvre le site. Un échec pendant l’activation conserve la maintenance pour diagnostic. Les références et données métier reprises ne sont pas annulées par un rollback de code.

Le contrôle final recherche le devis historique `Q-01M25M17` et vérifie son lien avec le dossier historique `AT-01M25KZJ`, leurs nouveaux numéros communs, leur client et leur accord. Aucun devis de test n’est créé en production. L’activation et les nouveaux numéros réels restent à confirmer par la sortie de cette commande.

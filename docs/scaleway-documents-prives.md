# Documents privés — Scaleway Object Storage

## Règle de sécurité

Les documents privés (contrats, PDF de devis et facture, pièces jointes et
preuves) utilisent le disque Laravel `scaleway-private`. Ce disque ne possède
pas d’URL publique. Un téléchargement passe toujours par Cremona, qui vérifie
l’authentification, le droit d’accès et l’organisation concernée avant de
diffuser le fichier.

Les médias publics du site restent sur leur circuit public ; ils ne partagent
ni le bucket ni les liens des documents privés.

## Configuration serveur

Les seules variables nécessaires dans le `.env` du serveur sont :

```dotenv
PRIVATE_DOCUMENTS_DISK=scaleway-private
SCW_OBJECT_STORAGE_ACCESS_KEY=…
SCW_OBJECT_STORAGE_SECRET_KEY=…
SCW_OBJECT_STORAGE_REGION=fr-par
SCW_OBJECT_STORAGE_ENDPOINT=https://s3.fr-par.scw.cloud
SCW_OBJECT_STORAGE_PATH_STYLE=false
SCW_PRIVATE_DOCUMENTS_BUCKET=…
```

Elles restent sur le serveur. Elles ne sont ni commitées, ni affichées dans
Configuration de l’organisation, ni copiées dans un export. Après une mise à
jour de ces variables :

```bash
php artisan optimize:clear
php artisan config:cache
```

Dans **Plateforme → Organisations → [organisation]**, le bloc « Documents
privés et connexions » indique si le stockage Scaleway est prêt. Il ne montre
que l’état, jamais les identifiants.

## Migration des documents existants

Les nouveaux documents partent directement sur Scaleway. Les documents
historiques conservent leur disque enregistré tant qu’ils ne sont pas vérifiés.
La migration ne supprime jamais la copie locale : cet effacement éventuel est
une décision de conservation distincte.

1. Faire d’abord un inventaire sans modification :

   ```bash
   php artisan private-documents:migrate-to-scaleway
   ```

2. Examiner les éventuels statuts `failed` dans la table
   `private_document_storage_migrations` et résoudre la cause avant de
   recommencer.

3. Migrer une organisation pilote, de préférence avec une limite :

   ```bash
   php artisan private-documents:migrate-to-scaleway --organization=contempo-luthiers --limit=20 --execute
   ```

4. Confirmer les téléchargements authentifiés dans Cremona, puis retirer la
   limite pour cette organisation. La commande peut être relancée : la copie
   est idempotente et le basculement du registre intervient seulement après
   contrôle de taille et SHA-256.

Chaque ligne de journal contient la source, la cible, la taille, l’empreinte,
le nombre de tentatives, l’état et l’heure de vérification. Le fichier source
reste intact après une migration confirmée.

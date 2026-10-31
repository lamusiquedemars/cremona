#!/usr/bin/env bash

# Publication atomique Cremona sur LWS.
#
# Invariant non négociable : les fichiers utilisateurs vivent dans
# $app/shared/storage/app, hors des releases. Cette commande refuse d'activer
# une release si storage/app n'est pas le lien prévu vers ce répertoire.

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
deploy_host="${CREMONA_DEPLOY_HOST:-maracuja-prod}"
application_path="${CREMONA_APPLICATION_PATH:-/home/cp2802197p33/apps/cremona}"
commit="$(git -C "$repository_root" rev-parse --short=12 HEAD)"
release="$(date -u +%Y%m%d_%H%M%SZ)_${commit}"
stage_path="$application_path/releases/$release"

if [[ -n "$(git -C "$repository_root" status --porcelain)" ]]; then
    echo "Déploiement annulé : le dépôt contient des modifications non commitées." >&2
    exit 1
fi

# Assets Vite are deliberately ignored by Git. Build them locally with the
# code that is about to be released, then ship that exact build to staging.
(cd "$repository_root" && npm run build)

git -C "$repository_root" archive --format=tar HEAD \
    | ssh "$deploy_host" "mkdir -p '$stage_path' && tar -xf - -C '$stage_path'"

tar -C "$repository_root/public/build" -cf - . \
    | ssh "$deploy_host" "mkdir -p '$stage_path/public/build' && tar -xf - -C '$stage_path/public/build'"

ssh "$deploy_host" "APP_PATH='$application_path' STAGE_PATH='$stage_path' bash -s" <<'REMOTE'
set -euo pipefail

shared_storage="$APP_PATH/shared/storage/app"
previous_release="$(readlink -f "$APP_PATH/current")"

test -d "$shared_storage"
test -d "$shared_storage/public"
test -L "$previous_release/storage/app"
test "$(readlink "$previous_release/storage/app")" = '../../../shared/storage/app'

# Une archive Git ne peut apporter ici que les .gitignore vides. Tout autre
# contenu ferait suspecter une écriture accidentelle dans les données et bloque
# donc le déploiement avant toute bascule.
unexpected_file="$(find "$STAGE_PATH/storage/app" -type f ! -name '.gitignore' -print -quit)"
if [[ -n "$unexpected_file" ]]; then
    echo "Déploiement annulé : contenu inattendu dans storage/app du staging : $unexpected_file" >&2
    exit 1
fi

find "$STAGE_PATH/storage/app" -type f -name '.gitignore' -delete
find "$STAGE_PATH/storage/app" -depth -type d -empty -delete
test ! -e "$STAGE_PATH/storage/app"
ln -s ../../../shared/storage/app "$STAGE_PATH/storage/app"
test "$(readlink "$STAGE_PATH/storage/app")" = '../../../shared/storage/app'
test "$(readlink -f "$STAGE_PATH/storage/app")" = "$shared_storage"

# Le code et les dépendances peuvent être propres à une release ; jamais les médias.
cp "$previous_release/.env" "$STAGE_PATH/.env"
cp -al "$previous_release/vendor" "$STAGE_PATH/vendor"

cd "$STAGE_PATH"
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components

test -f public/build/manifest.json
test -L storage/app
test "$(readlink storage/app)" = '../../../shared/storage/app'

touch DEPLOYED_OK
ln -sfn "$STAGE_PATH" "$APP_PATH/current"

# Contrôles après bascule : l'application et le stockage persistent doivent répondre.
curl -fsS -o /dev/null https://cremona.maracujadigital.fr/dashboard/login
test -d "$shared_storage/public"
REMOTE

# The application keeps its data in shared/storage. Releases only contain
# immutable code, dependencies and generated caches: retain a short rollback
# window rather than silently exhausting the cPanel account quota.
ssh "$deploy_host" "APP_PATH='$application_path' bash -s" <<'REMOTE'
set -euo pipefail

retention_dir="$(mktemp -d)"
trap 'rm -rf -- "$retention_dir"' EXIT
active_release="$(readlink -f "$APP_PATH/current")"
printf '%s\n' "$active_release" > "$retention_dir/keep"
find "$APP_PATH/releases" -mindepth 2 -maxdepth 2 -name DEPLOYED_OK -printf '%T@ %h\n' \
    | sort -nr | awk '!seen[$2]++ { print $2 }' | head -n 5 >> "$retention_dir/keep"
sort -u "$retention_dir/keep" -o "$retention_dir/keep"
find "$APP_PATH/releases" -mindepth 1 -maxdepth 1 -type d -print > "$retention_dir/releases"

while IFS= read -r old_release; do
    if ! grep -Fxq "$old_release" "$retention_dir/keep"; then
        rm -rf -- "$old_release"
    fi
done < "$retention_dir/releases"
REMOTE

echo "Cremona publiée : $release"

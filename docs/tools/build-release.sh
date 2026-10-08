#!/usr/bin/env bash
# Builds the full package for new buyers: dist/qr-menu-<version>.zip
# Run on your own machine from the project root:  docs/tools/build-release.sh 1.0.0
# Needs: php, composer, node/npm, tar, zip.
set -euo pipefail

VERSION="${1:?usage: build-release.sh <version>}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="$ROOT/dist"
STAGE="$OUT/stage/qr-menu"

cd "$ROOT"

# The version in config/version.php must match, or updates would compare against the wrong number.
grep -q "'current' => '$VERSION'" config/version.php || { echo "config/version.php says a different version than $VERSION"; exit 1; }

echo "== Tests"
php artisan test --compact

echo "== Production dependencies and assets"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

echo "== Documentation"
php artisan docs:licenses
php artisan docs:build

echo "== Staging files"
rm -rf "$OUT/stage" && mkdir -p "$STAGE"
tar -c \
  --exclude=./.git --exclude=./.github --exclude=./.claude --exclude=./.env --exclude='./.env.*' --exclude=./node_modules --exclude=./tests --exclude=./dist \
  --exclude=./phpunit.xml --exclude=./AGENTS.md --exclude=./CLAUDE.md --exclude=./package-lock.json \
  --exclude='./storage/logs/*' --exclude='./storage/framework/cache/*' --exclude='./storage/framework/sessions/*' --exclude='./storage/framework/views/*' \
  --exclude=./storage/app/installed --exclude=./storage/app/addons.json --exclude=./storage/app/updates --exclude=./storage/app/addon-uploads --exclude=./storage/app/backups \
  --exclude='./database/*.sqlite' --exclude='./bootstrap/cache/*.php' --exclude=./public/storage --exclude=./public/hot . | tar -x -C "$STAGE"

echo "== Slimming vendor (git folders, tests, unused AWS services)"
find "$STAGE/vendor" -maxdepth 3 -name .git -type d -prune -exec rm -rf {} +
for d in "$STAGE"/vendor/*/*/; do for x in tests Tests test samples doc docs .github examples .changes build; do rm -rf "$d$x"; done; done
php -r '
$src = $argv[1]."/vendor/aws/aws-sdk-php/src"; if (!is_dir($src)) exit;
$keep = ["S3","Sts","Sso","SsoOidc","Ses","SesV2"]; $keepData = ["s3","sts","sso","sso-oidc","ses","sesv2"];
$rm = function ($d) use (&$rm) { foreach (scandir($d) as $f) { if ($f === "." || $f === "..") continue; is_dir("$d/$f") ? $rm("$d/$f") : unlink("$d/$f"); } rmdir($d); };
foreach (scandir("$src/data") as $f) if ($f[0] !== "." && is_dir("$src/data/$f") && !in_array($f, $keepData)) $rm("$src/data/$f");
foreach (scandir($src) as $f) if ($f[0] !== "." && is_dir("$src/$f") && !in_array($f, $keep) && file_exists("$src/$f/{$f}Client.php")) $rm("$src/$f");
' "$STAGE"
(cd "$STAGE" && composer dump-autoload --no-dev --optimize --no-scripts)
cp .env.example "$STAGE/.env.example"
mkdir -p "$STAGE/storage/logs" "$STAGE/storage/framework/"{cache,sessions,views} "$STAGE/bootstrap/cache"
touch "$STAGE/storage/logs/.gitignore" "$STAGE/bootstrap/cache/.gitignore"

echo "== Zip"
cd "$OUT/stage"
zip -qr "$OUT/qr-menu-$VERSION.zip" qr-menu
echo "Built $OUT/qr-menu-$VERSION.zip"

# Put development packages back for continued work.
cd "$ROOT" && composer install --no-interaction

#!/usr/bin/env bash
# Builds the package for buyers: dist/qr-menu-<version>-envato.zip
#
#   qr-menu-<version>/
#     README.txt           what is in the package and how to start
#     LICENSE.txt          licence note
#     CHANGELOG.txt
#     documentation/       HTML guides, Markdown sources, OpenAPI file
#     source/              the application: upload THIS to the server
#     examples/            a tiny add-on to copy from
#
# Run from the project root:   docs/tools/build-release.sh 1.0.0
# Needs: php, composer, node/npm, tar, zip.   Optional: RUN_TESTS=1 runs the whole test suite first (takes a while).
# Nothing here touches your development vendor folder: production dependencies are installed inside the staging folder.
set -euo pipefail

VERSION="${1:?usage: build-release.sh <version>}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="$ROOT/dist"
STAGE="$OUT/stage"
PKG="$STAGE/qr-menu-$VERSION"
SRC="$PKG/source"

cd "$ROOT"

# The version in config/version.php must match, or updates would compare against the wrong number.
grep -q "'current' => '$VERSION'" config/version.php || { echo "config/version.php says a different version than $VERSION"; exit 1; }

if [ "${RUN_TESTS:-0}" = "1" ]; then
  echo "== Tests"
  php artisan test --compact
fi

echo "== Assets and documentation"
[ -d node_modules ] || npm ci
npm run build
php artisan docs:licenses
php artisan docs:build

echo "== Staging the application"
rm -rf "$STAGE" && mkdir -p "$SRC"
tar -c \
  --exclude=./.git --exclude=./.github --exclude=./.claude --exclude=./.env --exclude='./.env.*' --exclude=./node_modules --exclude=./tests --exclude=./dist --exclude=./vendor \
  --exclude=./phpunit.xml --exclude=./AGENTS.md --exclude=./CLAUDE.md --exclude=./package-lock.json --exclude=./LICENSE.txt --exclude=./marketplace \
  --exclude='./storage/logs/*' --exclude='./storage/framework/cache/*' --exclude='./storage/framework/sessions/*' --exclude='./storage/framework/views/*' \
  --exclude=./storage/app/installed --exclude=./storage/app/addons.json --exclude=./storage/app/updates --exclude=./storage/app/addon-uploads --exclude=./storage/app/backups \
  --exclude='./database/*.sqlite' --exclude='./bootstrap/cache/*.php' --exclude=./public/storage --exclude=./public/hot . | tar -x -C "$SRC"
cp .env.example "$SRC/.env.example"

echo "== Production dependencies (inside the staging folder)"
(cd "$SRC" && composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --quiet)

echo "== Slimming vendor (git folders, tests, unused AWS services)"
find "$SRC/vendor" -maxdepth 3 -name .git -type d -prune -exec rm -rf {} +
for d in "$SRC"/vendor/*/*/; do for x in tests Tests test samples doc docs .github examples .changes build; do rm -rf "$d$x"; done; done
php -r '
$src = $argv[1]."/vendor/aws/aws-sdk-php/src"; if (!is_dir($src)) exit;
$keep = ["S3","Sts","Sso","SsoOidc","Ses","SesV2"]; $keepData = ["s3","sts","sso","sso-oidc","ses","sesv2"];
$rm = function ($d) use (&$rm) { foreach (scandir($d) as $f) { if ($f === "." || $f === "..") continue; is_dir("$d/$f") ? $rm("$d/$f") : unlink("$d/$f"); } rmdir($d); };
foreach (scandir("$src/data") as $f) if ($f[0] !== "." && is_dir("$src/data/$f") && !in_array($f, $keepData)) $rm("$src/data/$f");
foreach (scandir($src) as $f) if ($f[0] !== "." && is_dir("$src/$f") && !in_array($f, $keep) && file_exists("$src/$f/{$f}Client.php")) $rm("$src/$f");
' "$SRC"
(cd "$SRC" && composer dump-autoload --no-dev --optimize --no-scripts --quiet)
mkdir -p "$SRC/storage/logs" "$SRC/storage/framework/"{cache,sessions,views} "$SRC/bootstrap/cache"
touch "$SRC/storage/logs/.gitignore" "$SRC/bootstrap/cache/.gitignore"

echo "== Documentation and licence files"
mkdir -p "$PKG/documentation" "$PKG/examples"
cp -r docs/html/. "$PKG/documentation/"
cp docs/*.md docs/openapi.json "$PKG/documentation/"
cp -r docs/examples/. "$PKG/examples/"
cp LICENSE.txt "$PKG/LICENSE.txt"
cp CHANGELOG.md "$PKG/CHANGELOG.txt"
# The documentation folder inside the app is not needed on the server.
rm -rf "$SRC/docs/html" "$SRC/docs/tools" "$SRC/docs/examples"
cat > "$PKG/README.txt" <<EOF
QR Menu & Online Ordering SaaS - version $VERSION

START HERE
1. Open documentation/index.html (installation.html is the first guide to read).
2. Upload the CONTENTS of the "source" folder to your server (make "source/public" the web root, or follow
   the shared-hosting steps in the installation guide).
3. Open your site in a browser: the installer asks for the database and your Envato purchase code.

WHAT IS HERE
  source/          the application
  documentation/   guides (HTML and Markdown), REST API description (openapi.json), list of third-party licences
  examples/        a small add-on to copy from (see documentation/ADDONS.md)
  LICENSE.txt      licence note
  CHANGELOG.txt    what changed in each version

REQUIREMENTS
  PHP 8.2 or newer, MySQL 5.7+ / MariaDB 10.3+ (or SQLite), HTTPS, one cron job. Node.js is not needed on the server.

SUPPORT
  See documentation/CODECANYON.md (support policy) and documentation/TROUBLESHOOTING.md.
EOF

echo "== Zip"
cd "$STAGE"
rm -f "$OUT/qr-menu-$VERSION-envato.zip"
zip -qr "$OUT/qr-menu-$VERSION-envato.zip" "qr-menu-$VERSION"
echo "Built $OUT/qr-menu-$VERSION-envato.zip"

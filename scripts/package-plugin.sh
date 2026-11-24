#!/usr/bin/env bash
# Build a WordPress.org zip from the working tree using .distignore.
# Run after `npm run build` and `composer install --no-dev --optimize-autoloader`.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

if [[ ! -f build/admin.js ]]; then
	echo "build/admin.js is missing. Run npm ci && npm run build first." >&2
	exit 1
fi

if [[ ! -f vendor/autoload.php ]]; then
	echo "vendor/autoload.php is missing. Run composer install --no-dev --optimize-autoloader first." >&2
	exit 1
fi

stage="$(mktemp -d)"
dest="$stage/rankmyseo"
mkdir -p "$dest"

# .distignore uses gitignore-style comments and a leading slash.
# rsync accepts both. The negation for .distignore itself is unnecessary in the zip.
grep -v -e '^#' -e '^!' -e '^$' .distignore > "$stage/exclude.txt"

rsync -a --exclude-from="$stage/exclude.txt" ./ "$dest/"

mkdir -p dist
rm -f dist/rankmyseo.zip
rm -rf dist/rankmyseo
cp -a "$dest" dist/rankmyseo
(
	cd "$stage"
	zip -r "$root/dist/rankmyseo.zip" rankmyseo -x '*.DS_Store'
)

echo "Wrote $root/dist/rankmyseo.zip"
find dist/rankmyseo -maxdepth 2 -type d -o -maxdepth 1 -type f | sort

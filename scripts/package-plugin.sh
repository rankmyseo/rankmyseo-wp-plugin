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

required=(
	rankmyseo/rankmyseo.php
	rankmyseo/readme.txt
	rankmyseo/uninstall.php
	rankmyseo/LICENSE
	rankmyseo/languages/rankmyseo.pot
	rankmyseo/includes/template-functions.php
	rankmyseo/assets/css/breadcrumbs.css
	rankmyseo/assets/css/faq.css
	rankmyseo/build/admin.js
	rankmyseo/build/admin.css
	rankmyseo/build/admin.asset.php
	rankmyseo/build/editor.js
	rankmyseo/build/editor.asset.php
	rankmyseo/build/classic.js
	rankmyseo/build/classic.asset.php
	rankmyseo/build/quick-edit.js
	rankmyseo/build/quick-edit.asset.php
	rankmyseo/build/elementor.js
	rankmyseo/build/elementor.asset.php
	rankmyseo/build/divi.js
	rankmyseo/build/divi.asset.php
	rankmyseo/vendor/autoload.php
)
for file in "${required[@]}"; do
	if ! unzip -l "$root/dist/rankmyseo.zip" "$file" | grep -q "$file"; then
		echo "Release zip is missing $file" >&2
		exit 1
	fi
done

forbidden=(
	rankmyseo/node_modules/
	rankmyseo/src/
	rankmyseo/tests/
	rankmyseo/scripts/
	rankmyseo/package.json
	rankmyseo/package-lock.json
	rankmyseo/composer.json
	rankmyseo/composer.lock
	rankmyseo/README.md
	rankmyseo/phpstan-bootstrap.php
	rankmyseo/phpstan-elementor-stub.php
	rankmyseo/phpstan.neon.dist
	rankmyseo/phpunit.xml.dist
)
for file in "${forbidden[@]}"; do
	if unzip -l "$root/dist/rankmyseo.zip" | grep -q "$file"; then
		echo "Release zip should not contain $file" >&2
		exit 1
	fi
done

find dist/rankmyseo -maxdepth 2 -type d -o -maxdepth 1 -type f | sort
unzip -l "$root/dist/rankmyseo.zip" | tail -1

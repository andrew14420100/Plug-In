#!/usr/bin/env bash
# Crea lo ZIP installabile del plugin: dist/bracelli-club-<versione>.zip (+ .sha256).
# Prerequisito: asset compilati (npm run build). Lo ZIP contiene una sola directory "bracelli-club/".
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
SRC="$ROOT/plugin/bracelli-club"
BUILD="$ROOT/build"
DIST="$ROOT/dist"

VERSION=$(grep -m1 -E '^\s*\*\s*Version:' "$SRC/bracelli-club.php" | sed -E 's/.*Version:\s*//; s/\s+$//')
CONST_VERSION=$(grep -m1 "define( 'BRACELLI_CLUB_VERSION'" "$SRC/bracelli-club.php" | sed -E "s/.*'([0-9.]+)'.*/\1/")
README_VERSION=$(grep -m1 -E '^Stable tag:' "$SRC/readme.txt" | sed -E 's/Stable tag:\s*//; s/\s+$//')

if [ "$VERSION" != "$CONST_VERSION" ] || [ "$VERSION" != "$README_VERSION" ]; then
	echo "Versioni incoerenti: header=$VERSION costante=$CONST_VERSION readme=$README_VERSION" >&2
	exit 1
fi

for bundle in dashboard audit; do
	if [ ! -f "$SRC/assets/dist/$bundle/index.js" ] || [ ! -f "$SRC/assets/dist/$bundle/index.asset.php" ]; then
		echo "Bundle mancante: assets/dist/$bundle (esegui npm run build)" >&2
		exit 1
	fi
done

rm -rf "$BUILD" && mkdir -p "$BUILD" "$DIST"
cp -R "$SRC" "$BUILD/bracelli-club"

# Nessun file di sviluppo o potenzialmente sensibile nel pacchetto.
FORBIDDEN=$(find "$BUILD/bracelli-club" \( -name '.*' -o -name '*.map' -o -name '*.sql' -o -name '*.log' -o -name '*.env' -o -name 'composer.*' -o -name 'package*.json' -o -name 'node_modules' -o -name 'tests' \) -print)
if [ -n "$FORBIDDEN" ]; then
	echo "File non ammessi nel pacchetto:" >&2
	echo "$FORBIDDEN" >&2
	exit 1
fi

ZIP="$DIST/bracelli-club-$VERSION.zip"
rm -f "$ZIP" "$ZIP.sha256"
(cd "$BUILD" && find bracelli-club -type f | LC_ALL=C sort | TZ=UTC zip -q -X -D "$ZIP" -@)

# Verifica struttura: un'unica directory radice.
ROOTS=$(unzip -Z1 "$ZIP" | cut -d/ -f1 | sort -u)
if [ "$ROOTS" != "bracelli-club" ]; then
	echo "Struttura ZIP non valida: $ROOTS" >&2
	exit 1
fi
unzip -Z1 "$ZIP" | grep -qx 'bracelli-club/bracelli-club.php'

(cd "$DIST" && sha256sum "$(basename "$ZIP")" > "$(basename "$ZIP").sha256")
echo "Creato $ZIP ($(unzip -Z1 "$ZIP" | wc -l) file)"
cat "$ZIP.sha256"

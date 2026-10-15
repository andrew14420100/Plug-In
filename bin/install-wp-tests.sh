#!/usr/bin/env bash
# Installa WordPress core e la test suite ufficiale (wordpress-develop/tests/phpunit)
# per i test di integrazione. Uso:
#   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]
# wp-version: "latest", "nightly" oppure un numero di versione (es. 7.1.3, 6.9).
# Le directory si configurano con WP_TESTS_DIR e WP_CORE_DIR.
set -euo pipefail

if [ $# -lt 3 ]; then
	echo "Uso: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]" >&2
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

download() {
	curl -fsSL --retry 3 "$1" -o "$2"
}

resolve_version() {
	if [ "$WP_VERSION" = "latest" ]; then
		download https://api.wordpress.org/core/version-check/1.7/ "$TMPDIR/wp-latest.json"
		WP_VERSION=$(grep -o '"version":"[^"]*' "$TMPDIR/wp-latest.json" | head -1 | sed 's/"version":"//')
	fi
	if [ "$WP_VERSION" = "nightly" ] || [ "$WP_VERSION" = "trunk" ]; then
		WP_TESTS_TAG="trunk"
	elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
		# Ramo x.y: usa l'ultima patch disponibile del ramo.
		download https://api.wordpress.org/core/version-check/1.7/ "$TMPDIR/wp-latest.json"
		local full
		full=$(grep -o "\"version\":\"${WP_VERSION}[^\"]*" "$TMPDIR/wp-latest.json" | head -1 | sed 's/"version":"//')
		WP_VERSION=${full:-$WP_VERSION}
		WP_TESTS_TAG="tags/$WP_VERSION"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
	echo "WordPress: $WP_VERSION (test suite: $WP_TESTS_TAG)"
}

install_wp() {
	if [ -f "$WP_CORE_DIR/wp-load.php" ]; then
		return
	fi
	mkdir -p "$WP_CORE_DIR"
	if [ "$WP_TESTS_TAG" = "trunk" ]; then
		download https://wordpress.org/nightly-builds/wordpress-latest.zip "$TMPDIR/wordpress.zip"
	else
		download "https://downloads.wordpress.org/release/wordpress-$WP_VERSION.zip" "$TMPDIR/wordpress.zip"
	fi
	unzip -q -o "$TMPDIR/wordpress.zip" -d "$TMPDIR/wp-unzip"
	cp -R "$TMPDIR/wp-unzip/wordpress/." "$WP_CORE_DIR/"
	rm -rf "$TMPDIR/wp-unzip" "$TMPDIR/wordpress.zip"
}

fetch_tests_svn() {
	svn export --quiet --force "https://develop.svn.wordpress.org/$WP_TESTS_TAG/tests/phpunit/includes/" "$WP_TESTS_DIR/includes" &&
	svn export --quiet --force "https://develop.svn.wordpress.org/$WP_TESTS_TAG/tests/phpunit/data/" "$WP_TESTS_DIR/data" &&
	svn export --quiet --force "https://develop.svn.wordpress.org/$WP_TESTS_TAG/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
}

fetch_tests_git() {
	local ref="trunk" tmp="$TMPDIR/wordpress-develop"
	if [ "$WP_TESTS_TAG" != "trunk" ]; then
		ref="${WP_TESTS_TAG#tags/}"
	fi
	rm -rf "$tmp"
	git clone --quiet --depth 1 --branch "$ref" --filter=blob:none --sparse https://github.com/WordPress/wordpress-develop.git "$tmp"
	git -C "$tmp" sparse-checkout set tests/phpunit/includes tests/phpunit/data
	mkdir -p "$WP_TESTS_DIR"
	cp -R "$tmp/tests/phpunit/includes" "$tmp/tests/phpunit/data" "$WP_TESTS_DIR/"
	cp "$tmp/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
	rm -rf "$tmp"
}

install_test_suite() {
	if [ ! -d "$WP_TESTS_DIR/includes" ]; then
		mkdir -p "$WP_TESTS_DIR"
		if command -v svn >/dev/null 2>&1 && fetch_tests_svn; then
			:
		else
			fetch_tests_git
		fi
	fi
	local cfg="$WP_TESTS_DIR/wp-tests-config.php"
	if ! grep -q "youremptytestdbnamehere" "$cfg"; then
		return
	fi
	sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$cfg"
	sed -i "s:__DIR__ . '/src/':'$WP_CORE_DIR/':" "$cfg"
	sed -i "s/youremptytestdbnamehere/$DB_NAME/" "$cfg"
	sed -i "s/yourusernamehere/$DB_USER/" "$cfg"
	sed -i "s/yourpasswordhere/$DB_PASS/" "$cfg"
	sed -i "s|localhost|${DB_HOST}|" "$cfg"
}

create_db() {
	if [ "$SKIP_DB_CREATE" = "true" ]; then
		return
	fi
	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" 2>/dev/null || true
}

resolve_version
install_wp
install_test_suite
create_db
echo "Pronto: WP_CORE_DIR=$WP_CORE_DIR WP_TESTS_DIR=$WP_TESTS_DIR"

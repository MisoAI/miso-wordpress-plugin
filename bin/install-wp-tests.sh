#!/usr/bin/env bash
#
# Installs the WordPress PHPUnit test library configured for SQLite via the
# official "SQLite Database Integration" drop-in. No MySQL service required.
#
# Usage: bash bin/install-wp-tests.sh [wp-version]
#   wp-version  WordPress version to test against (default: latest)
#
# Environment variables:
#   WP_TESTS_DIR  Where the test library is installed (default: <project>/tests-wp/lib)
#   WP_CORE_DIR   Where WordPress is installed (default: <project>/tests-wp/core)

set -e

WP_VERSION=${1-latest}

PROJECT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)

WP_TESTS_DIR=${WP_TESTS_DIR-$PROJECT_DIR/tests-wp/lib}
WP_CORE_DIR=${WP_CORE_DIR-$PROJECT_DIR/tests-wp/core}
SQLITE_PLUGIN_DIR=${SQLITE_PLUGIN_DIR-$PROJECT_DIR/tests-wp/sqlite-plugin}

download() {
    if [ "$(which curl)" ]; then
        curl -fsSL "$1" > "$2"
    elif [ "$(which wget)" ]; then
        wget -nv -O "$2" "$1"
    else
        echo "Need curl or wget installed." >&2
        exit 1
    fi
}

resolve_wp_version() {
    if [ "$WP_VERSION" = "latest" ]; then
        ARCHIVE_NAME="latest"
        WP_TESTS_TAG="trunk"
    else
        ARCHIVE_NAME="wordpress-$WP_VERSION"
        WP_TESTS_TAG="tags/$WP_VERSION"
    fi
}

install_wp() {
    if [ -d "$WP_CORE_DIR" ]; then
        echo "WordPress already present at $WP_CORE_DIR"
        return
    fi
    mkdir -p "$WP_CORE_DIR"
    download "https://wordpress.org/${ARCHIVE_NAME}.tar.gz" /tmp/wordpress.tar.gz
    tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
    rm /tmp/wordpress.tar.gz
}

install_test_suite() {
    if [ ! -d "$WP_TESTS_DIR/includes" ]; then
        mkdir -p "$WP_TESTS_DIR"
        # Prefer SVN if available; otherwise fall back to GitHub raw downloads.
        if [ "$(which svn)" ]; then
            svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
            svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "$WP_TESTS_DIR/data"
        else
            echo "svn not found — install subversion or use a wp-version that maps to a release tag." >&2
            exit 1
        fi
    fi

    if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
        download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
        # Point ABSPATH-equivalent at the installed core.
        WP_CORE_PATH_ESCAPED=$(echo "$WP_CORE_DIR/" | sed 's:/:\\/:g')
        sed -i.bak "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_PATH_ESCAPED':" "$WP_TESTS_DIR/wp-tests-config.php"
        # DB credentials are ignored once the SQLite drop-in is installed, but
        # the constants still need to be defined.
        sed -i.bak "s/youremptytestdbnamehere/wordpress_test/" "$WP_TESTS_DIR/wp-tests-config.php"
        sed -i.bak "s/yourusernamehere/root/" "$WP_TESTS_DIR/wp-tests-config.php"
        sed -i.bak "s/yourpasswordhere//" "$WP_TESTS_DIR/wp-tests-config.php"
        rm "$WP_TESTS_DIR/wp-tests-config.php.bak"
    fi
}

install_sqlite_dropin() {
    # SQLite Database Integration is the official WordPress feature plugin
    # that shims $wpdb to use SQLite. We copy its db.copy into wp-content/db.php
    # and replace the placeholders.
    if [ ! -d "$SQLITE_PLUGIN_DIR" ]; then
        download "https://downloads.wordpress.org/plugin/sqlite-database-integration.zip" /tmp/sqlite-database-integration.zip
        unzip -q /tmp/sqlite-database-integration.zip -d /tmp
        mv /tmp/sqlite-database-integration "$SQLITE_PLUGIN_DIR" 2>/dev/null || true
        rm /tmp/sqlite-database-integration.zip
    fi

    cp "$SQLITE_PLUGIN_DIR/db.copy" "$WP_CORE_DIR/wp-content/db.php"
    SQLITE_PATH_ESCAPED=$(echo "$SQLITE_PLUGIN_DIR" | sed 's:/:\\/:g')
    sed -i.bak "s/{SQLITE_IMPLEMENTATION_FOLDER_PATH}/$SQLITE_PATH_ESCAPED/" "$WP_CORE_DIR/wp-content/db.php"
    sed -i.bak "s/{SQLITE_PLUGIN}/sqlite-database-integration\/load.php/" "$WP_CORE_DIR/wp-content/db.php"
    rm "$WP_CORE_DIR/wp-content/db.php.bak"

    # Tell the drop-in where to put the SQLite file. Constants are appended
    # only once.
    if ! grep -q "DB_ENGINE" "$WP_TESTS_DIR/wp-tests-config.php"; then
        DB_DIR_ESCAPED=$(echo "$WP_TESTS_DIR/database" | sed "s:':\\\\':g")
        cat >> "$WP_TESTS_DIR/wp-tests-config.php" <<EOF

// --- SQLite Database Integration ---
define( 'DB_ENGINE', 'sqlite' );
define( 'DB_DIR', '$WP_TESTS_DIR/database' );
define( 'DB_FILE', 'wordpress-test.sqlite' );
EOF
    fi
    mkdir -p "$WP_TESTS_DIR/database"
    # Start each install fresh so DB schema matches the installed WP version.
    rm -f "$WP_TESTS_DIR/database/wordpress-test.sqlite"
}

resolve_wp_version
install_wp
install_test_suite
install_sqlite_dropin

echo ""
echo "WordPress test suite installed."
echo "  WP core:    $WP_CORE_DIR"
echo "  Test lib:   $WP_TESTS_DIR"
echo "  SQLite DB:  $WP_TESTS_DIR/database/wordpress-test.sqlite"
echo ""
echo "Run tests with: composer test"

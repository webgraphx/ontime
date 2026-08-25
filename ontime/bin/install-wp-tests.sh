#!/usr/bin/env bash
#
# Install WordPress test suite for OnTime plugin.
# @since 1.0.0

set -euo pipefail

WP_VERSION="${1:-latest}"
TESTS_DIR="${2:-/tmp/wordpress-tests-lib}"
DB_NAME="${3:-wordpress_test}"
DB_USER="${4:-root}"
DB_PASS="${5:-}"
DB_HOST="${6:-localhost}"

if [ ! -d "${TESTS_DIR}" ]; then
	mkdir -p "${TESTS_DIR}"
	svn co --quiet "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/" "${TESTS_DIR}"
fi

cat > "${TESTS_DIR}/wp-tests-config.php" <<EOF
<?php
define( 'DB_NAME',     '${DB_NAME}' );
define( 'DB_USER',     '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASS}' );
define( 'DB_HOST',     '${DB_HOST}' );
define( 'DB_CHARSET',  'utf8' );
define( 'DB_COLLATE',  '' );
\$table_prefix = 'wptests_';
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL',  'admin@example.org' );
define( 'WP_TESTS_TITLE',  'OnTime Test Blog' );
EOF

echo "WordPress test suite installed at ${TESTS_DIR}"
echo "Run: phpunit"

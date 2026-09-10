#!/usr/bin/env bash
set -euo pipefail

cat > /var/www/omeka-s/config/database.ini <<EOF_DB
user     = "${OMEKA_DB_USER:-omeka}"
password = "${OMEKA_DB_PASSWORD:-omeka}"
dbname   = "${OMEKA_DB_NAME:-omeka}"
host     = "${OMEKA_DB_HOST:-db}"
port     = "${OMEKA_DB_PORT:-3306}"
EOF_DB

exec php -d variables_order=EGPCS -S 0.0.0.0:8080 -t /var/www/omeka-s /var/www/omeka-s/index.php

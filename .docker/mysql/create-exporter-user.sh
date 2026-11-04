#!/usr/bin/env bash
#
# Creates the least-privileged account that mysqld-exporter logs in with.
#
# MySQL only runs /docker-entrypoint-initdb.d scripts on an empty data volume.
# On an existing volume, run it once by hand (it is safe to re-run and also
# resets the password to the current MYSQL_EXPORTER_PASSWORD):
#
#   ./vendor/bin/sail exec mysql bash /docker-entrypoint-initdb.d/20-create-exporter-user.sh

: "${MYSQL_EXPORTER_PASSWORD:?MYSQL_EXPORTER_PASSWORD must be set}"

# Escape backslashes and single quotes for use inside a SQL string literal.
exporter_password=${MYSQL_EXPORTER_PASSWORD//\\/\\\\}
exporter_password=${exporter_password//\'/\'\'}

mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE USER IF NOT EXISTS 'exporter'@'%' IDENTIFIED BY '${exporter_password}';
    ALTER USER 'exporter'@'%' IDENTIFIED BY '${exporter_password}' WITH MAX_USER_CONNECTIONS 3;
    GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'exporter'@'%';
EOSQL

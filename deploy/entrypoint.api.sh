#!/bin/sh
# Entrada del contenedor de la API.
#
# NO se cambian permisos ni se corrigen datos al arrancar: un contenedor que
# "repara" cosas en cada inicio esconde problemas y puede tapar una
# migracion que nunca se ejecuto.

set -eu

# Espera a que PostgreSQL acepte conexiones, con limite de tiempo.
intentos=0
hasta=$(date +%s)

while ! php -r 'exit(@pg_connect(getenv("DB_HOST")."=".getenv("DB_PORT")." dbname=".getenv("DB_DATABASE")." user=".getenv("DB_USERNAME")." password=".getenv("DB_PASSWORD")) ? 0 : 1);'
do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge 30 ]; then
        echo "La base de datos no respondio a tiempo. Se aborta el arranque." >&2
        exit 1
    fi
    sleep 2
done

echo "Panel de Operacion: arrancando en modo solo lectura."

exec "$@"
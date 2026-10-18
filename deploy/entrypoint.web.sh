#!/bin/sh
# Entrada del contenedor del frontend.
set -eu

echo "Panel de Operacion: frontend Nuxt en modo SSR, solo lectura."

exec "$@"
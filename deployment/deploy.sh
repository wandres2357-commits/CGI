#!/bin/sh
set -eu
ACCOUNT="${1:?Uso: sh deployment/deploy.sh CUENTA}"
APP="$HOME/inventario-ti"
cd "$APP"
git pull --ff-only origin main
mkdir -p storage/logs storage/private storage/sessions
chmod 700 storage/private
chmod 700 .env
find storage -type d -exec chmod 700 {} echo "Despliegue actualizado para $ACCOUNT"

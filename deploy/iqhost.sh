#!/usr/bin/env bash
# Wdrozenie na iqhost (DirectAdmin, alt-php). Uzycie: deploy/iqhost.sh
# Caly projekt laduje w katalogu strony; glowny .htaccess kieruje ruch do public/.
# PHP na tym serwerze ma open_basedir ograniczone do ~/domains/abedo.pl/.
# Na serwerze zostaje (nie jest nadpisywane): var/ i .env.local (APP_SECRET, DATABASE_URL, klucze Google).
set -euo pipefail

SSH_KEY="${SSH_KEY:-$HOME/.ssh/iqhost_ed25519}"
REMOTE="${REMOTE:-host32414@host32414.iqhs.pl}"
APP_DIR="${APP_DIR:-/home/host32414/domains/abedo.pl/public_html/sportsdoc}"
PHP_BIN="${PHP_BIN:-/opt/alt/php84/usr/bin/php}"
SRC="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$(mktemp -d)"
SSH="ssh -i $SSH_KEY -o IdentitiesOnly=yes -o BatchMode=yes"
trap 'rm -rf "$BUILD"' EXIT

# Paczka bez zaleznosci dev, testow, gita i lokalnych sekretow.
rsync -a --exclude vendor --exclude var --exclude '.env.local*' --exclude .idea --exclude .git \
    --exclude tests --exclude phpunit.dist.xml --exclude .phpunit.cache --exclude deploy "$SRC/" "$BUILD/"
(cd "$BUILD" && composer install --no-dev --optimize-autoloader --no-scripts --no-interaction -q)

$SSH "$REMOTE" "mkdir -p '$APP_DIR'"
rsync -az -e "$SSH" --exclude var --exclude '.env.local*' "$BUILD/" "$REMOTE:$APP_DIR/"

$SSH "$REMOTE" "
set -e
cd '$APP_DIR'
mkdir -p var; chmod 775 var
[ -f .env.local ] || { printf 'APP_ENV=prod\nAPP_DEBUG=0\nAPP_SECRET=%s\n' \"\$(head -c16 /dev/urandom | od -An -tx1 | tr -d ' \n')\" > .env.local; chmod 640 .env.local; }
rm -rf var/cache/prod && $PHP_BIN bin/console cache:warmup --env=prod
"
echo "Gotowe: $APP_DIR"

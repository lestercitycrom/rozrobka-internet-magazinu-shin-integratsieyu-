#!/usr/bin/env bash
# Run once as root on 95.217.153.189 after copying deploy/ to /root/shop-ndi-deploy.
set -Eeuo pipefail
umask 0027

readonly APP_USER='easytire-deploy'
readonly APP_GROUP='www-data'
readonly DOMAIN='staging-easytire.bethub.fun'
readonly APP_ROOT="/var/www/$DOMAIN"
readonly REPO='/srv/git/shop-ndi.git'
readonly DEPLOY_SOURCE='/root/shop-ndi-deploy'

[[ -d "$DEPLOY_SOURCE" ]] || { echo "Missing $DEPLOY_SOURCE" >&2; exit 1; }
[[ -f /root/.ssh/authorized_keys ]] || { echo 'Root authorized_keys is missing.' >&2; exit 1; }
id "$APP_USER" &>/dev/null || useradd --create-home --shell /bin/bash "$APP_USER"
usermod -aG "$APP_GROUP" "$APP_USER"
install -d -m 700 -o "$APP_USER" -g "$APP_USER" "/home/$APP_USER/.ssh"
install -m 600 -o "$APP_USER" -g "$APP_USER" /root/.ssh/authorized_keys "/home/$APP_USER/.ssh/authorized_keys"

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y php8.3-sqlite3 mysql-server curl ca-certificates unzip certbot python3-certbot-nginx
php -r 'exit(function_exists("proc_open") ? 0 : 1);' || {
  echo 'PHP CLI must allow proc_open for Composer and Laravel test processes. FPM may keep its stricter policy.' >&2
  exit 1
}

expected_checksum="$(curl -fsSL https://composer.github.io/installer.sig)"
curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
actual_checksum="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
[[ "$expected_checksum" == "$actual_checksum" ]] || { rm -f /tmp/composer-setup.php; echo 'Invalid Composer installer signature.' >&2; exit 1; }
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
rm -f /tmp/composer-setup.php

install -d -m 2750 -o "$APP_USER" -g "$APP_GROUP" "$APP_ROOT" "$APP_ROOT/releases" "$APP_ROOT/shared"
install -d -m 2750 -o root -g "$APP_GROUP" /srv/git
install -d -m 2770 -o "$APP_USER" -g "$APP_GROUP" \
  "$APP_ROOT/shared/storage" \
  "$APP_ROOT/shared/storage/app/public" \
  "$APP_ROOT/shared/storage/framework/cache/data" \
  "$APP_ROOT/shared/storage/framework/sessions" \
  "$APP_ROOT/shared/storage/framework/testing" \
  "$APP_ROOT/shared/storage/framework/views" \
  "$APP_ROOT/shared/storage/logs"
[[ -e "$REPO" ]] || git init --bare --shared=group "$REPO"
chown -R "$APP_USER:$APP_GROUP" "$REPO"
install -m 755 -o "$APP_USER" -g "$APP_GROUP" "$DEPLOY_SOURCE/post-receive" "$REPO/hooks/post-receive"

if [[ ! -f "$APP_ROOT/shared/.env" ]]; then
db_password="$(openssl rand -base64 36 | tr -d '\n' | tr '/+' 'ab' | cut -c1-32)"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS shop_ndi_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'shop_ndi_staging'@'localhost' IDENTIFIED BY '${db_password}';
ALTER USER 'shop_ndi_staging'@'localhost' IDENTIFIED BY '${db_password}';
GRANT ALL PRIVILEGES ON shop_ndi_staging.* TO 'shop_ndi_staging'@'localhost';
FLUSH PRIVILEGES;
SQL
app_key="base64:$(openssl rand -base64 32 | tr -d '\n')"
install -m 640 -o "$APP_USER" -g "$APP_GROUP" /dev/null "$APP_ROOT/shared/.env"
cat > "$APP_ROOT/shared/.env" <<ENV
APP_NAME="EasyTire Staging"
APP_ENV=staging
APP_KEY=$app_key
APP_DEBUG=false
APP_URL=https://$DOMAIN
APP_LOCALE=nb
APP_FALLBACK_LOCALE=nb
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shop_ndi_staging
DB_USERNAME=shop_ndi_staging
DB_PASSWORD=$db_password
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
ENV
chown "$APP_USER:$APP_GROUP" "$APP_ROOT/shared/.env"
chmod 640 "$APP_ROOT/shared/.env"
fi

install -m 644 "$DEPLOY_SOURCE/staging-easytire.nginx.conf" "/etc/nginx/sites-available/$DOMAIN"
enabled_tmp="/etc/nginx/sites-enabled/.${DOMAIN}.$$"
ln -s "/etc/nginx/sites-available/$DOMAIN" "$enabled_tmp"
mv -Tf "$enabled_tmp" "/etc/nginx/sites-enabled/$DOMAIN"
nginx -t
systemctl reload nginx
certbot --nginx --non-interactive --agree-tos --register-unsafely-without-email -d "$DOMAIN" --redirect

echo "Bootstrap complete. Git remote: $APP_USER@95.217.153.189:$REPO"

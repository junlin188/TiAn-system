#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/tian-system}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
DOMAIN="${DOMAIN:-_}"
DEPLOY_PUBLIC_KEY="${DEPLOY_PUBLIC_KEY:-}"

if [ "$(id -u)" -ne 0 ]; then
  echo "Please run this script as root." >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y nginx php-fpm php-cli php-sqlite3 php-mbstring php-gd rsync git acl

if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
  useradd --create-home --shell /bin/bash "$DEPLOY_USER"
fi
usermod -aG www-data "$DEPLOY_USER"

mkdir -p "$APP_DIR/storage/uploads" "$APP_DIR/storage/captcha"
chown -R "$DEPLOY_USER:www-data" "$APP_DIR"
chmod -R 2775 "$APP_DIR/storage"
setfacl -R -m "u:${DEPLOY_USER}:rwx,u:www-data:rwx" "$APP_DIR/storage"
setfacl -R -d -m "u:${DEPLOY_USER}:rwx,u:www-data:rwx" "$APP_DIR/storage"

if [ -n "$DEPLOY_PUBLIC_KEY" ]; then
  install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"
  printf '%s\n' "$DEPLOY_PUBLIC_KEY" > "/home/$DEPLOY_USER/.ssh/authorized_keys"
  chown "$DEPLOY_USER:$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh/authorized_keys"
  chmod 600 "/home/$DEPLOY_USER/.ssh/authorized_keys"
fi

PHP_FPM_SOCK="$(find /run/php -maxdepth 1 -name 'php*-fpm.sock' | sort -V | tail -n 1)"
if [ -z "$PHP_FPM_SOCK" ]; then
  echo "Could not find PHP-FPM socket under /run/php." >&2
  exit 1
fi

cat > /etc/nginx/sites-available/tian-system <<NGINX
server {
    listen 80;
    server_name ${DOMAIN};

    root ${APP_DIR}/public;
    index index.php index.html;

    client_max_body_size 520m;

    access_log /var/log/nginx/tian-system.access.log;
    error_log /var/log/nginx/tian-system.error.log;

    location / {
        try_files \$uri /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_FPM_SOCK};
    }

    location ~ /\.(?!well-known) {
        deny all;
    }

    location ~ ^/(storage|deploy|\.github|\.git) {
        deny all;
    }
}
NGINX

ln -sfn /etc/nginx/sites-available/tian-system /etc/nginx/sites-enabled/tian-system
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl enable --now nginx
systemctl reload nginx

echo "Server is ready."
echo "Application directory: $APP_DIR"
echo "Deploy user: $DEPLOY_USER"
echo "PHP-FPM socket: $PHP_FPM_SOCK"

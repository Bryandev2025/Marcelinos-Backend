#!/usr/bin/env bash
set -Eeuo pipefail

# =========================================================
# Laravel Ubuntu Server Provision + Deploy Prerequisites
# Includes:
# - Nginx
# - PHP-FPM
# - MySQL
# - Composer
# - Node.js LTS
# - UFW
# - Certbot SSL
# - Permissions
# - GitHub Actions SSH deploy key generation
# =========================================================

if [[ $EUID -ne 0 ]]; then
    echo "Run this script as root"
    exit 1
fi

if [[ -f ".env" ]]; then
    set -a
    source .env
    set +a
else
    echo "ERROR: .env file not found"
    exit 1
fi

required_vars=(
    DEPLOY_USER
    APP_DIR
    DOMAIN_NAME
    CERTBOT_EMAIL
    APP_NAME
    APP_ENV
    APP_URL
    DB_CONNECTION
    DB_DATABASE
    DB_USERNAME
    DB_PASSWORD
)

for v in "${required_vars[@]}"; do
    if [[ -z "${!v:-}" ]]; then
        echo "ERROR: Missing environment variable: $v"
        exit 1
    fi
done

PHP_VERSION="8.3"
WEB_GROUP="www-data"
BASE_DIR="/var/www"
APP_PATH="$BASE_DIR/$APP_DIR"
DEPLOY_HOME="/home/$DEPLOY_USER"
DEPLOY_SSH_DIR="$DEPLOY_HOME/.ssh"
DEPLOY_KEY_PATH="$DEPLOY_SSH_DIR/github_actions_deploy"
PHP_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"

echo "========================================================="
echo "Starting server setup for $DOMAIN_NAME"
echo "App path: $APP_PATH"
echo "Deploy user: $DEPLOY_USER"
echo "========================================================="

export DEBIAN_FRONTEND=noninteractive

echo "Updating system packages..."
apt-get update -y
apt-get upgrade -y

echo "Installing base packages..."
apt-get install -y \
    nginx \
    git \
    curl \
    unzip \
    ufw \
    certbot \
    python3-certbot-nginx \
    mysql-server \
    ca-certificates \
    gnupg \
    lsb-release \
    software-properties-common \
    openssh-client \
    php${PHP_VERSION} \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-intl

echo "Installing Composer if missing..."
if ! command -v composer >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

sudo apt install -y php8.3-gd
php -m | grep gd
sudo systemctl restart php8.3-fpm

echo "Installing latest Node.js LTS..."
apt-get remove -y nodejs || true
rm -f /etc/apt/sources.list.d/nodesource.list || true
curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
apt-get install -y nodejs build-essential

echo "Verifying runtime versions..."
php -v
composer --version
node -v
npm -v

echo "Enabling and starting services..."
systemctl enable nginx
systemctl enable php${PHP_VERSION}-fpm
systemctl enable mysql

systemctl start nginx
systemctl start php${PHP_VERSION}-fpm
systemctl start mysql

echo "Ensuring deploy user exists..."
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" "$DEPLOY_USER"
fi

echo "Adding deploy user to web group..."
usermod -aG "$WEB_GROUP" "$DEPLOY_USER"

echo "Preparing SSH directory for deploy user..."
mkdir -p "$DEPLOY_SSH_DIR"
chmod 700 "$DEPLOY_SSH_DIR"
chown -R "$DEPLOY_USER:$DEPLOY_USER" "$DEPLOY_SSH_DIR"

AUTHORIZED_KEYS_FILE="$DEPLOY_SSH_DIR/authorized_keys"
touch "$AUTHORIZED_KEYS_FILE"
chmod 600 "$AUTHORIZED_KEYS_FILE"
chown "$DEPLOY_USER:$DEPLOY_USER" "$AUTHORIZED_KEYS_FILE"

echo "Generating GitHub Actions deploy SSH key if missing..."
if [[ ! -f "$DEPLOY_KEY_PATH" ]]; then
    sudo -u "$DEPLOY_USER" ssh-keygen \
        -t ed25519 \
        -C "github-actions@$DOMAIN_NAME" \
        -f "$DEPLOY_KEY_PATH" \
        -N ""
fi

echo "Authorizing public key for SSH login..."
PUB_KEY_CONTENT="$(cat "${DEPLOY_KEY_PATH}.pub")"
if ! grep -qxF "$PUB_KEY_CONTENT" "$AUTHORIZED_KEYS_FILE"; then
    echo "$PUB_KEY_CONTENT" >> "$AUTHORIZED_KEYS_FILE"
fi

chmod 600 "$AUTHORIZED_KEYS_FILE"
chown "$DEPLOY_USER:$DEPLOY_USER" "$AUTHORIZED_KEYS_FILE"

echo "Creating application base directory..."
mkdir -p "$BASE_DIR"

if [[ ! -d "$APP_PATH" ]]; then
    echo "Creating app directory at $APP_PATH"
    mkdir -p "$APP_PATH"
fi

echo "Setting base ownership..."
chown -R "$DEPLOY_USER:$WEB_GROUP" "$APP_PATH"
find "$APP_PATH" -type d -exec chmod 775 {} \; || true
find "$APP_PATH" -type f -exec chmod 664 {} \; || true

cd "$APP_PATH"

if [[ ! -f .env && -f .env.example ]]; then
    cp .env.example .env
    chown "$DEPLOY_USER:$WEB_GROUP" .env
    chmod 640 .env
fi

set_env() {
    local key="$1"
    local value="$2"

    if grep -q "^${key}=" .env 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=\"${value}\"|" .env
    else
        echo "${key}=\"${value}\"" >> .env
    fi
}

if [[ -f .env ]]; then
    echo "Updating Laravel .env values..."
    set_env APP_NAME "$APP_NAME"
    set_env APP_ENV "$APP_ENV"
    set_env APP_DEBUG "false"
    set_env APP_URL "$APP_URL"
fi

if [[ "$DB_CONNECTION" == "mysql" ]]; then
    echo "Configuring MySQL database and user..."
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\`;
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';
FLUSH PRIVILEGES;
SQL

    if [[ -f .env ]]; then
        set_env DB_CONNECTION "mysql"
        set_env DB_HOST "127.0.0.1"
        set_env DB_PORT "3306"
        set_env DB_DATABASE "$DB_DATABASE"
        set_env DB_USERNAME "$DB_USERNAME"
        set_env DB_PASSWORD "$DB_PASSWORD"
    fi
fi

echo "Installing Composer dependencies if composer.json exists..."
if [[ -f composer.json ]]; then
    sudo -u "$DEPLOY_USER" composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction
fi

echo "Installing/building frontend if package.json exists..."
if [[ -f package.json ]]; then
    sudo -u "$DEPLOY_USER" npm install
    sudo -u "$DEPLOY_USER" npm run build
fi

echo "Fixing Laravel permissions..."
mkdir -p "$APP_PATH/storage" "$APP_PATH/bootstrap/cache"
chown -R "$DEPLOY_USER:$WEB_GROUP" "$APP_PATH"
chmod -R 775 "$APP_PATH/storage"
chmod -R 775 "$APP_PATH/bootstrap/cache"

find "$APP_PATH/storage" -type f -exec chmod 664 {} \;
find "$APP_PATH/bootstrap/cache" -type f -exec chmod 664 {} \;

echo "Running Laravel commands if artisan exists..."
if [[ -f artisan ]]; then
    sudo -u "$DEPLOY_USER" php artisan key:generate --force || true
    sudo -u "$DEPLOY_USER" php artisan migrate --force || true
    sudo -u "$DEPLOY_USER" php artisan config:cache || true
    sudo -u "$DEPLOY_USER" php artisan route:cache || true
    sudo -u "$DEPLOY_USER" php artisan view:cache || true
fi

echo "Writing Nginx virtual host..."
cat > "/etc/nginx/sites-available/$APP_DIR" <<EOF
server {
    listen 80;
    server_name $DOMAIN_NAME;

    root $APP_PATH/public;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCK;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
EOF

ln -sf "/etc/nginx/sites-available/$APP_DIR" "/etc/nginx/sites-enabled/$APP_DIR"
rm -f /etc/nginx/sites-enabled/default

echo "Testing Nginx config..."
nginx -t

echo "Reloading Nginx..."
systemctl reload nginx

echo "Configuring firewall..."
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

echo "Obtaining SSL certificate..."
certbot --nginx \
    --non-interactive \
    --agree-tos \
    -m "$CERTBOT_EMAIL" \
    -d "$DOMAIN_NAME" \
    --redirect

echo "Final permission pass..."
chown -R "$DEPLOY_USER:$WEB_GROUP" "$APP_PATH"
find "$APP_PATH" -type d -exec chmod 775 {} \; || true
find "$APP_PATH" -type f -exec chmod 664 {} \; || true
chmod -R 775 "$APP_PATH/storage" "$APP_PATH/bootstrap/cache" || true
chmod 640 "$APP_PATH/.env" || true

echo
echo "========================================================="
echo "DEPLOYMENT SETUP COMPLETE"
echo "Site: https://$DOMAIN_NAME"
echo "App path: $APP_PATH"
echo "Deploy user: $DEPLOY_USER"
echo "========================================================="
echo
echo "GitHub Actions public key:"
cat "${DEPLOY_KEY_PATH}.pub"
echo
echo "========================================================="
echo "COPY THIS PRIVATE KEY TO YOUR GITHUB SECRET:"
echo "Secret name suggestion: SERVER_SSH_PRIVATE_KEY"
echo "========================================================="
cat "$DEPLOY_KEY_PATH"
echo "========================================================="
echo
echo "Also set these GitHub Secrets:"
echo "SERVER_HOST=$DOMAIN_NAME or server IP"
echo "SERVER_USER=$DEPLOY_USER"
echo "APP_PATH=$APP_PATH"
echo
#!/bin/bash
set -euo pipefail

################################################################################
# STRATOSPHERE - Installation complète (CLI mode)
# Installe et configure : Apache, PHP, MariaDB, OpenSSL, SSL, BD, etc.
#
# Usage: sudo bash install.sh
################################################################################

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_ROOT="/var/www/html/stratosphere"
SSL_DIR="${WEB_ROOT}/ssl"
APACHE_ENVVARS="/etc/apache2/envvars"
PHP_VERSION=""

log()    { echo -e "${BLUE}  →${NC} $*"; }
ok()     { echo -e "${GREEN}  ✓${NC} $*"; }
warn()   { echo -e "${YELLOW}  ⚠${NC} $*"; }
error()  { echo -e "${RED}  ✗${NC} $*"; exit 1; }
title()  { echo -e "\n${BOLD}${CYAN}$*${NC}"; }
divider(){ echo -e "${CYAN}────────────────────────────────────────────────────────${NC}"; }

log_info()   { log "$@"; }
log_success() { ok "$@"; }
log_warn()   { warn "$@"; }
log_error()  { error "$@"; }

# ── Vérification root ─────────────────────────────────────────────────────────
if [ "$EUID" -ne 0 ]; then
    error "Ce script doit être lancé avec sudo."
fi

clear
echo -e "${CYAN}"
cat << "EOF"
   ╔═══════════════════════════════════════════════════════╗
   ║    STRATOSPHERE  —  Installation Complète (CLI)      ║
   ║  Apache + PHP + MariaDB + OpenSSL + SSL + Config    ║
   ╚═══════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

# ── Questions ─────────────────────────────────────────────────────────────────
title "── Configuration ──"
divider

# Mot de passe MySQL
echo ""
log "Mot de passe pour le compte MySQL de l'application"
read -s -p "$(echo -e ${BLUE}  Mot de passe MySQL${NC}): " DB_PASS; echo ""
[ -z "$DB_PASS" ] && error "Le mot de passe ne peut pas être vide"

# Mot de passe admin
echo ""
log "Mot de passe pour le compte admin du tableau de bord"
read -s -p "$(echo -e ${BLUE}  Mot de passe admin${NC}): " ADMIN_PASS; echo ""
[ -z "$ADMIN_PASS" ] && error "Le mot de passe ne peut pas être vide"

# Email admin
echo ""
log "Email pour le compte admin"
read -p "$(echo -e ${BLUE}  Email admin${NC}): " ADMIN_EMAIL
[ -z "$ADMIN_EMAIL" ] && ADMIN_EMAIL="admin@localhost"
ok "Email: ${ADMIN_EMAIL}"

echo ""
ok "Configuration sauvegardée"

# ── Installation des paquets ──────────────────────────────────────────────────
title "── Installation des paquets système ──"
divider

log "Mise à jour des dépôts..."
apt-get update -qq

log "Installation d'Apache..."
apt-get install -y -qq apache2

log "Installation de PHP et dépendances..."
apt-get install -y -qq php php-mysql php-mbstring php-xml php-curl php-json libapache2-mod-php
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "unknown")
ok "PHP ${PHP_VERSION} installé"

log "Installation de MariaDB..."
apt-get install -y -qq mariadb-server mariadb-client

log "Installation d'OpenSSL..."
apt-get install -y -qq openssl
OPENSSL_VERSION=$(openssl version | cut -d' ' -f2)
ok "OpenSSL ${OPENSSL_VERSION} trouvé"

log "Installation d'utilitaires..."
apt-get install -y -qq curl wget unzip git

ok "Tous les paquets installés"

# ── Déploiement des fichiers ──────────────────────────────────────────────────
title "── Déploiement des fichiers ──"
divider

log "Suppression du répertoire existant..."
rm -rf "${WEB_ROOT}" 2>/dev/null || true

log "Création du répertoire ${WEB_ROOT}..."
mkdir -p "${WEB_ROOT}"

log "Copie des fichiers du projet..."
cp -r "${SCRIPT_DIR}/." "${WEB_ROOT}/"

log "Configuration des permissions..."
chown -R www-data:www-data "${WEB_ROOT}"
chmod -R 755 "${WEB_ROOT}"
chmod -R 775 "${WEB_ROOT}/uploads" "${WEB_ROOT}/logs" 2>/dev/null || true

ok "Fichiers déployés et configurés"

# ============================================================================
# GÉNÉRATION SSL
# ============================================================================

title "── Configuration SSL/TLS ──"
divider

log_info "Création du répertoire SSL..."
mkdir -p "${SSL_DIR}"
chmod 755 "${SSL_DIR}"

if [ -f "${SSL_DIR}/key.pem" ] && [ -f "${SSL_DIR}/cert.pem" ]; then
    log_warn "Certificats SSL existants détectés - Pas de régénération"
else
    log_info "Génération des certificats auto-signés..."
    log_info "  • Type: RSA 4096 bits"
    log_info "  • Validité: 365 jours"
    log_info "  • Subject: CN=localhost"
    
    openssl req -x509 -newkey rsa:4096 \
        -keyout "${SSL_DIR}/key.pem" \
        -out "${SSL_DIR}/cert.pem" \
        -days 365 -nodes \
        -subj "/C=FR/ST=State/L=City/O=StratoSphere/CN=localhost" 2>/dev/null
    
    if [ ! -f "${SSL_DIR}/key.pem" ] || [ ! -f "${SSL_DIR}/cert.pem" ]; then
        log_error "Erreur lors de la génération des certificats"
    fi
    
    log_success "Certificats générés avec succès"
fi

log_info "Validation des certificats..."
if openssl x509 -in "${SSL_DIR}/cert.pem" -text -noout > /dev/null 2>&1; then
    CERT_SUBJECT=$(openssl x509 -in "${SSL_DIR}/cert.pem" -noout -subject 2>/dev/null | sed 's/subject=//')
    CERT_DATES=$(openssl x509 -in "${SSL_DIR}/cert.pem" -noout -dates 2>/dev/null)
    log_success "Certificat valide"
    echo "   ${CERT_SUBJECT}"
    echo "   ${CERT_DATES}" | tr ',' '\n' | sed 's/^/   /'
else
    log_error "Certificat invalide!"
fi

log_info "Configuration des permissions SSL..."
chmod 600 "${SSL_DIR}/key.pem"
chmod 644 "${SSL_DIR}/cert.pem"
chown www-data:www-data "${SSL_DIR}/key.pem" "${SSL_DIR}/cert.pem"
ok "Permissions SSL configurées"

# ============================================================================
# ACTIVATION SERVICES
# ============================================================================

title "── Activation des services ──"
divider

log "Démarrage et activation d'Apache..."
systemctl enable apache2 --quiet 2>/dev/null || true
systemctl start apache2 2>/dev/null || true

log "Démarrage et activation de MariaDB..."
systemctl enable mariadb --quiet 2>/dev/null || true
systemctl start mariadb 2>/dev/null || true

log "Activation des modules Apache..."
a2enmod rewrite headers ssl -q 2>/dev/null || true

ok "Services démarrés et activés"

# ============================================================================
# CONFIGURATION APACHE
# ============================================================================

title "── Configuration Apache ──"
divider

log "Configuration d'Apache pour StratoSphere..."

# AllowOverride All
if ! grep -q "AllowOverride All" /etc/apache2/apache2.conf 2>/dev/null; then
    cat >> /etc/apache2/apache2.conf << 'APACHE_CONF'

<Directory /var/www/html>
    AllowOverride All
    Require all granted
</Directory>
APACHE_CONF
fi

# VirtualHost HTTPS
VHOST_SSL="/etc/apache2/sites-available/000-default-ssl.conf"
if [ ! -f "${VHOST_SSL}" ] || ! grep -q "SSLEngine on" "${VHOST_SSL}" 2>/dev/null; then
    log "Création du VirtualHost HTTPS..."
    cat > "${VHOST_SSL}" << 'VHOSTEOF'
<VirtualHost *:443>
    DocumentRoot /var/www/html
    ServerName localhost

    SSLEngine on
    SSLCertificateFile /var/www/html/stratosphere/ssl/cert.pem
    SSLCertificateKeyFile /var/www/html/stratosphere/ssl/key.pem

    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
VHOSTEOF
    a2ensite 000-default-ssl -q 2>/dev/null || true
    ok "VirtualHost HTTPS créé"
fi

# Redirection HTTP → HTTPS
if ! grep -q "RewriteEngine On" /etc/apache2/sites-available/000-default.conf 2>/dev/null; then
    log "Configuration de la redirection HTTP → HTTPS..."
    cat > /etc/apache2/sites-available/000-default.conf << 'HTTPVHOST'
<VirtualHost *:80>
    DocumentRoot /var/www/html
    ServerName localhost

    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
HTTPVHOST
fi

log "Redémarrage d'Apache..."
systemctl restart apache2 2>/dev/null || true

ok "Apache configuré"

# ============================================================================
# CONFIGURATION BASE DE DONNÉES
# ============================================================================

title "── Configuration Base de Données ──"
divider

DB_NAME="stratosphere_$(openssl rand -hex 3)"
DB_USER="stratos_app"

log "Création de l'utilisateur MySQL '${DB_USER}'..."
mysql -u root <<MYSQL 2>/dev/null || true
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
MYSQL

ok "Utilisateur MySQL créé (BD: ${DB_NAME})"

log "Création du fichier de configuration .env..."
cat > "${WEB_ROOT}/.env" << ENVFILE
APP_ENV=production
APP_DEBUG=false

DB_HOST=localhost
DB_PORT=3306
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}

ADMIN_EMAIL=${ADMIN_EMAIL}
ADMIN_PASS_HASH=$(echo -n "${ADMIN_PASS}" | sha256sum | cut -d' ' -f1)

APP_URL=https://localhost
APP_SECRET=$(openssl rand -hex 32)

SSL_CERT=/var/www/html/stratosphere/ssl/cert.pem
SSL_KEY=/var/www/html/stratosphere/ssl/key.pem
ENVFILE

chown www-data:www-data "${WEB_ROOT}/.env"
chmod 600 "${WEB_ROOT}/.env"

ok "Fichier .env créé"

# ============================================================================
# CRÉATION SCHEMA BD
# ============================================================================

title "── Initialisation Base de Données ──"
divider

log "Création de la base de données..."
mysql -u root <<MYSQL 2>/dev/null || true
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;
MYSQL

log "Création des tables..."
mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" <<SQL 2>/dev/null || true
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_uuid VARCHAR(100) UNIQUE NOT NULL,
    device_name VARCHAR(100),
    owner_id INT NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    command_name VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS location_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO users (username, password, email, role) 
VALUES ('admin', SHA2('${ADMIN_PASS}', 256), '${ADMIN_EMAIL}', 'admin');
SQL

ok "Schéma et données créés"

# ============================================================================
# VÉRIFICATIONS FINALES
# ============================================================================

title "── Vérifications finales ──"
divider

# Vérifier Apache
if systemctl is-active --quiet apache2; then
    ok "Apache: ACTIF"
else
    warn "Apache: INACTIF"
fi

# Vérifier MariaDB
if systemctl is-active --quiet mariadb; then
    ok "MariaDB: ACTIF"
else
    warn "MariaDB: INACTIF"
fi

# Vérifier SSL
if [ -f "${SSL_DIR}/cert.pem" ] && [ -f "${SSL_DIR}/key.pem" ]; then
    ok "Certificats SSL: PRÉSENTS"
else
    warn "Certificats SSL: MANQUANTS"
fi

# Vérifier PHP
PHP_TEST=$(php -v 2>/dev/null | head -n1)
ok "PHP: ${PHP_TEST}"

# Vérifier BD
DB_TEST=$(mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "SELECT COUNT(*) FROM users;" 2>/dev/null | tail -n1)
if [ "$DB_TEST" -eq "1" ]; then
    ok "Base de données: INITIALISÉE (1 utilisateur admin)"
fi

# ============================================================================
# RÉSUMÉ FINAL
# ============================================================================

title "── Installation terminée ✓ ──"
divider

echo ""
echo -e "${GREEN}${BOLD}  ✓ Installation complète réussie!${NC}"
echo ""
echo -e "  Informations de connexion:"
echo -e "  ${CYAN}URL:${NC}           https://localhost"
echo -e "  ${CYAN}Admin:${NC}         admin"
echo -e "  ${CYAN}Email:${NC}         ${ADMIN_EMAIL}"
echo ""
echo -e "  Informations techniques:"
echo -e "  ${CYAN}PHP:${NC}           ${PHP_VERSION}"
echo -e "  ${CYAN}OpenSSL:${NC}       ${OPENSSL_VERSION}"
echo -e "  ${CYAN}Base de données:${NC} ${DB_NAME}"
echo -e "  ${CYAN}Utilisateur BD:${NC}  ${DB_USER}"
echo -e "  ${CYAN}Web Root:${NC}       ${WEB_ROOT}"
echo -e "  ${CYAN}SSL Certs:${NC}      ${SSL_DIR}"
echo ""
echo -e "  Configuration:"
echo -e "  ${CYAN}Apache:${NC}         Configuré (AllowOverride All)"
echo -e "  ${CYAN}HTTPS:${NC}          Activé (Port 443)"
echo -e "  ${CYAN}Redirect:${NC}       HTTP → HTTPS"
echo -e "  ${CYAN}.env:${NC}           ${WEB_ROOT}/.env"
echo ""
echo -e "${YELLOW}  ⚠  Prochaines étapes:${NC}"
echo "  1. Accéder à: https://localhost/index.html"
echo "  2. Se connecter avec: admin / (votre mot de passe)"
echo "  3. Accepter le certificat auto-signé (normal pour développement)"
echo ""
echo -e "${YELLOW}  ⚠  Pour la production:${NC}"
echo "  1. Remplacer le certificat SSL par Let's Encrypt (GRATUIT)"
echo "  2. Configurer un nom de domaine réel"
echo "  3. Mettre à jour la configuration Apache"
echo ""
echo -e "${YELLOW}  ℹ  Documentation:${NC}"
echo "  • Installation: docs/INSTALL.md"
echo "  • SSL Setup: docs/SSL-HTTPS-SETUP.md"
echo "  • Architecture: docs/STRUCTURE_RECOMMANDEE.md"
echo ""

ok "Prêt pour l'utilisation!"
echo ""

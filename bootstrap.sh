#!/bin/bash
set -euo pipefail

################################################################################
# STRATOSPHERE - Bootstrap complet avec SSL
# Installe toutes les dépendances système + configure Apache/MariaDB
# puis ouvre le navigateur sur le wizard d'installation.
#
# Usage: sudo bash bootstrap.sh
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

# Alias pour clarté
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
   ║        STRATOSPHERE  —  Bootstrap + SSL Setup        ║
   ║     Installation complète des dépendances             ║
   ╚═══════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"
echo "Ce script va :"
echo "  1. Installer Apache, PHP, MariaDB, OpenSSL"
echo "  2. Générer certificats SSL (HTTPS)"
echo "  3. Déployer les fichiers du projet"
echo "  4. Créer l'utilisateur MySQL dédié"
echo "  5. Ouvrir le wizard d'installation dans votre navigateur"
echo ""

# ── Questions minimales ───────────────────────────────────────────────────────
title "── Configuration ──"
divider

# Mot de passe MySQL pour le compte applicatif
echo ""
warn "Choisissez un mot de passe pour le compte MySQL de l'application."
while true; do
    read -s -p "$(echo -e ${BLUE}  Mot de passe MySQL${NC}): " DB_PASS; echo ""
    [ -n "$DB_PASS" ] && break
    warn "Le mot de passe ne peut pas être vide."
done

# Mot de passe admin du tableau de bord
echo ""
warn "Choisissez un mot de passe pour le compte admin du tableau de bord."
while true; do
    read -s -p "$(echo -e ${BLUE}  Mot de passe admin${NC}): " ADMIN_PASS; echo ""
    [ -n "$ADMIN_PASS" ] && break
    warn "Le mot de passe ne peut pas être vide."
done

# Token d'installation (auto-généré, affiché à l'utilisateur)
INSTALL_TOKEN=$(openssl rand -hex 8)
echo ""
ok "Token d'installation généré automatiquement : ${YELLOW}${BOLD}${INSTALL_TOKEN}${NC}"
warn "Notez-le — il vous sera demandé dans le navigateur."
echo ""
read -p "$(echo -e ${BLUE}  Appuyez sur Entrée pour continuer...${NC})"

# ── Installation des paquets ──────────────────────────────────────────────────
title "── Installation des paquets ──"
divider

log "Mise à jour des dépôts..."
apt-get update -qq

log "Installation d'Apache..."
apt-get install -y -qq apache2

log "Installation de PHP (paquets officiels)..."
apt-get install -y -qq php php-mysql php-mbstring php-xml libapache2-mod-php
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "unknown")
ok "PHP ${PHP_VERSION} installé"

log "Installation de MariaDB..."
apt-get install -y -qq mariadb-server mariadb-client

log "Installation d'OpenSSL..."
apt-get install -y -qq openssl
OPENSSL_VERSION=$(openssl version | cut -d' ' -f2)
ok "OpenSSL ${OPENSSL_VERSION} trouvé"

# ── Déploiement des fichiers ──────────────────────────────────────────────────
title "── Déploiement ──"
divider

log "Copie des fichiers vers ${WEB_ROOT}..."
mkdir -p "${WEB_ROOT}"
cp -r "${SCRIPT_DIR}/." "${WEB_ROOT}/"
chown -R www-data:www-data "${WEB_ROOT}"
chmod -R 755 "${WEB_ROOT}"
chmod 644 "${WEB_ROOT}"/.env.example 2>/dev/null || true

ok "Fichiers déployés dans ${WEB_ROOT}"

# ============================================================================
# 2. GÉNÉRER CERTIFICATS SSL
# ============================================================================

title "── Configuration SSL/TLS ──"
divider

log_info "Création du répertoire SSL..."
mkdir -p "${SSL_DIR}"
chmod 755 "${SSL_DIR}"

if [ -f "${SSL_DIR}/key.pem" ] && [ -f "${SSL_DIR}/cert.pem" ]; then
    log_warn "Certificats SSL existants détectés - Pas de régénération"
else
    log_info "Génération des certificats auto-signés (4096 bits RSA, 365 jours)..."
    
    openssl req -x509 -newkey rsa:4096 \
        -keyout "${SSL_DIR}/key.pem" \
        -out "${SSL_DIR}/cert.pem" \
        -days 365 -nodes \
        -subj "/C=FR/ST=State/L=City/O=StratoSphere/CN=localhost" 2>/dev/null
    
    if [ -f "${SSL_DIR}/key.pem" ] && [ -f "${SSL_DIR}/cert.pem" ]; then
        log_success "Certificats générés avec succès!"
    else
        log_error "Erreur lors de la génération des certificats"
    fi
fi

log_info "Validation des certificats..."
if openssl x509 -in "${SSL_DIR}/cert.pem" -text -noout > /dev/null 2>&1; then
    CERT_SUBJECT=$(openssl x509 -in "${SSL_DIR}/cert.pem" -noout -subject 2>/dev/null)
    CERT_DATES=$(openssl x509 -in "${SSL_DIR}/cert.pem" -noout -dates 2>/dev/null)
    log_success "Certificat valide:"
    echo "   ${CERT_SUBJECT}" | sed 's/^/   /'
    echo "   ${CERT_DATES}" | sed 's/^/   /'
else
    log_error "Certificat invalide!"
fi

log_info "Configuration des permissions SSL..."
chmod 600 "${SSL_DIR}/key.pem"
chmod 644 "${SSL_DIR}/cert.pem"
chown www-data:www-data "${SSL_DIR}/key.pem" "${SSL_DIR}/cert.pem"
log_success "Permissions SSL configurées"

ok "SSL/TLS prêt"

# ── Activation des services ───────────────────────────────────────────────────
title "── Services ──"
divider

log "Activation et démarrage d'Apache..."
systemctl enable apache2 --quiet
systemctl start  apache2

log "Activation et démarrage de MariaDB..."
systemctl enable mariadb --quiet
systemctl start  mariadb

log "Activation des modules Apache (rewrite, headers, ssl)..."
a2enmod rewrite headers ssl -q

ok "Services démarrés"

# ── Configuration Apache ──────────────────────────────────────────────────────
title "── Configuration Apache ──"
divider

log "Activation de AllowOverride All..."
if ! grep -q "AllowOverride All" /etc/apache2/apache2.conf 2>/dev/null; then
    cat >> /etc/apache2/apache2.conf << 'APACHE_CONF'

<Directory /var/www/html>
    AllowOverride All
    Require all granted
</Directory>
APACHE_CONF
fi

log "Création du VirtualHost HTTPS..."
VHOST_SSL="/etc/apache2/sites-available/000-default-ssl.conf"
if [ ! -f "${VHOST_SSL}" ] || ! grep -q "SSLEngine on" "${VHOST_SSL}" 2>/dev/null; then
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
    a2ensite 000-default-ssl -q
    ok "VirtualHost HTTPS créé"
else
    ok "VirtualHost HTTPS existant"
fi

log "Redirection HTTP → HTTPS..."
if ! grep -q "RewriteEngine On" /etc/apache2/sites-available/000-default.conf 2>/dev/null; then
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

# Stocker les variables d'environnement pour le setup.php
log "Configuration des variables d'environnement..."
sed -i '/^# STRATOSPHERE install token/,+2d' "${APACHE_ENVVARS}" 2>/dev/null || true
cat >> "${APACHE_ENVVARS}" << ENVEOF

# STRATOSPHERE install token (supprimer après installation)
export INSTALL_TOKEN="${INSTALL_TOKEN}"
export INSTALL_ADMIN_PASS="${ADMIN_PASS}"
ENVEOF

ok "Variables injectées"

# ── Configuration MySQL ───────────────────────────────────────────────────────
title "── Base de données ──"
divider

DB_NAME="stratosphere_$(openssl rand -hex 3)"
DB_USER="stratos_app"

log "Création de l'utilisateur MySQL '${DB_USER}'..."
mysql -u root <<MYSQL 2>/dev/null || true
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
MYSQL

ok "Utilisateur MySQL créé"

log "Stockage des paramètres BD dans .env.bootstrap..."
cat > "${WEB_ROOT}/.env.bootstrap" << ENVFILE
DB_HOST=localhost
DB_PORT=3306
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
ENVFILE
chown www-data:www-data "${WEB_ROOT}/.env.bootstrap"
chmod 600 "${WEB_ROOT}/.env.bootstrap"

ok ".env.bootstrap créé (permissions 600)"

# ── Redémarrage Apache ────────────────────────────────────────────────────────
title "── Finalisation ──"
divider

log "Redémarrage d'Apache..."
systemctl restart apache2
ok "Apache redémarré"

# ── Ouverture du navigateur ───────────────────────────────────────────────────
title "── Installation terminée ──"
divider

SETUP_URL="https://localhost/stratosphere/setup.php?token=${INSTALL_TOKEN}"

echo ""
echo -e "${GREEN}${BOLD}  ✓ Toutes les dépendances sont installées !${NC}"
echo -e "    PHP version : ${PHP_VERSION}"
echo -e "    OpenSSL : ${OPENSSL_VERSION}"
echo ""
echo -e "  Ouvrez cette URL dans votre navigateur :"
echo ""
echo -e "  ${CYAN}${BOLD}  ${SETUP_URL}${NC}"
echo ""
echo -e "  Token : ${YELLOW}${BOLD}${INSTALL_TOKEN}${NC}"
echo ""
echo -e "${YELLOW}  ⚠  Acceptez le avertissement de certificat (normal pour auto-signé)${NC}"
echo ""

# Tenter d'ouvrir le navigateur automatiquement
if command -v xdg-open &>/dev/null; then
    log "Ouverture du navigateur..."
    REAL_USER="${SUDO_USER:-$USER}"
    if [ "$REAL_USER" != "root" ]; then
        sudo -u "$REAL_USER" DISPLAY="${DISPLAY:-:0}" \
            xdg-open "$SETUP_URL" 2>/dev/null &
    else
        xdg-open "$SETUP_URL" 2>/dev/null &
    fi
elif command -v sensible-browser &>/dev/null; then
    sensible-browser "$SETUP_URL" 2>/dev/null &
elif command -v firefox &>/dev/null; then
    firefox "$SETUP_URL" &
elif command -v chromium-browser &>/dev/null; then
    chromium-browser "$SETUP_URL" &
else
    warn "Impossible d'ouvrir le navigateur automatiquement."
    warn "Copiez l'URL ci-dessus dans votre navigateur."
fi

echo ""
warn "Après l'installation, supprimez les scripts d'install :"
echo "  sudo rm ${WEB_ROOT}/bootstrap.sh"
echo "  sudo rm ${WEB_ROOT}/setup.php"
echo ""
echo -e "${GREEN}${BOLD}  ✓ Bootstrap terminé!${NC}"
echo ""

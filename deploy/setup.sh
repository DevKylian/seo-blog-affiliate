#!/bin/bash
# =============================================================================
# BlogSEO — Setup (Version SAFE pour TinyCP / Serveur Existant)
# Usage : sudo bash setup.sh
# =============================================================================
set -euo pipefail

QUEUE_WORKERS=3

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; BOLD='\033[1m'; NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
info() { echo -e "${BLUE}[→]${NC} $1"; }
fail() { echo -e "${RED}[✗] ERREUR : $1${NC}" && exit 1; }
hr()   { echo -e "${BOLD}────────────────────────────────────────────${NC}"; }

[[ $EUID -ne 0 ]] && fail "Lancer en root : sudo bash setup.sh"

APP_DIR="/var/www/blogseo"
LOG_DIR="/var/log/blogseo"
BACKUP_DIR="/var/backups/blogseo"

read -rp "  Nom de domaine (ex: blogseo.fr) : " DOMAIN
[[ -z "$DOMAIN" ]] && fail "Nom de domaine requis."

read -rp "  Nom de la base de données (ex: blogseodb) : " DB_NAME
[[ -z "$DB_NAME" ]] && fail "Nom de la base requis."

read -rp "  Utilisateur de la base de données : " DB_USER
[[ -z "$DB_USER" ]] && fail "Utilisateur requis."

read -rsp "  Mot de passe MySQL : " DB_PASS
echo ""
[[ -z "$DB_PASS" ]] && fail "Mot de passe requis."

hr
echo -e "${BOLD}  BlogSEO — Setup Sécurisé (Spécial TinyCP)${NC}"
hr

# 1. UTILISATEUR DEPLOY
info "1/4 — Utilisateur deploy & Clé SSH"
if ! id "deploy" &>/dev/null; then
    useradd -m -s /bin/bash deploy
    log "Utilisateur 'deploy' créé."
fi

sudo -u deploy mkdir -p /home/deploy/.ssh
sudo -u deploy chmod 700 /home/deploy/.ssh

SSH_KEY_FILE="/home/deploy/.ssh/id_ed25519_github_actions"
if [ ! -f "$SSH_KEY_FILE" ]; then
    sudo -u deploy ssh-keygen -t ed25519 -f "$SSH_KEY_FILE" -N "" -q
    cat "${SSH_KEY_FILE}.pub" >> /home/deploy/.ssh/authorized_keys
    sudo -u deploy chmod 600 /home/deploy/.ssh/authorized_keys
    log "Clé SSH générée."
fi

# Sudoers
cat > /etc/sudoers.d/deploy-blogseo << 'EOF'
deploy ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart blogseo-queue*, /usr/bin/systemctl status blogseo-queue*, /usr/bin/systemctl restart blogseo-blog-worker, /usr/bin/systemctl status blogseo-blog-worker, /usr/bin/systemctl daemon-reload, /usr/bin/chown -R deploy\:www-data /var/www/blogseo, /usr/bin/rm -rf /var/www/blogseo/node_modules, /usr/bin/rm -rf /var/www/blogseo/public/build
EOF
chmod 0440 /etc/sudoers.d/deploy-blogseo

# MySQL credentials pour les backups
cat > /home/deploy/.my.cnf << EOF
[client]
user=${DB_USER}
password=${DB_PASS}
EOF
chmod 600 /home/deploy/.my.cnf
chown deploy:deploy /home/deploy/.my.cnf

# 2. DOSSIERS ET ENVIRONNEMENT
info "2/4 — Dossiers & Fichier .env"
mkdir -p "$APP_DIR" "$LOG_DIR" "$BACKUP_DIR"
chown -R deploy:www-data "$APP_DIR" "$LOG_DIR" "$BACKUP_DIR"

APP_KEY=$(openssl rand -base64 32)
if [ ! -f "$APP_DIR/.env" ]; then
cat > "$APP_DIR/.env" << EOF
APP_NAME="BlogSEO"
APP_ENV=production
APP_KEY=base64:${APP_KEY}
APP_DEBUG=false
APP_URL=https://${DOMAIN}

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

BROADCAST_CONNECTION=log
CACHE_STORE=database
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@${DOMAIN}
MAIL_FROM_NAME="BlogSEO"

GEMINI_API_KEY=
SEMRUSH_API_KEY=
INDEXNOW_KEY=

GOOGLE_SEARCH_CONSOLE_SITE_URL=https://${DOMAIN}
GOOGLE_SERVICE_ACCOUNT_JSON=

BING_WEBMASTER_SITE_URL=https://${DOMAIN}
BING_WEBMASTER_API_KEY=
EOF
chown deploy:www-data "$APP_DIR/.env"
chmod 640 "$APP_DIR/.env"
log "Fichier .env créé — complète les clés Gemini, Semrush, etc. manuellement."
else
    log "Fichier .env déjà existant, ignoré."
fi

# 3. SERVICES QUEUE WORKER
info "3/4 — Services Queue Worker (Laravel) — ${QUEUE_WORKERS} workers + 1 worker blog IA"

if systemctl list-unit-files blogseo-queue.service --no-legend 2>/dev/null | grep -q blogseo-queue.service; then
    systemctl stop blogseo-queue 2>/dev/null || true
    systemctl disable blogseo-queue --quiet 2>/dev/null || true
    rm -f /etc/systemd/system/blogseo-queue.service
    log "Ancien service single-instance supprimé."
fi

cat > "/etc/systemd/system/blogseo-queue@.service" << EOF
[Unit]
Description=BlogSEO Queue Worker %i
After=network.target mysql.service

[Service]
Type=simple
User=deploy
Group=www-data
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=90
Restart=always
RestartSec=5
StandardOutput=append:${LOG_DIR}/queue-%i.log
StandardError=append:${LOG_DIR}/queue-error-%i.log
KillSignal=SIGTERM
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
for i in $(seq 1 ${QUEUE_WORKERS}); do
    systemctl enable "blogseo-queue@${i}" --quiet
done
log "${QUEUE_WORKERS} workers queue configurés (seront démarrés au 1er déploiement)."

# Worker dédié blog IA (timeout long pour les appels Gemini multi-agents)
cat > "/etc/systemd/system/blogseo-blog-worker.service" << EOF
[Unit]
Description=BlogSEO Blog AI Worker
After=network.target mysql.service

[Service]
Type=simple
User=deploy
Group=www-data
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan queue:work database --queue=blog --sleep=5 --tries=2 --max-time=7200 --timeout=600
Restart=always
RestartSec=10
StandardOutput=append:${LOG_DIR}/blog-worker.log
StandardError=append:${LOG_DIR}/blog-worker-error.log
KillSignal=SIGTERM
TimeoutStopSec=120

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable blogseo-blog-worker --quiet
log "Worker blog IA configuré (sera démarré au 1er déploiement)."

# Crontab Laravel Scheduler (schedule:run toutes les minutes)
CRON_LINE="* * * * * cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"
( crontab -u deploy -l 2>/dev/null | grep -v 'artisan schedule:run'; echo "$CRON_LINE" ) | crontab -u deploy -
log "Crontab schedule:run ajouté pour l'utilisateur deploy."

# 4. LOGROTATE
info "4/4 — Logrotate"
cat > "/etc/logrotate.d/blogseo" << EOF
${LOG_DIR}/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 640 deploy www-data
    sharedscripts
    postrotate
        for i in \$(seq 1 ${QUEUE_WORKERS}); do systemctl restart "blogseo-queue@\${i}" > /dev/null 2>&1 || true; done
    endscript
}
EOF

# RÉSUMÉ
hr
echo -e "${BOLD}${GREEN}  ✅ Configuration Serveur terminée !${NC}"
echo ""
echo -e "${YELLOW}⚠  Complète manuellement dans ${APP_DIR}/.env :${NC}"
echo "   • GEMINI_API_KEY"
echo "   • SEMRUSH_API_KEY"
echo "   • INDEXNOW_KEY"
echo "   • GOOGLE_SERVICE_ACCOUNT_JSON"
echo "   • BING_WEBMASTER_API_KEY"
echo "   • MAIL_HOST / MAIL_USERNAME / MAIL_PASSWORD"
echo ""
PRIVATE_KEY=$(cat "$SSH_KEY_FILE")
echo -e "${BOLD}${YELLOW}=== CLÉ POUR GITHUB ACTIONS ===${NC}"
echo "Créez un secret 'SERVER_SSH_KEY' avec cette valeur exacte :"
echo ""
echo -e "${RED}${PRIVATE_KEY}${NC}"
echo ""
echo "Vérifiez aussi que 'SERVER_HOST' contient l'IP du VPS"
echo "et que 'SERVER_USER' contient 'deploy'."
hr

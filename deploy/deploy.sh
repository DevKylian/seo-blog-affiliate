#!/bin/bash
# =============================================================================
# BusinessKit — Script de déploiement (lancé par GitHub Actions)
# =============================================================================
set -euo pipefail

APP_DIR="/var/www/businesskit.fr"
LOG_DIR="/var/log/businesskit"
BACKUP_DIR="/var/backups/businesskit"
PHP="php"
GITHUB_REPO="DevKylian/seo-blog-affiliate"
GITHUB_BRANCH="main"
DEPLOY_LOG="${LOG_DIR}/deploy.log"
QUEUE_WORKERS=3

exec > >(tee -a "${DEPLOY_LOG}") 2>&1

log()  { echo "[DEPLOY $(date '+%Y-%m-%d %H:%M:%S')] $1"; }
fail() { log "ERREUR : $1"; exit 1; }

log "====== DÉPLOIEMENT DÉMARRÉ ======"

# --- Clonage initial si le dossier est vide ----------------------------------
if [ ! -d "$APP_DIR/.git" ]; then
    log "Premier déploiement détecté : clonage du dépôt..."
    sudo -u deploy git clone --branch "$GITHUB_BRANCH" "git@github.com:${GITHUB_REPO}.git" "$APP_DIR" --quiet || fail "Échec du clonage initial"
    git config --global --add safe.directory "$APP_DIR"
fi

cd "$APP_DIR" || fail "Impossible d'accéder à $APP_DIR"

# --- Mise à jour du code via Git (SSH deploy key) ----------------------------
git fetch origin ${GITHUB_BRANCH} --quiet
LOCAL=$(git rev-parse HEAD 2>/dev/null || echo "unborn")
REMOTE=$(git rev-parse origin/${GITHUB_BRANCH} 2>/dev/null || echo "unknown")

if [ "$LOCAL" = "$REMOTE" ] && [ "${1:-}" != "--force" ]; then
    log "Déjà à jour — rien à déployer."
    exit 0
fi

PREV_COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "none")
sudo /usr/bin/chown -R deploy:www-data "$APP_DIR"
git fetch origin ${GITHUB_BRANCH} --quiet
git reset --hard "origin/${GITHUB_BRANCH}" --quiet
NEW_COMMIT=$(git rev-parse --short HEAD)
log "Mise à jour : ${PREV_COMMIT} → ${NEW_COMMIT}"

# --- Permissions -------------------------------------------------------------
log "Ajustement des permissions..."
sudo /usr/bin/chown -R deploy:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null || true

# --- Mode maintenance --------------------------------------------------------
log "Mode maintenance activé..."
$PHP artisan down --render="errors.503" --retry=60 || true

# --- Dépendances PHP ---------------------------------------------------------
log "composer install..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet --ignore-platform-reqs \
    || composer update --no-dev --optimize-autoloader --no-interaction --quiet --ignore-platform-reqs

# --- Frontend Build ----------------------------------------------------------
log "Vérification des changements frontend..."
FRONTEND_REGEX="(resources/|lang/|app/View/|app/Livewire/|package|vite\.config|tailwind\.config|postcss\.config|composer\.lock)"
FRONTEND_FILES=$(git diff "${PREV_COMMIT}" "${NEW_COMMIT}" --name-only 2>/dev/null | grep -E "$FRONTEND_REGEX" || true)
FRONTEND_CHANGED=$(echo "$FRONTEND_FILES" | grep -v '^$' | wc -l || true)

    # Les assets sont désormais compilés dans GitHub Actions et transférés via scp.
    # On s'assure juste de supprimer le hot reload file et de mettre les bonnes permissions.
    rm -f public/hot || true
    chmod -R 775 public/build 2>/dev/null || true

# --- Sauvegarde BDD ----------------------------------------------------------
log "Sauvegarde de la base de données..."
DB_NAME=$(grep '^DB_DATABASE=' "$APP_DIR/.env" | cut -d= -f2 | tr -d '"' | tr -d "'")
mysqldump --defaults-file="/home/deploy/.my.cnf" --single-transaction "${DB_NAME}" \
    | gzip > "${BACKUP_DIR}/db_$(date +%Y%m%d_%H%M%S).sql.gz" || log "Attention: Échec de la sauvegarde BDD"
ls -t "${BACKUP_DIR}"/*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f

# --- Caches (avant migrations) -----------------------------------------------
log "Correction des guillemets dans le .env si nécessaire..."
if [ -f ".env" ]; then
    sed -i -E 's/^DB_PASSWORD=([^"].*=[^"]*)$/DB_PASSWORD="\1"/' .env
fi

log "Nettoyage du cache pour charger la configuration fraîche..."
$PHP artisan optimize:clear 2>/dev/null || true

# --- Migrations --------------------------------------------------------------
log "Migrations..."
$PHP artisan migrate --force || fail "Migrations échouées"

# --- Rebuild caches ----------------------------------------------------------
log "Rebuild des caches..."
$PHP artisan optimize:clear
$PHP artisan optimize
$PHP artisan view:cache
$PHP artisan event:cache

# --- Storage link ------------------------------------------------------------
$PHP artisan storage:link --quiet 2>/dev/null || true

# --- Mode maintenance OFF ----------------------------------------------------
log "Mode maintenance désactivé..."
$PHP artisan up

# --- SEO ---------------------------------------------------------------------
log "Soumission du sitemap à Google Search Console..."
$PHP artisan seo:submit-sitemap-to-google || log "Note: soumission sitemap Google ignorée ou échouée"

# --- Redémarrage queue workers -----------------------------------------------
log "Redémarrage des ${QUEUE_WORKERS} workers queue..."
$PHP artisan queue:restart || log "Note: Échec de queue:restart"
if systemctl list-unit-files "businesskit-queue@.service" --no-legend 2>/dev/null | grep -q "businesskit-queue@"; then
    for i in $(seq 1 ${QUEUE_WORKERS}); do
        sudo systemctl restart "businesskit-queue@${i}" || log "Note: Échec restart worker ${i}"
    done
    log "${QUEUE_WORKERS} workers redémarrés."
else
    sudo systemctl restart businesskit-queue || log "Note: Échec restart queue (single)"
fi

# Worker dédié blog IA
sudo systemctl restart businesskit-blog-worker && log "Worker blog IA redémarré." \
    || log "Note: Échec restart blog-worker (normal si pas encore configuré via setup.sh)"

log "====== DÉPLOIEMENT TERMINÉ ======"

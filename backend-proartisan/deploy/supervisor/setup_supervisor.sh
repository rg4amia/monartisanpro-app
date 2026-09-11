#!/bin/bash
# ==============================================================================
# Script de déploiement et configuration automatique de Supervisor pour ProsArtisan
# Usage : sudo bash setup_supervisor.sh [CHEMIN_PROJET] [UTILISATEUR]
# Exemple: sudo bash setup_supervisor.sh /var/www/prosartisan/backend-proartisan www-data
# ==============================================================================

set -e

PROJECT_PATH="${1:-/var/www/prosartisan/backend-proartisan}"
APP_USER="${2:-www-data}"

echo "=========================================================="
echo "🚀 Configuration de Supervisor pour ProsArtisan"
echo "Chemin du projet: ${PROJECT_PATH}"
echo "Utilisateur d'exécution: ${APP_USER}"
echo "=========================================================="

# 1. Vérification / Installation de Supervisor
if ! command -v supervisorctl &> /dev/null; then
    echo "📦 Installation de Supervisor..."
    apt-get update -y
    apt-get install -y supervisor
else
    echo "✅ Supervisor est déjà installé."
fi

# 2. Création des dossiers de logs
mkdir -p /var/log/supervisor
touch /var/log/supervisor/prosartisan-worker.log
touch /var/log/supervisor/prosartisan-scheduler.log
chown -R ${APP_USER}:${APP_USER} /var/log/supervisor/prosartisan-*.log 2>/dev/null || true

# 3. Génération des fichiers de configuration adaptés au chemin
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

cat <<EOF > /etc/supervisor/conf.d/prosartisan-worker.conf
[program:prosartisan-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${PROJECT_PATH}/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --memory=512 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${APP_USER}
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/prosartisan-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=3600
stopsignal=QUIT
EOF

cat <<EOF > /etc/supervisor/conf.d/prosartisan-scheduler.conf
[program:prosartisan-scheduler]
process_name=%(program_name)s
command=php ${PROJECT_PATH}/artisan schedule:work
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${APP_USER}
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/prosartisan-scheduler.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=60
stopsignal=TERM
EOF

echo "✅ Fichiers de configuration copiés dans /etc/supervisor/conf.d/."

# 4. Rechargement et démarrage des processus
echo "🔄 Rechargement de Supervisor..."
supervisorctl reread
supervisorctl update
supervisorctl start prosartisan-worker:* || supervisorctl restart prosartisan-worker:*
supervisorctl start prosartisan-scheduler:* || supervisorctl restart prosartisan-scheduler:*

echo "----------------------------------------------------------"
echo "📊 Statut des processus ProsArtisan :"
supervisorctl status
echo "=========================================================="
echo "🎉 Supervisor configuré et actif avec succès !"

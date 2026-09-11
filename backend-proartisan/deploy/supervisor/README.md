# Guide de Configuration Supervisor — ProsArtisan

Ce répertoire contient la configuration complète de **Supervisor** pour maintenir en arrière-plan les travailleurs de file d'attente (Queue Workers) et le planificateur de tâches (Scheduler) de **ProsArtisan**.

---

## 📦 Fichiers inclus

| Fichier | Rôle |
| :--- | :--- |
| **`prosartisan-worker.conf`** | Gère 4 workers parallèles pour `php artisan queue:work` (virements J-Code J+1, notifications OneSignal/SMS, webhooks). |
| **`prosartisan-scheduler.conf`** | Maintient le daemon `php artisan schedule:work` (Force-Pass 72h, Rouille du score ProsArtisan, Watchdog livreur, Health check). |
| **`setup_supervisor.sh`** | Script Bash d'installation et de configuration automatique en une seule commande. |

---

## 🚀 Installation Rapide (Recommandée)

Sur votre serveur Ubuntu / Debian / VPS :

```bash
cd /var/www/prosartisan/backend-proartisan/deploy/supervisor
sudo bash setup_supervisor.sh /chemin/vers/backend-proartisan www-data
```

*(Remplacez `/chemin/vers/backend-proartisan` et `www-data` si vos chemins ou utilisateurs diffèrent).*

---

## 🛠️ Configuration Manuelle (Étape par étape)

### 1. Installer Supervisor
```bash
sudo apt-get update
sudo apt-get install -y supervisor
```

### 2. Copier les fichiers de configuration
```bash
sudo cp prosartisan-worker.conf /etc/supervisor/conf.d/
sudo cp prosartisan-scheduler.conf /etc/supervisor/conf.d/
```
*(Assurez-vous que le chemin vers `artisan` et l'utilisateur `user` dans ces fichiers correspondent à votre environnement serveur).*

### 3. Créer les fichiers de logs
```bash
sudo touch /var/log/supervisor/prosartisan-worker.log
sudo touch /var/log/supervisor/prosartisan-scheduler.log
sudo chown www-data:www-data /var/log/supervisor/prosartisan-*.log
```

### 4. Activer et démarrer les daemons
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start prosartisan-worker:*
sudo supervisorctl start prosartisan-scheduler:*
```

### 5. Vérifier le statut
```bash
sudo supervisorctl status
```

Sortie attendue :
```text
prosartisan-scheduler            RUNNING   pid 12345, uptime 0:05:00
prosartisan-worker:prosartisan-worker_00   RUNNING   pid 12346, uptime 0:05:00
prosartisan-worker:prosartisan-worker_01   RUNNING   pid 12347, uptime 0:05:00
prosartisan-worker:prosartisan-worker_02   RUNNING   pid 12348, uptime 0:05:00
prosartisan-worker:prosartisan-worker_03   RUNNING   pid 12349, uptime 0:05:00
```

---

## 🔄 Gestion au Quotidien & Déploiement

### Lors d'un déploiement (`git pull` ou mise à jour du code)
Toujours ordonner à Laravel de redémarrer gracieusement les workers une fois le nouveau code déployé :
```bash
php artisan queue:restart
```
Supervisor relancera automatiquement les processus avec le nouveau code en mémoire sans interrompre les jobs en cours.

### Commandes utiles Supervisor
- **Voir le statut** : `sudo supervisorctl status`
- **Redémarrer les workers** : `sudo supervisorctl restart prosartisan-worker:*`
- **Redémarrer le planificateur** : `sudo supervisorctl restart prosartisan-scheduler:*`
- **Arrêter temporairement** : `sudo supervisorctl stop prosartisan-worker:*`
- **Consulter les logs en temps réel** :
  ```bash
  tail -f /var/log/supervisor/prosartisan-worker.log
  tail -f /var/log/supervisor/prosartisan-scheduler.log
  ```

---

## ⚙️ Paramètres Clés Expliqués

- `numprocs=4` : Exécute 4 workers concurrents pour absorber les pics de charge (notifications SMS/Push, paiements).
- `--sleep=3` : Attend 3 secondes avant de réinterroger la base si aucun job n'est en attente (économie CPU).
- `--tries=3` : Réessaie un job échoué jusqu'à 3 fois avant de le classer en `failed_jobs`.
- `--max-time=3600` : Recycle le processus PHP toutes les heures pour éviter toute fuite de mémoire PHP.
- `stopsignal=QUIT` & `stopwaitsecs=3600` : Assure une fermeture gracieuse ; si un job financier est en cours, Supervisor attend sa fin avant d'éteindre le worker.

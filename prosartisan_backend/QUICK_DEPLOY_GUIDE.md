# Guide de Déploiement Rapide 🚀

## ✅ Connexion SSH Vérifiée

Votre connexion SSH fonctionne! Voici les étapes pour finaliser le déploiement.

## Étape 1: Configurer les Secrets GitHub (5 min)

1. Allez sur GitHub → Votre repository → **Settings** → **Secrets and variables** → **Actions**

2. Cliquez sur **"New repository secret"** et ajoutez ces 4 secrets :

   **HOST**
   ```
   145.79.14.250
   ```

   **PORT**
   ```
   65002
   ```

   **USERNAME**
   ```
   u398732316
   ```

   **SSH_PRIVATE_KEY**
   ```bash
   # Sur votre machine locale, copiez le contenu de la clé :
   cat ~/.ssh/hostinger_deploy
   
   # Collez TOUT le contenu dans GitHub (incluant BEGIN et END)
   ```

3. Vérifiez que les 4 secrets sont bien créés ✓

## Étape 2: Préparer le Serveur (5 min)

Connectez-vous au serveur et exécutez le script de vérification :

```bash
# Connexion SSH
ssh -i ~/.ssh/hostinger_deploy -p 65002 u398732316@145.79.14.250

# Téléchargez le script de vérification (ou copiez-le manuellement)
# Puis exécutez-le
bash verify_server_setup.sh
```

Ou manuellement :

```bash
# Créer la structure de dossiers
mkdir -p /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend

# Créer le dossier de backups
mkdir -p /home/u398732316/backups

# Préparer le fichier .env (si vous en avez un)
# Copiez votre .env de production vers :
# /home/u398732316/.env.prod
```

## Étape 3: Créer le .env de Production

Sur le serveur, créez le fichier `.env` :

```bash
# Créez un .env de production
nano /home/u398732316/.env.prod
```

Contenu minimal requis :

```env
APP_NAME="ProSartisan"
APP_ENV=production
APP_KEY=base64:VOTRE_CLE_ICI
APP_DEBUG=false
APP_URL=https://prosartisan.net

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=VOTRE_HOST_DB
DB_PORT=5432
DB_DATABASE=VOTRE_DATABASE
DB_USERNAME=VOTRE_USERNAME
DB_PASSWORD=VOTRE_PASSWORD

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
```

**Note**: Le CI/CD copiera automatiquement ce fichier lors du premier déploiement.

## Étape 4: Lancer le Déploiement (1 min)

### Option A: Via Push Git
```bash
# Sur votre machine locale
git add .
git commit -m "Configure deployment for Hostinger"
git push origin master
```

### Option B: Via GitHub Actions UI
1. Allez sur GitHub → **Actions**
2. Sélectionnez le workflow "Deploy to Production"
3. Cliquez sur **"Run workflow"**
4. Sélectionnez la branche `master`
5. Cliquez sur **"Run workflow"**

## Étape 5: Surveiller le Déploiement (5-10 min)

1. Allez sur GitHub → **Actions**
2. Cliquez sur le workflow en cours d'exécution
3. Surveillez les logs en temps réel
4. Attendez que toutes les étapes soient ✓ vertes

## Étape 6: Vérifier le Déploiement

### Sur le serveur :
```bash
# Connectez-vous
ssh -i ~/.ssh/hostinger_deploy -p 65002 u398732316@145.79.14.250

# Vérifiez que les fichiers sont déployés
ls -la /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend/

# Vérifiez les logs Laravel
tail -f /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend/storage/logs/laravel.log
```

### Dans le navigateur :
1. Ouvrez https://prosartisan.net
2. Vous devriez voir votre application Laravel
3. Testez les routes API : https://prosartisan.net/api/v1/docs/spec

## Dépannage Rapide

### Le déploiement échoue sur GitHub Actions
- Vérifiez que les 4 secrets sont bien configurés
- Vérifiez les logs GitHub Actions pour voir l'erreur exacte
- Vérifiez que la clé SSH est complète (BEGIN et END inclus)

### Erreur 500 sur le site
```bash
# Sur le serveur
cd /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend

# Vérifiez les permissions
chmod -R 775 storage bootstrap/cache

# Vérifiez le .env
cat .env

# Générez la clé si nécessaire
php artisan key:generate

# Vérifiez les logs
tail -50 storage/logs/laravel.log
```

### Le site affiche une page blanche
- Vérifiez que le `.htaccess` racine existe et redirige correctement
- Vérifiez les logs Apache/Nginx
- Vérifiez que `public/index.php` existe

### Les assets ne chargent pas
```bash
# Sur le serveur
cd /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend

# Vérifiez que le dossier build existe
ls -la public/build/

# Si manquant, relancez le build localement et redéployez
```

## Commandes Utiles

```bash
# Vider les caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Reconstruire les caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Lancer les migrations
php artisan migrate --force

# Vérifier la configuration
php artisan about

# Voir les routes
php artisan route:list
```

## Checklist Finale

- [ ] Secrets GitHub configurés (HOST, PORT, USERNAME, SSH_PRIVATE_KEY)
- [ ] Structure de dossiers créée sur le serveur
- [ ] Fichier .env.prod créé sur le serveur
- [ ] Déploiement lancé via GitHub Actions
- [ ] Déploiement terminé avec succès (toutes les étapes ✓)
- [ ] Site accessible sur https://prosartisan.net
- [ ] API accessible sur https://prosartisan.net/api/v1/docs/spec
- [ ] Pas d'erreurs dans les logs Laravel

## Support

Si vous rencontrez des problèmes :
1. Consultez `SSH_CONFIG_VERIFIED.md` pour les détails de connexion
2. Consultez `HOSTINGER_SETUP_GUIDE.md` pour la configuration détaillée
3. Consultez `GITHUB_SECRETS_SETUP.md` pour les secrets GitHub
4. Vérifiez les logs GitHub Actions
5. Vérifiez les logs Laravel sur le serveur

## Prochaines Étapes

Une fois le déploiement réussi :
1. Configurez un domaine personnalisé si nécessaire
2. Configurez SSL/HTTPS (normalement automatique avec Hostinger)
3. Configurez les sauvegardes automatiques
4. Configurez la surveillance et les alertes
5. Testez toutes les fonctionnalités de l'application

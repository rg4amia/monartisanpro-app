# Prochaines Étapes pour le Déploiement 🚀

## ✅ Ce qui est fait

- [x] Connexion SSH testée et fonctionnelle
- [x] Workflow CI/CD configuré et optimisé
- [x] Documentation complète créée
- [x] Scripts de vérification créés
- [x] Configuration .htaccess pour redirection Laravel
- [x] Code style vérifié (Laravel Pint)

## 📋 Ce qu'il reste à faire (30 minutes max)

### 1. Configurer les Secrets GitHub (5 min)

Allez sur GitHub → Settings → Secrets and variables → Actions

Créez ces 4 secrets :

```
HOST = 145.79.14.250
PORT = 65002
USERNAME = u398732316
SSH_PRIVATE_KEY = [contenu de ~/.ssh/hostinger_deploy]
```

Pour obtenir la clé privée :
```bash
cat ~/.ssh/hostinger_deploy
```

Copiez TOUT le contenu (incluant BEGIN et END).

### 2. Préparer le Serveur (10 min)

Connectez-vous au serveur :
```bash
ssh -i ~/.ssh/hostinger_deploy -p 65002 u398732316@145.79.14.250
```

Créez la structure :
```bash
# Créer les dossiers
mkdir -p /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend
mkdir -p /home/u398732316/backups

# Créer le .env de production
nano /home/u398732316/.env.prod
```

Contenu minimal du .env :
```env
APP_NAME="ProSartisan"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://prosartisan.net

DB_CONNECTION=pgsql
DB_HOST=VOTRE_HOST
DB_PORT=5432
DB_DATABASE=VOTRE_DB
DB_USERNAME=VOTRE_USER
DB_PASSWORD=VOTRE_PASSWORD

LOG_CHANNEL=stack
LOG_LEVEL=error

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

Sauvegardez avec `Ctrl+X`, puis `Y`, puis `Enter`.

### 3. Lancer le Déploiement (2 min)

Sur votre machine locale :
```bash
cd /Users/stephaneamia/Documents/PROJET\ PERSO/monartisanpro-app

# Vérifier que tout est commité
git status

# Ajouter les nouveaux fichiers
git add .

# Commiter
git commit -m "Configure deployment for Hostinger"

# Pousser sur master
git push origin master
```

Le déploiement se lancera automatiquement!

### 4. Surveiller le Déploiement (5-10 min)

1. Allez sur GitHub → Actions
2. Cliquez sur le workflow en cours
3. Surveillez les logs en temps réel
4. Attendez que toutes les étapes soient ✓ vertes

### 5. Vérifier le Déploiement (5 min)

#### Dans le navigateur :
- Ouvrez https://prosartisan.net
- Vérifiez que l'application se charge
- Testez https://prosartisan.net/api/v1/docs/spec

#### Sur le serveur :
```bash
# Connectez-vous
ssh -i ~/.ssh/hostinger_deploy -p 65002 u398732316@145.79.14.250

# Vérifiez les fichiers
ls -la /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend/

# Vérifiez les logs
tail -50 /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend/storage/logs/laravel.log
```

## 🎯 Checklist Rapide

- [ ] Secrets GitHub configurés (HOST, PORT, USERNAME, SSH_PRIVATE_KEY)
- [ ] Dossiers créés sur le serveur
- [ ] Fichier .env.prod créé avec les bonnes valeurs DB
- [ ] Code poussé sur master
- [ ] Workflow GitHub Actions lancé
- [ ] Workflow terminé avec succès (toutes les étapes ✓)
- [ ] Site accessible sur https://prosartisan.net
- [ ] API accessible sur https://prosartisan.net/api/v1/docs/spec
- [ ] Pas d'erreurs dans les logs

## 🆘 En cas de problème

### Le workflow échoue
1. Vérifiez les logs GitHub Actions pour voir l'erreur exacte
2. Vérifiez que les 4 secrets sont bien configurés
3. Vérifiez que la clé SSH est complète (BEGIN et END inclus)

### Erreur 500 sur le site
```bash
# Sur le serveur
cd /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend

# Vérifiez les permissions
chmod -R 775 storage bootstrap/cache

# Générez la clé APP_KEY
php artisan key:generate

# Vérifiez les logs
tail -50 storage/logs/laravel.log
```

### Le site affiche une page blanche
1. Vérifiez que le .htaccess racine existe
2. Vérifiez les logs Apache/Nginx
3. Vérifiez que public/index.php existe

### Les assets ne chargent pas
```bash
# Vérifiez que le build existe
ls -la public/build/

# Si manquant, relancez le déploiement
```

## 📚 Documentation Disponible

Tous les guides sont dans le dossier `prosartisan_backend/` :

1. **QUICK_DEPLOY_GUIDE.md** - Guide complet pas à pas
2. **SSH_CONFIG_VERIFIED.md** - Infos de connexion SSH
3. **DEPLOYMENT_INFO.md** - Toutes les infos de déploiement
4. **HOSTINGER_SETUP_GUIDE.md** - Configuration Hostinger
5. **GITHUB_SECRETS_SETUP.md** - Configuration des secrets
6. **README.md** - Documentation générale du projet

## 🎉 Après le Déploiement Réussi

Une fois que tout fonctionne :

1. **Testez les fonctionnalités principales**
   - Inscription/Connexion
   - Création de mission
   - Recherche d'artisans
   - API endpoints

2. **Configurez la surveillance**
   - Mettez en place des alertes pour les erreurs
   - Surveillez l'espace disque
   - Surveillez la charge serveur

3. **Configurez les backups automatiques**
   - Base de données
   - Fichiers uploadés
   - Configuration

4. **Documentez les procédures**
   - Rollback en cas de problème
   - Maintenance régulière
   - Gestion des incidents

## 💡 Conseils

- **Testez d'abord en local** avant de déployer
- **Faites des commits atomiques** (une fonctionnalité = un commit)
- **Surveillez les logs** après chaque déploiement
- **Gardez les backups** des 10 derniers déploiements
- **Documentez les changements** importants

## 🚀 Commande Rapide pour Déployer

```bash
# Depuis le dossier du projet
git add . && git commit -m "Deploy: description des changements" && git push origin master
```

## ✨ Vous êtes prêt!

Tout est configuré et prêt pour le déploiement. Suivez simplement les 5 étapes ci-dessus et votre application sera en ligne en moins de 30 minutes!

Bonne chance! 🎉

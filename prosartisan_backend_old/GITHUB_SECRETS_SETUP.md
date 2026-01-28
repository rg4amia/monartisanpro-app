# Configuration des Secrets GitHub pour le Déploiement

## Erreur SSH Authentication

L'erreur `ssh: unable to authenticate, attempted methods [none password], no supported methods remain` signifie que les credentials SSH ne sont pas corrects ou pas configurés.

## Étapes de Configuration

### 1. Obtenir les Informations SSH de Hostinger

#### Via cPanel Hostinger :
1. Connectez-vous à votre cPanel Hostinger
2. Allez dans **"Advanced" → "SSH Access"**
3. Notez les informations :
   - **Host**: Généralement votre IP ou domaine
   - **Port**: Souvent 65002 pour Hostinger
   - **Username**: Votre nom d'utilisateur cPanel (ex: u398732316)

#### Vérifier les informations SSH :
```bash
# Testez la connexion SSH localement
ssh -p 65002 u398732316@145.79.14.250
```

### 2. Choisir la Méthode d'Authentification

Hostinger supporte deux méthodes :

#### Option A: Authentification par Mot de Passe (Plus Simple)
- Utilisez votre mot de passe cPanel

#### Option B: Authentification par Clé SSH (Plus Sécurisé - Recommandé)
1. **Générer une paire de clés SSH** (si vous n'en avez pas) :
   ```bash
   ssh-keygen -t ed25519 -C "github-actions-prosartisan" -f ~/.ssh/hostinger_deploy
   ```
   - Appuyez sur Entrée pour ne pas mettre de passphrase (ou ajoutez-en une)
   - Cela crée deux fichiers :
     - `hostinger_deploy` (clé privée)
     - `hostinger_deploy.pub` (clé publique)

2. **Ajouter la clé publique à Hostinger** :
   - Via cPanel → SSH Access → Manage SSH Keys
   - Cliquez sur "Import Key"
   - Collez le contenu de `hostinger_deploy.pub`
   - Ou via SSH :
     ```bash
     ssh-copy-id -i ~/.ssh/hostinger_deploy.pub -p 65002 u398732316@145.79.14.250
     ```

3. **Tester la connexion** :
   ```bash
   ssh -i ~/.ssh/hostinger_deploy -p 65002 u398732316@145.79.14.250
   ```

### 3. Configurer les Secrets GitHub

1. Allez sur votre repository GitHub
2. Cliquez sur **Settings** → **Secrets and variables** → **Actions**
3. Cliquez sur **"New repository secret"**

#### Secrets Requis :

##### Pour Authentification par Mot de Passe :
```
HOST = 145.79.14.250
PORT = 65002
USERNAME = u398732316
SSH_PASSWORD = votre_mot_de_passe_cpanel
```

##### Pour Authentification par Clé SSH (Recommandé) :
```
HOST = 145.79.14.250
PORT = 65002
USERNAME = u398732316
SSH_PRIVATE_KEY = contenu_de_votre_clé_privée
SSH_PASSPHRASE = passphrase_si_vous_en_avez_une (optionnel)
```

**Pour SSH_PRIVATE_KEY** :
```bash
# Affichez le contenu de votre clé privée
cat ~/.ssh/hostinger_deploy

# Copiez TOUT le contenu, y compris les lignes :
# -----BEGIN OPENSSH PRIVATE KEY-----
# ...
# -----END OPENSSH PRIVATE KEY-----
```

### 4. Vérification des Secrets

Après configuration, vos secrets devraient ressembler à :

```
✓ HOST
✓ PORT  
✓ USERNAME
✓ SSH_PASSWORD (si mot de passe)
  OU
✓ SSH_PRIVATE_KEY (si clé SSH)
✓ SSH_PASSPHRASE (optionnel)
```

### 5. Tester le Déploiement

1. Poussez un commit sur la branche `master`
2. Allez dans **Actions** sur GitHub
3. Vérifiez que le workflow démarre
4. Surveillez les logs pour voir si la connexion SSH fonctionne

## Dépannage

### Erreur: "Permission denied (publickey,password)"
- Vérifiez que le mot de passe est correct
- Vérifiez que la clé SSH est bien ajoutée à Hostinger
- Vérifiez que le username est correct

### Erreur: "Connection refused"
- Vérifiez le HOST et le PORT
- Vérifiez que SSH est activé sur votre compte Hostinger

### Erreur: "Host key verification failed"
Ajoutez cette option au workflow (déjà inclus dans appleboy/ssh-action) :
```yaml
script_stop: false
```

### Tester manuellement la connexion SSH

```bash
# Avec mot de passe
ssh -p 65002 u398732316@145.79.14.250

# Avec clé SSH
ssh -i ~/.ssh/hostinger_deploy -p 65002 u398732316@145.79.14.250

# Verbose pour debug
ssh -vvv -p 65002 u398732316@145.79.14.250
```

## Informations Hostinger Spécifiques

### Trouver votre Username
- Dans cPanel, en haut à droite, vous verrez votre username (ex: u398732316)
- Ou dans l'URL du cPanel : `https://cpanel.hostinger.com/u398732316`

### Activer SSH sur Hostinger
1. Connectez-vous à cPanel
2. Allez dans **Advanced** → **SSH Access**
3. Cliquez sur **"Manage SSH Keys"**
4. Assurez-vous que SSH est activé pour votre compte

### Limites Hostinger
- Certains plans Hostinger n'ont pas accès SSH
- Vérifiez que votre plan inclut l'accès SSH
- Si SSH n'est pas disponible, vous devrez utiliser FTP/SFTP à la place

## Alternative : Déploiement via FTP

Si SSH n'est pas disponible, modifiez le workflow pour utiliser FTP :

```yaml
- name: Deploy via FTP
  uses: SamKirkland/FTP-Deploy-Action@v4.3.4
  with:
    server: ftp.prosartisan.net
    username: ${{ secrets.FTP_USERNAME }}
    password: ${{ secrets.FTP_PASSWORD }}
    local-dir: ./prosartisan_backend/
    server-dir: /public_html/monartisanpro-app/prosartisan_backend/
```

## Support

Si vous continuez à avoir des problèmes :
1. Contactez le support Hostinger pour vérifier l'accès SSH
2. Vérifiez les logs GitHub Actions pour plus de détails
3. Testez la connexion SSH manuellement depuis votre machine locale

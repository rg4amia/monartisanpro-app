# Configuration SSH Vérifiée ✅

## Informations de Connexion Confirmées

La connexion SSH a été testée avec succès le 28 janvier 2026 à 19:21 UTC.

### Détails de Connexion
```
Host: 145.79.14.250
Port: 65002
Username: u398732316
Authentication: SSH Key (hostinger_deploy)
```

### Commande de Test Réussie
```bash
ssh -i ~/.ssh/hostinger_deploy -p 65002 u398732316@145.79.14.250
```

## Configuration des Secrets GitHub

Allez dans votre repository GitHub → **Settings** → **Secrets and variables** → **Actions**

### Secrets à Configurer :

1. **HOST**
   ```
   145.79.14.250
   ```

2. **PORT**
   ```
   65002
   ```

3. **USERNAME**
   ```
   u398732316
   ```

4. **SSH_PRIVATE_KEY**
   ```bash
   # Copiez le contenu de votre clé privée
   cat ~/.ssh/hostinger_deploy
   
   # Collez TOUT le contenu dans le secret GitHub, incluant :
   # -----BEGIN OPENSSH PRIVATE KEY-----
   # ...
   # -----END OPENSSH PRIVATE KEY-----
   ```

5. **SSH_PASSPHRASE** (optionnel)
   - Laissez vide si vous n'avez pas mis de passphrase lors de la génération de la clé
   - Sinon, entrez la passphrase que vous avez utilisée

## Vérification de la Structure sur le Serveur

Une fois connecté en SSH, vérifiez la structure :

```bash
# Connectez-vous
ssh -i ~/.ssh/hostinger_deploy -p 65002 u398732316@145.79.14.250

# Vérifiez la structure
ls -la /home/u398732316/domains/prosartisan.net/public_html/

# Vérifiez le dossier de déploiement
ls -la /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/

# Créez le dossier si nécessaire
mkdir -p /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend
```

## Test du Déploiement

Après avoir configuré les secrets GitHub :

1. Poussez un commit sur la branche `master`
2. Allez dans **Actions** sur GitHub
3. Le workflow devrait se lancer automatiquement
4. Surveillez les logs pour vérifier que tout fonctionne

## Notes Importantes

- **Charge serveur élevée** : Le serveur affiche une charge de 9.67, 10.32, 10.29 - c'est assez élevé
- **Tentatives de connexion échouées** : Il y a eu 2 tentatives échouées depuis la dernière connexion réussie
- **Sécurité** : Assurez-vous de ne jamais commiter votre clé privée SSH dans le repository

## Commandes Utiles sur le Serveur

```bash
# Vérifier l'espace disque
df -h

# Vérifier la version PHP
php -v

# Vérifier Composer
composer --version

# Vérifier les permissions
ls -la /home/u398732316/domains/prosartisan.net/public_html/

# Créer un backup manuel
tar -czf ~/backup-$(date +%Y%m%d).tar.gz /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/
```

## Prochaines Étapes

1. ✅ Connexion SSH vérifiée
2. ⏳ Configurer les secrets GitHub avec les bonnes valeurs
3. ⏳ Tester le déploiement automatique
4. ⏳ Vérifier que l'application fonctionne sur https://prosartisan.net

## Support

Si vous rencontrez des problèmes :
- Vérifiez les logs GitHub Actions
- Connectez-vous en SSH pour vérifier manuellement
- Consultez les logs Laravel : `tail -f storage/logs/laravel.log`

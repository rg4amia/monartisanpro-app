# Configuration Backend pour Émulateur Android

## Problème
L'URL `http://backend-proartisan.test` ne fonctionne pas dans l'émulateur Android Studio car c'est un domaine local.

## Solution

### 1. Démarrer le backend Laravel

Dans votre terminal, allez dans le dossier backend:

```bash
cd backend-proartisan
php artisan serve --host=0.0.0.0 --port=8000
```

Le backend sera accessible sur `http://127.0.0.1:8000`

### 2. Configuration automatique

L'application Flutter utilise maintenant `EnvConfig` qui détecte automatiquement l'environnement:

- **Émulateur Android**: `http://10.0.2.2:8000/api/v1`
- **iOS Simulator**: `http://localhost:8000/api/v1`
- **Appareil physique**: Modifiez `deviceBaseUrl` dans `env_config.dart` avec votre IP locale
- **Production**: `https://api.prosartisan.com/api/v1`

### 3. Trouver votre IP locale (pour appareil physique)

**Mac/Linux:**
```bash
ifconfig | grep "inet " | grep -v 127.0.0.1
```

**Windows:**
```bash
ipconfig
```

Cherchez l'adresse IPv4 (ex: 192.168.1.10)

### 4. Tester l'API

Une fois le backend démarré, testez dans votre navigateur:
```
http://localhost:8000/api/v1/sectors
```

### 5. Configuration CORS (si nécessaire)

Si vous avez des erreurs CORS, vérifiez `config/cors.php`:

```php
'allowed_origins' => ['*'],
'allowed_origins_patterns' => [],
'allowed_headers' => ['*'],
'allowed_methods' => ['*'],
```

## Résolution des problèmes

### Erreur: Connection refused
- Vérifiez que le backend tourne avec `php artisan serve`
- Vérifiez le port (8000 par défaut)

### Erreur: Network unreachable
- Sur appareil physique: utilisez votre IP locale au lieu de 10.0.2.2
- Assurez-vous que l'appareil et l'ordinateur sont sur le même réseau WiFi

### Erreur: 404 Not Found
- Vérifiez que l'URL de base est correcte
- Testez l'endpoint dans le navigateur d'abord

## URLs de référence

| Environnement | URL |
|---------------|-----|
| Émulateur Android | `http://10.0.2.2:8000/api/v1` |
| iOS Simulator | `http://localhost:8000/api/v1` |
| Appareil physique | `http://[VOTRE_IP]:8000/api/v1` |
| Production | `https://api.prosartisan.com/api/v1` |

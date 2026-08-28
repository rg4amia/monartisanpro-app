# Architecture Hostinger - Assistant IA & Laravel Admin

Ce document présente les adaptations spécifiques à l'environnement d'hébergement mutualisé **Hostinger** pour garantir la stabilité, la réactivité et la bonne communication entre l'application mobile Flutter et le backend web Laravel.

---

## 1. ⚙️ Gestion de la File d'Attente (Queues) sans Daemon

Sur Hostinger mutualisé, il est impossible de faire tourner un daemon persistant comme `php artisan queue:work` en tâche de fond. Le système le tuera automatiquement après quelques minutes.

### Solution 1 : Le mode Synchrone (Recommandé pour les tests rapides)
Si les tâches d'extraction (VLM) ou d'envoi de notifications (OneSignal) ne sont pas trop lourdes, configurez le driver de file d'attente en synchrone dans votre fichier `.env` de production :
```env
QUEUE_CONNECTION=sync
```
*Avantage :* Les tâches s'exécutent immédiatement dans la requête HTTP sans avoir besoin de worker.
*Inconvénient :* L'utilisateur mobile doit attendre la fin du traitement avant d'avoir une réponse.

### Solution 2 : Cron Job Hostinger (Recommandé pour la production)
Si vous conservez `QUEUE_CONNECTION=database`, vous devez configurer une tâche planifiée (Cron Job) dans le hPanel d'Hostinger :

1. Connectez-vous à votre **hPanel Hostinger**.
2. Allez dans **Avancé** &rarr; **Tâches Planifiées (Cron Jobs)**.
3. Créez un nouveau Cron Job avec les paramètres suivants :
    ```bash
     /usr/bin/php ~/domains/prosartisan.net/public_html/monartisanpro-app/backend-proartisan/artisan queue:work --stop-when-empty
     ```
     *(L'utilisation du tilde `~/` permet d'éviter les erreurs de nom d'utilisateur système absolu sur Hostinger).*
   * **Intervalle :** Toutes les minutes (`* * * * *`).

### 🔍 Astuce : Trouver le chemin absolu exact sur votre Hostinger
Si le Cron indique toujours que le fichier est introuvable, créez un fichier de diagnostic temporaire nommé `chemin.php` dans le dossier `public` de votre projet contenant :
```php
<?php echo __DIR__; ?>
```
En visitant `https://prosartisan.net/chemin.php`, vous obtiendrez le chemin absolu exact à utiliser (il suffira de remplacer la fin par `/artisan`).

*Fonctionnement :* Le script se lancera chaque minute, traitera tous les jobs en attente dans la base de données, puis s'arrêtera proprement dès que la file d'attente est vide, respectant ainsi les limites de ressources de Hostinger.

---

## 2. ⚡ Désactivation du Cache CDN Hostinger (HCDN)

Le CDN intégré de Hostinger met en cache les fichiers statiques de manière extrêmement agressive (jusqu'à 7 jours), ce qui empêche les téléphones des utilisateurs de recevoir les mises à jour des fichiers `client.js`, `db.js` ou `client.html`.

### Solution : Règles spécifiques dans le fichier `.htaccess` de production
Ajoutez ces lignes tout en haut du fichier `.htaccess` situé dans le dossier `/public` de votre Laravel de production (ou à la racine de votre hébergement) :

```apache
# Désactiver le cache pour les fichiers stratégiques de l'IA
<FilesMatch "\.(html|js|json|css)$">
    <IfModule mod_headers.c>
        Header set Cache-Control "no-cache, no-store, must-revalidate"
        Header set Pragma "no-cache"
        Header set Expires 0
    </IfModule>
</FilesMatch>

# Désactiver spécifiquement le CDN Hostinger sur ces assets
<FilesMatch "(client\.js|db\.js|config\.js|client\.html)$">
    <IfModule mod_headers.c>
        Header set X-HCDN-Cache-Control "no-cache"
    </IfModule>
</FilesMatch>
```

---

## 3. 🌐 Configuration CORS Sécurisée

Lorsque l'application mobile effectue des requêtes AJAX (`fetch`), Hostinger peut bloquer l'accès si les en-têtes CORS ne sont pas explicitement renvoyés, surtout lors de l'envoi d'images en Base64.

### Étape A : Activer CORS dans le fichier `.htaccess`
Ajoutez ces règles à votre `.htaccess` de production pour autoriser toutes les origines ou l'origine de votre WebView :

```apache
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Headers "Origin, X-Requested-With, Content-Type, Accept, Authorization"
    Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
</IfModule>
```

### Étape B : Configurer Laravel Cors (`config/cors.php`)
Assurez-vous que le fichier `config/cors.php` autorise les routes API :
```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

---

## 4. 🧠 Sécurisation et Protection contre les Timeouts de l'API Gemini

Les requêtes HTTP vers l'API externe de Google Gemini peuvent parfois dépasser les 30 secondes en cas de forte charge, déclenchant une erreur `504 Gateway Timeout` sur Hostinger.

### Solution : Timeout strict dans Laravel Http Client
Modifiez l'appel de l'API Gemini dans `LlmAdminController.php` (méthode `callGeminiApi`) pour ajouter un timeout raisonnable (ex: 12 secondes) :

```php
$response = Http::timeout(12)
    ->withHeaders([
        'Content-Type' => 'application/json',
    ])
    ->post($url, [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ]);
```

Si le timeout est dépassé, l'application attrapera proprement l'exception et basculera instantanément sur le **mode de secours local (Local Chat Fallback)**, évitant ainsi d'afficher un écran d'erreur noir ou un crash à l'artisan sur le chantier.

---

## 5. 🌐 Configuration Web de Production (Reverse Proxy Next.js + Laravel)

Pour que le Front Office Next.js (tournant sur le port 3000) et le Backend Laravel (API & Admin) cohabitent sur le même serveur et le même nom de domaine, vous devez configurer le serveur web (Nginx ou Apache) pour rediriger le trafic de manière sélective.

### Option A : Configuration Nginx (Recommandé pour VPS)

Ajoutez ou modifiez le bloc `server` de votre configuration Nginx (`/etc/nginx/sites-available/prosartisan`) :

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name prosartisan.net www.prosartisan.net;
    root /var/www/monartisanpro-app/backend-proartisan/public;

    index index.php index.html;

    charset utf-8;

    # 1. API Laravel, Sanctum et Backoffice Admin
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /admin {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /sanctum {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /storage {
        alias /var/www/monartisanpro-app/backend-proartisan/storage/app/public;
        access_log off;
        log_not_found off;
        expires max;
    }

    # 2. Exécution PHP pour Laravel
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 3. Redirection par défaut vers le Front Office Next.js (Port 3000)
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Option B : Configuration Apache (`.htaccess` / VirtualHost)

Si vous utilisez un serveur Apache (Hostinger mutualisé ou VPS configuré avec Apache), vous pouvez utiliser le module `mod_proxy` pour rediriger le trafic. 

Ajoutez ces règles dans le fichier `.htaccess` situé à la racine du dossier public de votre site (`public_html/`) :

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # 1. Ne pas intercepter les requêtes pour l'API Laravel, le Backoffice Admin et le stockage
    RewriteRule ^(api|admin|sanctum|storage) - [L]

    # 2. Proxy vers le serveur Next.js sur le port 3000 pour tout le reste
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ http://127.0.0.1:3000/$1 [P,L]
</IfModule>
```


# GitHub Actions Deployment Troubleshooting

## SSH Connection Timeout Error

If you're seeing `dial tcp ***:***: i/o timeout`, follow these steps:

### 1. Verify GitHub Secrets

Make sure these secrets are correctly set in your GitHub repository:
- `HOST` - Your Hostinger server hostname (e.g., `srv123.hostinger.com` or IP address)
- `PORT` - SSH port (usually `22`, but Hostinger may use `65002` or another port)
- `USERNAME` - Your cPanel/SSH username
- `SERVER_PASSWORD` - Your SSH password

To check/add secrets:
1. Go to your GitHub repository
2. Settings → Secrets and variables → Actions
3. Verify all secrets are present and correct

### 2. Find Your Correct SSH Port

Hostinger often uses non-standard SSH ports. To find yours:

1. Log into Hostinger hPanel
2. Go to Advanced → SSH Access
3. Look for "SSH Port" - it's often `65002` instead of `22`
4. Update your `PORT` secret in GitHub with this value

### 3. Enable SSH Access in Hostinger

1. Log into Hostinger hPanel
2. Go to Advanced → SSH Access
3. Make sure SSH is enabled
4. Note the SSH hostname and port shown

### 4. Test SSH Connection Locally

From your terminal, test the connection:

```bash
ssh -p YOUR_PORT YOUR_USERNAME@YOUR_HOST
```

If this fails locally, the issue is with your Hostinger SSH configuration, not GitHub Actions.

### 5. Check Hostinger Firewall

Hostinger may block GitHub Actions IPs. Contact Hostinger support to:
- Whitelist GitHub Actions IP ranges
- Temporarily disable firewall to test
- Check if your account has SSH restrictions

### 6. Alternative: Use SSH Key Authentication

Password authentication can be unreliable. Switch to SSH keys:

1. Generate SSH key pair locally:
```bash
ssh-keygen -t ed25519 -C "github-actions" -f ~/.ssh/hostinger_deploy
```

2. Add public key to Hostinger:
   - Log into hPanel
   - Go to Advanced → SSH Access
   - Add the content of `~/.ssh/hostinger_deploy.pub`

3. Update GitHub workflow to use key:
   - Add secret `SSH_PRIVATE_KEY` with content of `~/.ssh/hostinger_deploy`
   - Replace `password: ${{ secrets.SERVER_PASSWORD }}` with `key: ${{ secrets.SSH_PRIVATE_KEY }}`

### 7. Use Hostinger's Git Deployment (Alternative)

If SSH continues to fail, consider Hostinger's built-in Git deployment:

1. In hPanel, go to Advanced → Git
2. Create a new repository connection
3. Connect to your GitHub repository
4. Set deployment path to your Laravel directory
5. Configure post-deployment script for Laravel commands

### 8. Manual Deployment (Temporary Solution)

While troubleshooting, you can deploy manually:

1. Build locally:
```bash
cd backend
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

2. Create archive:
```bash
tar -czf deploy.tar.gz --exclude='node_modules' --exclude='.git' .
```

3. Upload via FTP/File Manager in hPanel

4. SSH into server and extract:
```bash
cd /home/YOUR_USERNAME/domains/prosartisan.net/public_html/monartisanpro-app/backend
tar -xzf deploy.tar.gz
php artisan migrate --force
php artisan config:cache
```

## Common Hostinger SSH Ports

- Standard: `22`
- Common alternative: `65002`
- Check your hPanel for the exact port

## Getting Help

If none of these work:
1. Contact Hostinger support with error message
2. Ask them to verify SSH access is enabled
3. Request GitHub Actions IP whitelisting
4. Ask for their recommended deployment method

## Testing the Fixed Workflow

After making changes:
1. Commit and push to a test branch
2. Check Actions tab for deployment status
3. Review logs for any new errors
4. Once working, merge to master/develop

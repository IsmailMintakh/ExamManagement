# Deploy to Hostinger via SSH on git push

Auto-deploys on every push to `main`. Below is the one-time setup.

## 1. One-time Hostinger setup

SSH into your Hostinger server (hPanel → Advanced → SSH Access):

```bash
ssh u123456@your-server.hostinger.com -p 65002
```

Clone the repo into your home directory (NOT into `public_html`):

```bash
cd ~
git clone git@github.com:YOUR_ORG/exam_management.git
cd exam_management
```

> **Why not `public_html`?** Only the `public/` folder of a Laravel app should be web-exposed. Everything else (`.env`, `storage/`, `app/`) must stay private.

### Point `public_html` at the app's `public/` folder

```bash
# Backup whatever's in public_html (default Hostinger placeholder)
mv ~/public_html ~/public_html.bak

# Symlink public_html → ~/exam_management/public
ln -s ~/exam_management/public ~/public_html
```

### Create `.env` on the server

```bash
cp .env.example .env
nano .env          # edit DB credentials, APP_URL, APP_KEY, etc.
php artisan key:generate
```

Key settings for production:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=u123456_exam
DB_USERNAME=u123456_admin
DB_PASSWORD=...
SESSION_SECURE_COOKIE=true
```

### First-run install

```bash
chmod +x deploy.sh
./deploy.sh
```

That runs the full deploy script: composer install, migrations, npm build, caching, etc.

## 2. GitHub Secrets — what to set

In your GitHub repo: **Settings → Secrets and variables → Actions → New repository secret**.

| Secret name | Value | Where to find |
|---|---|---|
| `HOSTINGER_HOST` | `your-server.hostinger.com` (or IP) | hPanel → SSH Access |
| `HOSTINGER_USER` | `u123456` (your SSH username) | hPanel → SSH Access |
| `HOSTINGER_PORT` | `65002` (Hostinger default; sometimes `22`) | hPanel → SSH Access |
| `HOSTINGER_SSH_KEY` | private key (full contents, including `-----BEGIN OPENSSH PRIVATE KEY-----` and `-----END...`) | see below |

### Generating the SSH key pair

On your local machine:

```bash
ssh-keygen -t ed25519 -C "github-actions" -f ~/.ssh/hostinger_deploy -N ""
```

This produces two files: `hostinger_deploy` (private) and `hostinger_deploy.pub` (public).

**Copy the public key to Hostinger**:

```bash
ssh-copy-id -i ~/.ssh/hostinger_deploy.pub -p 65002 u123456@your-server.hostinger.com
```

Or manually: paste the contents of `hostinger_deploy.pub` into hPanel → SSH Access → Manage SSH Keys.

**Copy the private key into GitHub**:

```bash
cat ~/.ssh/hostinger_deploy        # copy ALL of this
```

Paste the entire output (including `BEGIN`/`END` lines) as the `HOSTINGER_SSH_KEY` secret in GitHub.

### Test the key works

```bash
ssh -i ~/.ssh/hostinger_deploy -p 65002 u123456@your-server.hostinger.com 'whoami'
# Should print your username with no password prompt.
```

## 3. How it works

1. You `git push origin main`.
2. GitHub Actions picks up `.github/workflows/deploy.yml`.
3. The workflow opens an SSH connection to Hostinger using the secrets.
4. It runs `cd ~/exam_management && ./deploy.sh`.
5. `deploy.sh` does the full deploy:
   - `php artisan down` (maintenance mode)
   - `git fetch && git reset --hard origin/main`
   - `composer install --no-dev`
   - `php artisan migrate --force`
   - `npm ci && npm run build`
   - `php artisan storage:link`
   - clear + cache config / routes / views / events
   - `php artisan queue:restart`
   - `php artisan up`
6. Total time: ~30–60 seconds. Uses graceful maintenance mode so users get a polite "Be right back" page instead of errors.

## 4. Manual deploy (skip GitHub)

If GitHub Actions is unavailable, you can still deploy manually:

```bash
ssh u123456@your-server.hostinger.com -p 65002
cd ~/exam_management
./deploy.sh
```

## 5. Rolling back

```bash
ssh u123456@your-server.hostinger.com -p 65002
cd ~/exam_management
git log --oneline -10        # find the commit you want
git reset --hard <commit>
./deploy.sh
```

## 6. Watching the deploy log

Every deploy appends to `storage/logs/deploy.log`:

```bash
tail -f ~/exam_management/storage/logs/deploy.log
```

## 7. Troubleshooting

**"Permission denied (publickey)"** — The server doesn't recognize the GitHub SSH key. Verify the public key is in `~/.ssh/authorized_keys` on the server, and that `~/.ssh` is `700` and `authorized_keys` is `600`.

**`composer: command not found`** — Hostinger sometimes requires `~/composer.phar` instead. Set `COMPOSER=php ~/composer.phar` as a workflow env var, or alias it in `~/.bashrc`.

**`npm: command not found`** — Enable Node.js via hPanel → Advanced → Node.js. Then `which npm` to find the path and export `NODE_BIN=/full/path/to/npm` in `deploy.sh` config block.

**`storage/framework/views: permission denied`** — Run once on the server: `chmod -R 775 storage bootstrap/cache && chown -R u123456:u123456 storage bootstrap/cache`.

**Deploy hangs at `npm ci`** — Hostinger shared plans have a 512MB memory cap on npm. Add `--max_old_space_size=512` to your build script, or build assets locally and commit `public/build/` (less ideal).

**Workflow runs but nothing changes on the server** — Check the GitHub Actions log. If you see "Connection refused" or timeout, your SSH port is wrong (`HOSTINGER_PORT` should be `65002` not `22` on most Hostinger plans).

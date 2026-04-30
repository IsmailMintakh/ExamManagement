# Deploy to Hostinger via FTP on git push

Auto-deploys on every push to `main` using FTPS upload. No SSH required — works on shared/Premium Hostinger plans.

## How it works

1. You `git push origin main`.
2. GitHub Actions (`.github/workflows/deploy.yml`) builds **only the frontend** in CI:
   - Validates `composer.json` syntax (no install — `vendor/` is built on the server)
   - Node 20 + `npm ci && npm run build` (Vite production assets)
3. GitHub Actions uploads source code + built assets to Hostinger via **FTPS** (encrypted FTP). `vendor/` is **excluded** — typically ~500 files instead of ~5,000.
4. After upload, GitHub Actions hits a tiny PHP endpoint on your site (`https://your-domain.com/deploy-hook.php`) which:
   - Runs `composer install --no-dev --optimize-autoloader` on the server (so any newly-added PHP packages get pulled in automatically)
   - Runs `migrate --force`, clears caches, restarts queues
   - Protected by a shared secret so only your workflow can trigger it
5. Total time: ~2 minutes (FTP upload ~60s + composer install ~30–60s + cache clears ~10s).

---

## One-time setup

### Step 1 — Hostinger directory layout

Two folders on the server:

```
~/domains/your-domain.com/
    ├── public_html/         ← Hostinger document root (web-accessible)
    └── exam_management/     ← Laravel project root (PRIVATE)
        ├── app/
        ├── config/
        ├── public/
        ├── storage/
        ├── vendor/
        └── .env
```

Only `public/` should be web-exposed. We'll do this two ways depending on your plan:

**Option A — Premium / Business plan (preferred)**

You can change the document root in hPanel.

1. hPanel → **Websites** → Manage → **Advanced** → **General**
2. Find **Public Web Document Root** and change it from:
   ```
   public_html
   ```
   to:
   ```
   public_html/exam_management/public
   ```
3. Now Hostinger serves files from your Laravel `public/` folder, and everything else (`app/`, `.env`, `storage/`) is private.

**Option B — Single Web Hosting (no document root setting)**

You can't move the document root, so use this layout:

```
~/public_html/
    ├── (Laravel root files: app/, config/, vendor/, .env, etc.)
    └── public/             ← we'll trick Apache to use this via .htaccess
```

Add this `.htaccess` at `~/public_html/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

This redirects all incoming requests to `public/` so Laravel's actual entry point (`public/index.php`) handles them — while the rest of the project sits one level above where the public can't reach.

---

### Step 2 — Create FTP account in hPanel

1. hPanel → **Files** → **FTP Accounts**
2. Click **Create FTP Account**:
   - **Username**: `deploy@your-domain.com` (or anything)
   - **Password**: use the Hostinger generator (long random)
   - **Directory**: Set to root (`/`) so the workflow can access both `public_html/` and `exam_management/`. If you can only set per-domain, point it at `/domains/your-domain.com/`.
3. **Save the password** — you'll paste it into GitHub secrets next.

While you're here, note the **FTP server** address shown (something like `ftp.your-domain.com` or `145.223.xxx.xxx`) and the port (usually **21** for FTPS).

---

### Step 3 — Make sure composer is available on the server

The deploy hook runs `composer install` on Hostinger after each deploy. Composer must be findable.

**Hostinger Premium / Business / Cloud plans** — composer is pre-installed. You can verify:

```bash
ssh u123456@your-host -p 65002
which composer        # should print something like /usr/bin/composer
composer --version
```

If `composer` is found, you're done — skip to Step 4.

**Hostinger Single (no SSH) or composer not found** — install composer.phar once via the File Manager:

1. Download `composer.phar` to your local machine:
   ```bash
   curl -sS https://getcomposer.org/installer | php -- --filename=composer.phar
   ```
2. hPanel → **Files** → **File Manager** → navigate to your **home directory** (one level above `domains/`)
3. Upload `composer.phar` so it sits at `~/composer.phar`

The deploy hook auto-detects all of these locations:
- `composer` on PATH
- `~/composer` and `~/composer.phar`
- `~/exam_management/composer.phar`
- `/usr/local/bin/composer`, `/usr/bin/composer`, `/opt/composer/composer.phar`

If none are found, you'll see a clear error in the deploy log with download instructions.

---

### Step 4 — Create the `.env` file on the server (one-time)

This file is NEVER uploaded by the deploy. Create it manually:

1. hPanel → **Files** → **File Manager** → navigate to `~/domains/your-domain.com/exam_management/`
2. Create a new file named `.env`
3. Paste this template (edit values):

```env
APP_NAME="GBHSS No.1 Skardu"
APP_ENV=production
APP_KEY=                 # leave blank — we'll generate next
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456_exam        # from hPanel → Databases → MySQL
DB_USERNAME=u123456_admin
DB_PASSWORD=your-mysql-password

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=database

# Generate a long random string — used by the deploy hook to authenticate GitHub
APP_DEPLOY_SECRET=replace-with-32-char-random-string-from-keygen
```

4. **Generate APP_KEY**: hPanel → **Advanced** → **PHP Configuration** → switch to PHP 8.3, then either:
   - Use Hostinger's Cron Jobs → "Run Once" with command:
     ```
     cd ~/domains/your-domain.com/exam_management && php artisan key:generate --force
     ```
   - OR (if SSH is available) `ssh` in and run `php artisan key:generate`
   - OR generate one locally with `php artisan key:generate --show` and paste it manually.

---

### Step 5 — Create the database in hPanel

1. hPanel → **Databases** → **MySQL Databases**
2. Create a new database, e.g. `exam`. Hostinger prefixes your username, so it becomes `u123456_exam`.
3. Create a database user with the same password you put in `.env`.
4. **Important**: Hostinger may default to MariaDB 10.x — that works with Laravel 12.

---

### Step 6 — Add GitHub secrets

In your GitHub repo: **Settings → Secrets and variables → Actions → New repository secret**.

Add these 7 secrets:

| Secret name | Value | Where to find |
|---|---|---|
| `FTP_HOST` | `ftp.your-domain.com` or the IP shown in hPanel | hPanel → Files → FTP Accounts |
| `FTP_USER` | Full FTP username (often `deploy@your-domain.com`) | The one you created in Step 2 |
| `FTP_PASSWORD` | The FTP password | From Step 2 |
| `FTP_PORT` | `21` | Hostinger uses port 21 with TLS (FTPS) |
| `FTP_TARGET_DIR` | Path to upload to (see below) | Depends on your Step 1 layout |
| `DEPLOY_HOOK_URL` | `https://your-domain.com/deploy-hook.php` | After first deploy uploads the file |
| `DEPLOY_HOOK_SECRET` | Same string as `APP_DEPLOY_SECRET` in your `.env` | You set both to match |

**`FTP_TARGET_DIR` examples**:
- Option A (Premium plan): `domains/your-domain.com/public_html/exam_management`
- Option B (Single plan):  `public_html`

---

### Step 7 — First deploy

Push to `main`:

```bash
git push origin main
```

Watch the GitHub **Actions** tab — the workflow runs in 2–4 minutes.

The first deploy will take a bit longer because it uploads the entire `vendor/` folder (~50 MB / ~5,000 files). Subsequent deploys only upload changed files (FTP-Deploy-Action keeps a `.ftp-deploy-sync-state.json` on the server to track this).

---

### Step 8 — Run the seed once (production-safe baseline)

After the first deploy succeeds and migrations have run, you need to seed the DDO user + master data ONCE.

Easiest way — use Hostinger's hPanel Cron Jobs:

1. hPanel → **Advanced** → **Cron Jobs** → **Create Cron Job** → **Common settings: "Run once"**
2. Command:
   ```
   cd ~/domains/your-domain.com/exam_management && php artisan db:seed --force
   ```
3. Run it.

You can now log in at `https://your-domain.com/login` with:
- Email: `ddo@exam.com`
- Password: `password`

**Change this password immediately** via Settings → Profile.

---

## Everyday usage

Just push:

```bash
git push origin main
```

The workflow takes care of the rest. Watch progress in your GitHub repo's **Actions** tab.

---

## Manual deploy (skip GitHub)

If GitHub Actions is down, you can build locally and FTP manually:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
# Then drag-drop the project folder via FileZilla / Cyberduck to the same target dir
```

After upload, hit the deploy hook URL in your browser (or `curl`):

```bash
curl -X POST -H "X-Deploy-Secret: <your-secret>" https://your-domain.com/deploy-hook.php
```

---

## Rolling back

The workflow doesn't keep snapshots — but git does:

```bash
git revert <bad-commit>
git push origin main
```

GitHub Actions deploys the reverted state in ~3 minutes.

---

## Troubleshooting

**"550 Failed to change directory"** — `FTP_TARGET_DIR` is wrong. Double-check the absolute path matches what File Manager shows in hPanel.

**Site shows "404 Not Found" after deploy** — Document root isn't pointing at `public/`. Re-check Step 1 (Option A or B).

**Migrations didn't run** — Check `storage/logs/deploy-hook.log` on the server. If you see "AUTH FAILED", `DEPLOY_HOOK_SECRET` doesn't match `APP_DEPLOY_SECRET`. If the URL returned 404, `deploy-hook.php` may have been excluded from upload — check the workflow log.

**"500 Server Error" on the site, but logs are fine** — Permissions. Add this to a one-time cron job:
```bash
chmod -R 775 ~/domains/your-domain.com/exam_management/storage \
              ~/domains/your-domain.com/exam_management/bootstrap/cache
```

**Composer install fails on the server / hook reports "composer not found"** — see Step 3. Either install `composer.phar` to your home directory or contact Hostinger to enable composer. The deploy log under `storage/logs/deploy-hook.log` shows exactly which paths were tried.

**Composer install runs but takes too long** — Hostinger shared plans have memory limits that can slow composer down. The hook's `set_time_limit(180)` gives 3 minutes; if you regularly hit that, your packages list is unusually large. Consider running `composer dump-autoload --classmap-authoritative` once via SSH for faster autoload.

**FTP upload is slow on first deploy** — Should be fast now since `vendor/` is excluded (~500 files instead of ~5,000). If it's still slow, check that `vendor/` doesn't exist locally before push — old workflows may have left it behind:
```bash
git rm -r --cached vendor 2>/dev/null
echo "vendor/" >> .gitignore
git commit -m "exclude vendor from repo"
```

**.env got overwritten** — The workflow excludes `**/.env` so this shouldn't happen. If it did, restore from your backup. Always keep an offline copy of your production `.env`.

**Hook returns "Class 'Illuminate\Foundation\Application' not found"** — `vendor/` didn't upload completely. Re-run the workflow; or manually FTP-upload the `vendor/` folder.

---

## Security notes

- ✅ Workflow uses **FTPS** (port 21 with TLS), not plain FTP — credentials never sent in clear text
- ✅ `.env` is excluded from upload — your DB password stays on the server
- ✅ Deploy hook requires a 32+ character secret in a custom header
- ✅ Hook only accepts POST (not GET — won't be triggered by browser visits)
- ✅ Hook logs every invocation to `storage/logs/deploy-hook.log` for audit
- ⚠️ **Rotate** `APP_DEPLOY_SECRET` and `FTP_PASSWORD` every 6 months
- ⚠️ Never commit `.env`, `deploy-hook.php` is fine to commit (it's protected by the secret)

---

## Files in this repo

| File | Purpose |
|---|---|
| `.github/workflows/deploy.yml` | GitHub Actions FTP deploy workflow |
| `public/deploy-hook.php` | Web endpoint that runs migrations + cache clears |
| `DEPLOY.md` | This file |

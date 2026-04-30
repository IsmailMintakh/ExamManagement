# Deploy to Hostinger via FTP on git push

Auto-deploys on every push to `main` using FTPS upload. No SSH required — works on shared/Premium Hostinger plans.

## How it works

1. You `git push origin main`.
2. GitHub Actions (`.github/workflows/deploy.yml`) builds the project in CI:
   - PHP 8.3 setup + `composer install --no-dev --optimize-autoloader`
   - Node 20 + `npm ci && npm run build` (Vite production assets)
3. GitHub Actions uploads the built project to Hostinger via **FTPS** (encrypted FTP).
4. After upload, GitHub Actions hits a tiny PHP endpoint on your site (`https://your-domain.com/deploy-hook.php`) that runs `migrate --force`, clears caches, restarts queues. The hook is protected by a shared secret so only your workflow can trigger it.
5. Total time: 2–4 minutes.

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

### Step 3 — Create the `.env` file on the server (one-time)

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

### Step 4 — Create the database in hPanel

1. hPanel → **Databases** → **MySQL Databases**
2. Create a new database, e.g. `exam`. Hostinger prefixes your username, so it becomes `u123456_exam`.
3. Create a database user with the same password you put in `.env`.
4. **Important**: Hostinger may default to MariaDB 10.x — that works with Laravel 12.

---

### Step 5 — Add GitHub secrets

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

### Step 6 — First deploy

Push to `main`:

```bash
git push origin main
```

Watch the GitHub **Actions** tab — the workflow runs in 2–4 minutes.

The first deploy will take a bit longer because it uploads the entire `vendor/` folder (~50 MB / ~5,000 files). Subsequent deploys only upload changed files (FTP-Deploy-Action keeps a `.ftp-deploy-sync-state.json` on the server to track this).

---

### Step 7 — Run the seed once (production-safe baseline)

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

**FTP upload is very slow** — First deploy uploads ~5,000 files. Subsequent deploys are fast (only changed files). To speed up the first deploy:
- Use a wired connection
- Use FTPS (encrypted) on port 21, not SFTP — FTPS supports parallel transfers
- Consider committing `vendor/` and `public/build/` once to skip the install step (less ideal — bloats the repo)

**"Connection timed out" during FTP** — Hostinger sometimes throttles long-running FTP sessions. The workflow has a 20-minute timeout; if you hit it, split the deploy by excluding `vendor/` and uploading it separately one time, then re-enabling.

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

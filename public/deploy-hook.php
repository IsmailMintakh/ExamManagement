<?php
/**
 * ============================================================================
 * Post-deploy hook — runs composer install + migrations + cache clears
 * after FTP upload completes.
 *
 * Lives at:  public/deploy-hook.php  →  https://your-domain.com/deploy-hook.php
 * Triggered by: GitHub Actions workflow after FTP upload completes.
 *
 * What it does (in order):
 *   1. Auth check (X-Deploy-Secret header vs APP_DEPLOY_SECRET in .env)
 *   2. composer install --no-dev --optimize-autoloader
 *   3. artisan down (maintenance mode)
 *   4. artisan migrate --force
 *   5. artisan db:seed --force        (production-safe; all seeders are idempotent —
 *                                      use firstOrCreate / count()===0 guards, so
 *                                      re-running just inserts what's missing)
 *   6. artisan storage:link
 *   7. artisan optimize:clear + cache config/routes/views/events
 *   8. artisan queue:restart
 *   9. artisan up
 *
 * Composer detection:
 *   Tries `composer`, `/usr/local/bin/composer`, `/usr/bin/composer`, then
 *   `~/composer.phar`. If none found, returns a clear error.
 *
 * Required server-side setup:
 *   - .env contains APP_DEPLOY_SECRET=<long random string>
 *   - composer is on PATH OR composer.phar exists in user home
 *
 * Required GitHub secrets:
 *   - DEPLOY_HOOK_URL    = https://your-domain.com/deploy-hook.php
 *   - DEPLOY_HOOK_SECRET = same string as APP_DEPLOY_SECRET
 * ============================================================================
 */

declare(strict_types=1);
ignore_user_abort(true);
set_time_limit(180);                 // composer install can be slow on shared hosting

// ─── 1. Method check ──────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Method Not Allowed\n";
    exit;
}

// ─── 2. Bootstrap Laravel just enough to read .env ────────────
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

// ─── 3. Auth check (constant-time compare) ────────────────────
$expected = (string) env('APP_DEPLOY_SECRET', '');
$received = (string) ($_SERVER['HTTP_X_DEPLOY_SECRET'] ?? '');

if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden\n";
    @file_put_contents(
        __DIR__ . '/../storage/logs/deploy-hook.log',
        '[' . date('c') . "] AUTH FAILED from " . ($_SERVER['REMOTE_ADDR'] ?? '?') . "\n",
        FILE_APPEND
    );
    exit;
}

// ─── 4. Setup ─────────────────────────────────────────────────
header('Content-Type: text/plain; charset=utf-8');
$projectRoot = realpath(__DIR__ . '/..');
$log = [];

function logLine(array &$log, string $msg): void
{
    $log[] = $msg;
}

// ─── 5. Find a usable composer binary ─────────────────────────
function findComposer(string $projectRoot): ?string
{
    $candidates = [
        'composer',
        $_SERVER['HOME'] . '/composer.phar',
        $_SERVER['HOME'] . '/composer',
        $projectRoot . '/composer.phar',
        '/usr/local/bin/composer',
        '/usr/bin/composer',
        '/opt/composer/composer.phar',
    ];

    foreach ($candidates as $candidate) {
        // If it's a file path, check it exists and is executable
        if (str_contains($candidate, '/') || str_contains($candidate, DIRECTORY_SEPARATOR)) {
            if (is_file($candidate) && (is_executable($candidate) || str_ends_with($candidate, '.phar'))) {
                // .phar files are run via `php`
                return str_ends_with($candidate, '.phar')
                    ? "php " . escapeshellarg($candidate)
                    : escapeshellarg($candidate);
            }
        } else {
            // Bare command — check via `which` / `command -v`
            $which = trim((string) @shell_exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null'));
            if ($which !== '') return escapeshellarg($which);
        }
    }
    return null;
}

// ─── 6. Run composer install ──────────────────────────────────
logLine($log, '═══ Step 1: composer install ═══');
$composerBin = findComposer($projectRoot);

if ($composerBin === null) {
    logLine($log, '❌ composer not found. Tried: composer, ~/composer.phar, /usr/local/bin/composer, /usr/bin/composer');
    logLine($log, '   Hostinger fix: SSH in once and download:');
    logLine($log, '     curl -sS https://getcomposer.org/installer | php -- --install-dir=$HOME --filename=composer');
    logLine($log, '   Or upload composer.phar via FTP to ~/composer.phar');
} else {
    logLine($log, "Using: $composerBin");
    $cmd = sprintf(
        '%s install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader 2>&1',
        $composerBin
    );

    $cwd = getcwd();
    chdir($projectRoot);
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);
    chdir($cwd);

    foreach ($output as $line) logLine($log, '  ' . $line);
    logLine($log, "  composer exit: {$exitCode}");
    if ($exitCode !== 0) {
        logLine($log, '❌ composer install failed — aborting before running migrations.');
        finish($log);
    }
}
logLine($log, '');

// ─── 7. Run artisan commands ──────────────────────────────────
function runArtisan(array &$log, string $command): void
{
    logLine($log, "▸ artisan {$command}");
    try {
        $exitCode = \Illuminate\Support\Facades\Artisan::call($command);
        $output = trim(\Illuminate\Support\Facades\Artisan::output());
        if ($output !== '') logLine($log, '  ' . str_replace("\n", "\n  ", $output));
        logLine($log, "  exit: {$exitCode}");
    } catch (\Throwable $e) {
        logLine($log, '  ERROR: ' . $e->getMessage());
    }
    logLine($log, '');
}

logLine($log, '═══ Step 2: artisan commands ═══');
runArtisan($log, 'down --retry=15');
runArtisan($log, 'migrate --force');
// Run seeders. Production-safe because every called seeder uses
// firstOrCreate/updateOrCreate or guards on Model::count()===0 / ->exists()
// — so this only inserts data that's missing, never duplicates.
runArtisan($log, 'db:seed --force');
runArtisan($log, 'storage:link');
runArtisan($log, 'optimize:clear');
runArtisan($log, 'config:cache');
runArtisan($log, 'route:cache');
runArtisan($log, 'view:cache');
runArtisan($log, 'event:cache');
runArtisan($log, 'queue:restart');
runArtisan($log, 'up');

finish($log);

// ─── 8. Output + log ──────────────────────────────────────────
function finish(array $log): never
{
    $body = "Deploy hook ran at " . date('c') . "\n"
          . str_repeat('═', 60) . "\n"
          . implode("\n", $log) . "\n";

    echo $body;
    @file_put_contents(__DIR__ . '/../storage/logs/deploy-hook.log', $body, FILE_APPEND);
    exit;
}

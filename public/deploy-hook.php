<?php
/**
 * ============================================================================
 * Post-deploy hook — runs migrations and clears caches after FTP upload.
 *
 * This is the ONLY way to run server-side commands (php artisan migrate,
 * cache:clear, etc.) when you don't have SSH access — common on shared
 * Hostinger plans.
 *
 * Lives at:  public/deploy-hook.php  →  https://your-domain.com/deploy-hook.php
 * Triggered by: GitHub Actions workflow after FTP upload completes.
 *
 * Security:
 *   - Requires X-Deploy-Secret header matching APP_DEPLOY_SECRET in .env
 *   - Returns 403 on any auth failure
 *   - Only accepts POST requests
 *   - Logs every invocation to storage/logs/deploy-hook.log
 *
 * On the server:
 *   1. Add to .env:   APP_DEPLOY_SECRET=<long-random-string>
 *   2. Add to GitHub secrets:
 *        DEPLOY_HOOK_URL    = https://your-domain.com/deploy-hook.php
 *        DEPLOY_HOOK_SECRET = <same string as APP_DEPLOY_SECRET>
 * ============================================================================
 */

declare(strict_types=1);

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
    file_put_contents(
        __DIR__ . '/../storage/logs/deploy-hook.log',
        '[' . date('c') . "] AUTH FAILED from " . ($_SERVER['REMOTE_ADDR'] ?? '?') . "\n",
        FILE_APPEND
    );
    exit;
}

// ─── 4. Run the post-deploy commands ──────────────────────────
header('Content-Type: text/plain; charset=utf-8');
$log = [];

function runArtisan(array &$log, string $command): void
{
    $log[] = "▸ artisan {$command}";
    try {
        $exitCode = \Illuminate\Support\Facades\Artisan::call($command);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $log[] = trim($output);
        $log[] = "  exit: {$exitCode}";
    } catch (\Throwable $e) {
        $log[] = "  ERROR: " . $e->getMessage();
    }
    $log[] = '';
}

runArtisan($log, 'down --retry=15');
runArtisan($log, 'migrate --force');
runArtisan($log, 'storage:link');
runArtisan($log, 'optimize:clear');
runArtisan($log, 'config:cache');
runArtisan($log, 'route:cache');
runArtisan($log, 'view:cache');
runArtisan($log, 'event:cache');
runArtisan($log, 'queue:restart');
runArtisan($log, 'up');

// ─── 5. Output + log ──────────────────────────────────────────
$output = "Deploy hook ran at " . date('c') . "\n"
        . str_repeat('═', 60) . "\n"
        . implode("\n", $log) . "\n";

echo $output;

@file_put_contents(__DIR__ . '/../storage/logs/deploy-hook.log', $output, FILE_APPEND);

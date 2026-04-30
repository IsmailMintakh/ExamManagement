<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=10 : Number of backups to keep}';
    protected $description = 'Create a timestamped backup of the database';

    public function handle(): int
    {
        $connection = config('database.default');
        $db = config("database.connections.{$connection}");
        $timestamp = now()->format('Y-m-d_His');
        $dir = storage_path('app/backups');

        if (!File::isDirectory($dir)) File::makeDirectory($dir, 0755, true);

        $this->info('🔐 Starting database backup...');

        if ($db['driver'] === 'sqlite') {
            $source = $db['database'];
            if (!file_exists($source)) {
                $this->error("SQLite database not found at {$source}");
                return self::FAILURE;
            }
            $dest = "{$dir}/backup_{$timestamp}.sqlite";
            File::copy($source, $dest);
            $this->info("✅ Backup saved: " . basename($dest));
            $size = round(filesize($dest) / 1024, 2);
            $this->line("   Size: {$size} KB");
        } elseif ($db['driver'] === 'mysql') {
            $dest = "{$dir}/backup_{$timestamp}.sql";
            $cmd = sprintf(
                'mysqldump -h%s -P%s -u%s %s %s > %s',
                escapeshellarg($db['host']),
                escapeshellarg($db['port']),
                escapeshellarg($db['username']),
                $db['password'] ? '-p'.escapeshellarg($db['password']) : '',
                escapeshellarg($db['database']),
                escapeshellarg($dest)
            );
            exec($cmd, $output, $returnVar);
            if ($returnVar !== 0) {
                $this->error('mysqldump failed. Is it installed and in PATH?');
                return self::FAILURE;
            }
            $this->info("✅ Backup saved: " . basename($dest));
        } else {
            $this->error("Unsupported driver: {$db['driver']}");
            return self::FAILURE;
        }

        // Rotate old backups
        $keep = (int) $this->option('keep');
        $files = collect(File::files($dir))
            ->sortByDesc(fn ($f) => $f->getMTime());

        if ($files->count() > $keep) {
            $toDelete = $files->skip($keep);
            foreach ($toDelete as $f) {
                File::delete($f->getPathname());
                $this->line("   Deleted old backup: " . $f->getFilename());
            }
            $this->info("🧹 Kept last {$keep} backups.");
        }

        return self::SUCCESS;
    }
}

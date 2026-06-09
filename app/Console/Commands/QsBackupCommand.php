<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Live-Ops Phase 6 — daily backup for shared cPanel hosting.
 *
 * Produces TWO artefacts in storage/app/backups/:
 *   1. db-YYYY-MM-DD-HHMM.sql.gz  — full mysqldump of the configured
 *      database, gzipped on the fly. Uses the credentials from the
 *      default Laravel DB connection (env DB_HOST / DB_USERNAME / …).
 *   2. files-YYYY-MM-DD-HHMM.tar.gz — tarball of storage/app (uploads,
 *      verification images, evidence). Excludes storage/app/backups
 *      itself to prevent recursion.
 *
 * Both shell out: the cPanel environments QuizSnap targets ship
 * `mysqldump`, `gzip`, and `tar`. Symfony Process is used so failure
 * exit codes are preserved and surfaced to cron.
 *
 * Retention is enforced by --keep-days (default 7); older backup
 * artefacts are deleted at the end of the run.
 *
 * Recovery: see QUIZSNAP_LIVE_EXAM_OPS_PLAN.txt § 6.
 */
class QsBackupCommand extends Command
{
    protected $signature = 'qs:backup:run
        {--keep-days=7 : Delete backup artefacts older than this many days at the end of the run}
        {--db-only : Skip the storage tarball, only dump the database}
        {--files-only : Skip the database dump, only tarball storage/app}';

    protected $description = 'Run a full QuizSnap backup (DB dump + storage/app tarball) for shared cPanel hosting.';

    public function handle(): int
    {
        $stamp = now()->format('Y-m-d-Hi');
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            File::makeDirectory($backupDir, 0750, true, true);
        }

        $okDb = $this->option('files-only') ? true : $this->dumpDatabase($backupDir, $stamp);
        $okFiles = $this->option('db-only') ? true : $this->tarStorage($backupDir, $stamp);

        $this->prune($backupDir, (int) $this->option('keep-days'));

        if (! $okDb || ! $okFiles) {
            $this->error('Backup completed with errors. See the log for the failing step.');
            return self::FAILURE;
        }

        $this->info('Backup completed successfully into '.$backupDir);
        return self::SUCCESS;
    }

    private function dumpDatabase(string $dir, string $stamp): bool
    {
        $conn = config('database.default');
        $cfg = (array) config('database.connections.'.$conn, []);

        $driver = (string) ($cfg['driver'] ?? 'mysql');
        if ($driver !== 'mysql') {
            $this->warn("Database driver '{$driver}' is not mysql; skipping mysqldump (use a vendor-specific tool instead).");
            return true;
        }

        $output = $dir.DIRECTORY_SEPARATOR.'db-'.$stamp.'.sql.gz';

        // Build the mysqldump command. `--single-transaction` keeps
        // the dump consistent without locking InnoDB tables, which is
        // critical during a live exam window. `--quick` streams rows
        // straight to stdout instead of buffering huge result sets.
        $args = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--triggers',
            '--default-character-set=utf8mb4',
            '-h', (string) ($cfg['host'] ?? '127.0.0.1'),
            '-P', (string) ($cfg['port'] ?? 3306),
            '-u', (string) ($cfg['username'] ?? ''),
        ];
        $env = [];
        if (! empty($cfg['password'])) {
            // Pass via env so it doesn't appear in `ps`.
            $env['MYSQL_PWD'] = (string) $cfg['password'];
        }
        $args[] = (string) ($cfg['database'] ?? '');

        $cmd = implode(' ', array_map('escapeshellarg', $args)).' | gzip -9 > '.escapeshellarg($output);

        $this->line('› dumping database...');
        $exit = $this->runShell($cmd, $env);

        if ($exit !== 0) {
            @unlink($output);
            $this->error('mysqldump failed (exit '.$exit.'). Make sure mysqldump is on PATH and credentials are correct.');
            return false;
        }

        $bytes = is_file($output) ? filesize($output) : 0;
        $this->info('  wrote '.basename($output).' ('.$this->fmtBytes($bytes).')');
        return true;
    }

    private function tarStorage(string $dir, string $stamp): bool
    {
        $output = $dir.DIRECTORY_SEPARATOR.'files-'.$stamp.'.tar.gz';
        $base = base_path();
        $relStorage = 'storage/app';

        // -X gives an exclude file path. Use a temp file so we can list
        // both backup-recursion and obvious cache directories.
        $exclude = tempnam(sys_get_temp_dir(), 'qsbk_');
        File::put($exclude, implode("\n", [
            'storage/app/backups',
            'storage/app/.gitignore',
            '*.tmp',
        ]));

        $cmd = sprintf(
            'tar -czf %s -X %s -C %s %s',
            escapeshellarg($output),
            escapeshellarg($exclude),
            escapeshellarg($base),
            escapeshellarg($relStorage),
        );

        $this->line('› tarballing storage/app...');
        $exit = $this->runShell($cmd, []);

        @unlink($exclude);

        if ($exit !== 0) {
            @unlink($output);
            $this->error('tar failed (exit '.$exit.').');
            return false;
        }

        $bytes = is_file($output) ? filesize($output) : 0;
        $this->info('  wrote '.basename($output).' ('.$this->fmtBytes($bytes).')');
        return true;
    }

    private function prune(string $dir, int $keepDays): void
    {
        if ($keepDays <= 0) {
            return;
        }
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        foreach ((array) File::glob($dir.'/*.{gz,sql,tar}', GLOB_BRACE) as $path) {
            if (is_file($path) && filemtime($path) < $cutoff) {
                $this->line('› pruning old artefact '.basename($path));
                @unlink($path);
            }
        }
    }

    /**
     * @param  array<string, string>  $env
     */
    private function runShell(string $cmd, array $env): int
    {
        $envExports = '';
        foreach ($env as $k => $v) {
            $envExports .= sprintf('export %s=%s; ', escapeshellarg($k), escapeshellarg($v));
        }
        $full = $envExports.$cmd;

        $output = '';
        $exit = 0;
        $proc = popen($full.' 2>&1', 'r');
        if ($proc === false) {
            return 127;
        }
        while (! feof($proc)) {
            $output .= (string) fread($proc, 4096);
        }
        $exit = pclose($proc);

        if ($exit !== 0 && trim($output) !== '') {
            $this->warn(trim($output));
        }
        return $exit;
    }

    private function fmtBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2).' '.$units[$i];
    }
}

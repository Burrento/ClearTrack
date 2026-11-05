<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin-only database backups: make one now, and download any backup (including the server's
 * nightly and pre-deploy ones) to keep a copy off the server. Restoring stays a manual job.
 */
class BackupController extends Controller
{
    private const KEEP_MANUAL = 10;

    public function index(): View
    {
        $backups = collect(File::glob($this->dir() . '/*.sql.gz'))
            ->map(fn ($path) => ['name' => basename($path), 'size' => filesize($path), 'time' => filemtime($path)])
            ->sortByDesc('time')->values();

        return view('admin.backups', compact('backups'));
    }

    public function store(): RedirectResponse
    {
        $db = config('database.connections.' . config('database.default'));

        // ponytail: the dump is held in memory; fine at ClearTrack's size (a few MB), stream it if it grows past ~100 MB
        $result = Process::timeout(120)
            ->env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])
            ->run([
                config('cleartrack.mysqldump'), '--no-tablespaces', '--single-transaction',
                '-h', (string) ($db['host'] ?? '127.0.0.1'), '-P', (string) ($db['port'] ?? 3306),
                '-u', (string) ($db['username'] ?? 'root'), (string) $db['database'],
            ]);

        if ($result->failed() || $result->output() === '') {
            return back()->with('error', 'The backup failed: ' . (strtok(trim($result->errorOutput()), "\n") ?: 'mysqldump returned nothing.'));
        }

        File::ensureDirectoryExists($this->dir());
        $name = 'manual-' . now()->format('Y-m-d-His') . '.sql.gz';
        File::put($this->dir() . "/{$name}", gzencode($result->output(), 6));

        // Keep the newest few backups made from this page; nightly and pre-deploy ones prune themselves
        collect(File::glob($this->dir() . '/manual-*.sql.gz'))->sortDesc()->slice(self::KEEP_MANUAL)->each(fn ($old) => File::delete($old));

        return back()->with('status', "Backup saved: {$name}. Download it to keep a copy off the server.");
    }

    public function download(string $file): BinaryFileResponse
    {
        // Only plain backup file names, so nothing outside the backup folder can be fetched
        abort_unless(preg_match('/^[\w.-]+\.sql\.gz$/', $file) && is_file($path = $this->dir() . "/{$file}"), 404);

        return response()->download($path);
    }

    private function dir(): string
    {
        return rtrim(config('cleartrack.backup_path'), '/\\');
    }
}

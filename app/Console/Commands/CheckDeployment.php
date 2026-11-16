<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckDeployment extends Command
{
    protected $signature = 'deploy:check {--production : Also require production environment and security settings}';

    protected $description = 'Read-only deployment checks; never sends mail, runs jobs or changes data';

    public function handle(): int
    {
        $failures = 0;
        $check = function (string $label, callable $test) use (&$failures) {
            try {
                $passed = (bool) $test();
            } catch (Throwable) {
                // Connection errors can include credentials/DSNs. Do not print exceptions.
                $passed = false;
            }
            $this->line(($passed ? 'PASS: ' : 'FAIL: ') . $label);
            $failures += $passed ? 0 : 1;
        };

        $check('Supported application encryption key is configured', function () {
            $key = (string) config('app.key');
            $key = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

            return is_string($key) && Encrypter::supported($key, config('app.cipher'));
        });
        $check('Database is reachable', fn () => DB::select('SELECT 1') !== []);
        $check('All packaged migrations have been applied', function () {
            $applied = DB::table('migrations')->pluck('migration')->all();
            $packaged = array_keys(app('migrator')->getMigrationFiles(database_path('migrations')));

            return array_diff($packaged, $applied) === [];
        });
        $check('Request, history, attachment and outbox tables exist', function () {
            foreach (['document_requests', 'document_request_events', 'required_files', 'document_status_emails', 'notifications'] as $table) {
                if (! Schema::hasTable($table)) {
                    return false;
                }
            }

            return true;
        });
        $check('Document storage is private and outside the application public directory', function () {
            $config = config('filesystems.disks.documents');
            $root = realpath($config['root'] ?? '');
            $public = realpath(public_path());
            if (! $root || ! $public || $config['driver'] !== 'local') {
                return false;
            }
            $root = strtolower(str_replace('\\', '/', $root));
            $public = strtolower(str_replace('\\', '/', $public));

            return ! ($config['serve'] ?? false) && ($config['visibility'] ?? null) === 'private'
                && $root !== $public && ! str_starts_with($root, $public . '/');
        });
        $check('All referenced private attachments exist', function () {
            $root = config('filesystems.disks.documents.root');
            if (! is_string($root) || ! is_dir($root)) {
                return false;
            }
            foreach (DB::table('required_files')->select('file_path')->orderBy('id')->cursor() as $file) {
                if (! is_string($file->file_path) || ! str_starts_with($file->file_path, 'request_files/')
                    || str_contains($file->file_path, '..') || str_contains($file->file_path, '\\')
                    || ! is_file($root . '/' . $file->file_path)) {
                    return false;
                }
            }

            return true;
        });
        $check('No legacy proof files remain in either public location', function () {
            return ! $this->hasFiles(config('filesystems.disks.public.root') . '/request_files')
                && ! $this->hasFiles(public_path('storage/request_files'));
        });

        if ($this->option('production')) {
            $check('Production environment and debug disabled', fn () => app()->environment('production') && ! config('app.debug'));
            $check('Application URL uses HTTPS', fn () => parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https');
            $check('Session cookies require HTTPS and HttpOnly', fn () => config('session.secure') === true && config('session.http_only') === true);
            $check('Session and cache persist between processes', fn () => ! in_array(config('session.driver'), ['array', null], true)
                && ! in_array(config('cache.default'), ['array', 'null', null], true));
            $check('Mail uses a delivery transport without log/array fallback', fn () => $this->deliversMail((string) config('mail.default')));
            $check('Mail sender address is configured', fn () => filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false);
            $check('SMTP timeout fits the outbox lease', fn () => (int) config('mail.mailers.smtp.timeout') > 0 && (int) config('mail.mailers.smtp.timeout') < 600);
            $check('Vite build manifest exists and development hot file is absent', fn () => is_file(public_path('build/manifest.json')) && ! is_file(public_path('hot')));
            $check('Runtime storage and cache directories are writable', function () {
                foreach ([storage_path('app/private'), storage_path('framework/views'), storage_path('logs'), base_path('bootstrap/cache')] as $directory) {
                    if (! is_dir($directory) || ! is_writable($directory)) {
                        return false;
                    }
                }

                return true;
            });
        } else {
            $this->warn('Production security settings were not evaluated. Use --production on the production target.');
        }

        $this->warn('Manual verification required: hosting document root, HTTPS, cron execution, SMTP delivery and off-host backup recovery.');
        $this->line('Read-only checks complete; no emails sent or application records changed.');

        return $failures ? self::FAILURE : self::SUCCESS;
    }

    private function hasFiles(string $directory): bool
    {
        if (! is_dir($directory)) {
            return false;
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile() || $file->isLink()) {
                return true;
            }
        }

        return false;
    }

    private function deliversMail(string $name, array $visited = []): bool
    {
        if (in_array($name, $visited, true)) {
            return false;
        }
        $mailer = config('mail.mailers.' . $name, []);
        $transport = $mailer['transport'] ?? null;
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $mailer['mailers'] ?? [];
            foreach ($children as $child) {
                if (! $this->deliversMail($child, [...$visited, $name])) {
                    return false;
                }
            }

            return $children !== [];
        }

        return in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend'], true);
    }
}

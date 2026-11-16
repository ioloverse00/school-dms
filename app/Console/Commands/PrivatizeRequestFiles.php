<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PrivatizeRequestFiles extends Command
{
    protected $signature = 'files:privatize-requests {--apply : Copy, verify and remove legacy public copies; otherwise preview only}';

    protected $description = 'Move legacy request evidence to private storage without changing database paths';

    public function handle(): int
    {
        try {
            $private = Storage::disk('documents');
            $sources = [
                Storage::disk('public'),
                // Shared-host deployments sometimes copy uploads instead of linking storage.
                Storage::build(['driver' => 'local', 'root' => public_path('storage'), 'throw' => true]),
            ];
            $paths = [];

            foreach ($sources as $source) {
                foreach ($source->allFiles('request_files') as $path) {
                    if (! str_starts_with($path, 'request_files/') || str_contains($path, '..') || str_contains($path, '\\')) {
                        throw new RuntimeException('Unexpected legacy path.');
                    }
                    $paths[$path] = true;
                }
            }

            // Check every source/destination before copying or deleting anything.
            foreach (array_keys($paths) as $path) {
                $expected = $private->exists($path) ? $this->digest($private, $path) : null;
                foreach ($sources as $source) {
                    if ($source->exists($path)) {
                        $actual = $this->digest($source, $path);
                        if ($expected !== null && $actual !== $expected) {
                            throw new RuntimeException('Conflicting file contents.');
                        }
                        $expected = $actual;
                    }
                }
            }

            $this->info(count($paths) . ' legacy request file(s) found.');
            if (! $this->option('apply')) {
                $this->info('Preview only. Use --apply during maintenance after backing up uploads.');

                return self::SUCCESS;
            }

            // Verify the entire batch before beginning public-copy cleanup.
            foreach (array_keys($paths) as $path) {
                foreach ($sources as $source) {
                    if (! $source->exists($path)) {
                        continue;
                    }
                    if (! $private->exists($path)) {
                        $staged = '.request-file-migration/' . bin2hex(random_bytes(16));
                        $stream = $source->readStream($path);
                        if (! is_resource($stream)) {
                            throw new RuntimeException('Unable to read legacy file.');
                        }
                        try {
                            if (! $private->put($staged, $stream)) {
                                throw new RuntimeException('Unable to write private file.');
                            }
                            if ($this->digest($private, $staged) !== $this->digest($source, $path)) {
                                throw new RuntimeException('Staged copy verification failed.');
                            }
                            if ($private->exists($path)) {
                                throw new RuntimeException('Destination changed during migration.');
                            }
                            if (! $private->move($staged, $path)) {
                                throw new RuntimeException('Unable to finalize private file.');
                            }
                        } finally {
                            fclose($stream);
                            // Failed partial writes never become downloadable final files.
                            $private->delete($staged);
                        }
                    }
                    if ($this->digest($private, $path) !== $this->digest($source, $path)) {
                        throw new RuntimeException('Private copy verification failed.');
                    }
                }
            }

            foreach (array_keys($paths) as $path) {
                foreach ($sources as $source) {
                    if (! $source->exists($path)) {
                        continue;
                    }
                    // Recheck before deletion. Retrying also completes partial cleanup safely.
                    if ($this->digest($private, $path) !== $this->digest($source, $path)) {
                        throw new RuntimeException('Source changed during migration.');
                    }
                    if (! $source->delete($path)) {
                        throw new RuntimeException('Public copy cleanup failed.');
                    }
                }
            }

            $this->info('Private copies verified; public copies removed. Database paths are unchanged.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Storage exceptions can contain sensitive filenames or local paths.
            $this->error('Migration stopped. Review storage access or conflicting contents; retain backups before retrying.');

            return self::FAILURE;
        }
    }

    private function digest(FilesystemAdapter $disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read file for verification.');
        }
        try {
            $hash = hash_init('sha256');
            if (hash_update_stream($hash, $stream) === false) {
                throw new RuntimeException('Unable to hash file.');
            }

            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }
}

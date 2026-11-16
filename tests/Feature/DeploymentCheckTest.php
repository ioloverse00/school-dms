<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeploymentCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        Storage::fake('public');
        config(['filesystems.disks.documents.root' => Storage::disk('documents')->path('')]);
        config(['filesystems.disks.public.root' => Storage::disk('public')->path('')]);
        $this->app->usePublicPath(storage_path('framework/testing/deployment-public'));
        if (! is_dir(public_path())) {
            mkdir(public_path(), 0700, true);
        }
    }

    public function test_local_preflight_passes_without_sending_mail_or_changing_database(): void
    {
        Mail::fake();
        $this->artisan('deploy:check')->expectsOutputToContain('PASS: All packaged migrations have been applied')
            ->expectsOutputToContain('Manual verification required')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('document_requests', 0);
        $this->assertDatabaseCount('document_status_emails', 0);
        $this->assertDirectoryDoesNotExist(public_path('storage'));
    }

    public function test_preflight_detects_missing_migrations_without_running_them(): void
    {
        \Illuminate\Support\Facades\DB::table('migrations')->where('migration', '2026_11_11_000002_create_document_request_events')->delete();
        $this->artisan('deploy:check')->expectsOutputToContain('FAIL: All packaged migrations have been applied')->assertFailed();
        $this->assertDatabaseMissing('migrations', ['migration' => '2026_11_11_000002_create_document_request_events']);
    }

    public function test_preflight_detects_exposed_proofs_without_deleting_or_copying_them(): void
    {
        Storage::disk('public')->put('request_files/proof.pdf', 'sensitive proof');
        $this->artisan('deploy:check')->expectsOutputToContain('FAIL: No legacy proof files remain')->assertFailed();
        $this->assertSame('sensitive proof', Storage::disk('public')->get('request_files/proof.pdf'));
        $this->assertFalse(Storage::disk('documents')->exists('request_files/proof.pdf'));
    }

    public function test_preflight_detects_missing_private_root_without_creating_it(): void
    {
        $root = storage_path('framework/testing/absent-' . bin2hex(random_bytes(8)));
        config(['filesystems.disks.documents.root' => $root]);
        $this->artisan('deploy:check')->expectsOutputToContain('FAIL: All referenced private attachments exist')->assertFailed();
        $this->assertDirectoryDoesNotExist($root);
    }

    public function test_production_preflight_rejects_local_settings_and_capture_mail(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://localhost', 'session.secure' => false, 'mail.default' => 'array']);
        $this->artisan('deploy:check --production')
            ->expectsOutputToContain('FAIL: Production environment and debug disabled')
            ->expectsOutputToContain('FAIL: Application URL uses HTTPS')
            ->expectsOutputToContain('FAIL: Session cookies require HTTPS')
            ->expectsOutputToContain('FAIL: Mail uses a delivery transport without log/array fallback')->assertFailed();
    }

    public function test_production_preflight_rejects_smtp_with_log_fallback(): void
    {
        config(['mail.default' => 'failover', 'mail.mailers.failover' => ['transport' => 'failover', 'mailers' => ['smtp', 'log']]]);
        $this->artisan('deploy:check --production')->expectsOutputToContain('FAIL: Mail uses a delivery transport without log/array fallback')->assertFailed();
    }

    public function test_invalid_key_is_not_printed_in_diagnostic_output(): void
    {
        config(['app.key' => 'do-not-print-this-secret']);
        $this->artisan('deploy:check')->expectsOutputToContain('FAIL: Supported application encryption key')->assertFailed();
        $this->assertStringNotContainsString('do-not-print-this-secret', \Illuminate\Support\Facades\Artisan::output());
    }

    public function test_production_settings_can_pass_without_connecting_to_smtp(): void
    {
        Mail::fake();
        $this->app->instance('env', 'production');
        config([
            'app.debug' => false, 'app.url' => 'https://school.example.test',
            'session.secure' => true, 'session.http_only' => true, 'session.driver' => 'database',
            'cache.default' => 'database', 'mail.default' => 'smtp', 'mail.from.address' => 'school@example.test',
            'mail.mailers.smtp.timeout' => 30,
        ]);
        foreach ([public_path('build'), storage_path('app/private'), storage_path('framework/views'), storage_path('logs')] as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
        }
        file_put_contents(public_path('build/manifest.json'), '{}');
        $exitCode = \Illuminate\Support\Facades\Artisan::call('deploy:check', ['--production' => true]);
        $this->assertSame(0, $exitCode, \Illuminate\Support\Facades\Artisan::output());
        Mail::assertNothingSent();
    }
}

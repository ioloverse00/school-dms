<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrivateRequestFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('documents');
        $this->app->usePublicPath(storage_path('framework/testing/web-' . uniqid()));
    }

    public static function viewers(): array
    {
        return [
            'owner' => ['student', true, 200],
            'other student' => ['student', false, 403],
            'admin' => ['admin', false, 200],
            'registrar' => ['registrar', false, 200],
            'roleless' => [null, false, 403],
        ];
    }

    #[DataProvider('viewers')]
    public function test_only_owner_and_staff_can_read_private_proofs(?string $role, bool $ownsRequest, int $status): void
    {
        $owner = User::factory()->create();
        $viewer = $ownsRequest ? $owner : User::factory()->create();
        if ($role !== null) {
            Role::findOrCreate($role);
            $viewer->assignRole($role);
        }
        $file = $this->proof($owner);
        Storage::disk('documents')->put($file->file_path, 'private proof bytes');

        $response = $this->actingAs($viewer)->get(route('files.view', $file));
        $response->assertStatus($status);
        if ($status === 200) {
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertSame('private proof bytes', $response->streamedContent());
        }
    }

    public function test_guests_cannot_download_proofs(): void
    {
        $file = $this->proof(User::factory()->create());
        $this->get(route('files.view', $file))->assertRedirect(route('login'));
    }

    public function test_download_does_not_fall_back_to_public_storage(): void
    {
        $owner = User::factory()->create();
        Role::findOrCreate('student');
        $owner->assignRole('student');
        $file = $this->proof($owner);
        Storage::disk('public')->put($file->file_path, 'legacy public proof');

        $this->actingAs($owner)->get(route('files.view', $file))->assertNotFound();
    }

    public function test_migration_preview_does_not_change_files(): void
    {
        Storage::disk('public')->put('request_files/proof.pdf', 'legacy proof');
        $this->artisan('files:privatize-requests')->assertSuccessful();
        Storage::disk('public')->assertExists('request_files/proof.pdf');
        Storage::disk('documents')->assertMissing('request_files/proof.pdf');
    }

    public function test_migration_moves_verified_copies_from_both_roots_and_can_be_repeated(): void
    {
        $web = Storage::build(['driver' => 'local', 'root' => public_path('storage')]);
        Storage::disk('public')->put('request_files/proof.pdf', 'legacy proof');
        $web->put('request_files/proof.pdf', 'legacy proof');
        $web->put('request_files/copied.pdf', 'copied proof');
        Storage::disk('public')->put('unrelated.txt', 'leave alone');

        $this->artisan('files:privatize-requests', ['--apply' => true])->assertSuccessful();
        $this->assertSame('legacy proof', Storage::disk('documents')->get('request_files/proof.pdf'));
        $this->assertSame('copied proof', Storage::disk('documents')->get('request_files/copied.pdf'));
        Storage::disk('public')->assertMissing('request_files/proof.pdf');
        $this->assertFalse($web->exists('request_files/proof.pdf'));
        $this->assertFalse($web->exists('request_files/copied.pdf'));
        Storage::disk('public')->assertExists('unrelated.txt');
        $this->artisan('files:privatize-requests', ['--apply' => true])->assertSuccessful();
    }

    public function test_conflict_retains_all_public_sources_and_does_not_overwrite_private_data(): void
    {
        Storage::disk('public')->put('request_files/first.pdf', 'first');
        Storage::disk('public')->put('request_files/conflict.pdf', 'public version');
        Storage::disk('documents')->put('request_files/conflict.pdf', 'different private version');

        $this->artisan('files:privatize-requests', ['--apply' => true])->assertFailed();
        Storage::disk('public')->assertExists('request_files/first.pdf');
        Storage::disk('public')->assertExists('request_files/conflict.pdf');
        $this->assertSame('different private version', Storage::disk('documents')->get('request_files/conflict.pdf'));
    }

    public function test_conflicting_public_roots_are_retained_for_review(): void
    {
        Storage::disk('public')->put('request_files/proof.pdf', 'version one');
        $web = Storage::build(['driver' => 'local', 'root' => public_path('storage')]);
        $web->put('request_files/proof.pdf', 'version two');

        $this->artisan('files:privatize-requests', ['--apply' => true])->assertFailed();
        Storage::disk('public')->assertExists('request_files/proof.pdf');
        $this->assertTrue($web->exists('request_files/proof.pdf'));
        Storage::disk('documents')->assertMissing('request_files/proof.pdf');
    }

    private function proof(User $owner)
    {
        $type = DocumentType::create(['name' => 'Transcript', 'category' => 'academic', 'weight' => 4]);
        $request = DocumentRequest::create([
            'request_id' => 'REQ-PRIVATE-001',
            'user_id' => $owner->id,
            'document_type_id' => $type->id,
            'purpose' => 'Test',
            'status' => 'pending_verification',
        ]);

        return $request->requiredFiles()->create([
            'file_type' => 'proof_of_payment',
            'file_path' => 'request_files/proof.pdf',
            'original_name' => 'proof.pdf',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\RequiredFile;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RequestReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private DocumentType $type;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        foreach (['admin', 'registrar', 'student'] as $role) {
            Role::findOrCreate($role);
        }
        $this->student = User::factory()->create(['student_type' => 'alumni', 'year_graduated' => '2020']);
        $this->student->assignRole('student');
        $this->type = DocumentType::create(['name' => 'Transcript', 'category' => 'academic', 'weight' => 4]);
        $this->token = (string) Str::uuid();
        $this->actingAs($this->student);
    }

    public function test_failed_storage_does_not_create_request_or_update_profile(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('documents')->andReturn($disk);

        $this->post(route('student.requests.store'), $this->payload())->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('document_requests', 0);
        $this->assertDatabaseCount('required_files', 0);
        $this->assertSame('2020', $this->student->fresh()->year_graduated);
    }

    public function test_attachment_database_failure_rolls_back_profile_request_and_upload(): void
    {
        RequiredFile::creating(function () { throw new RuntimeException('Simulated insert failure'); });
        try {
            $this->post(route('student.requests.store'), $this->payload())->assertSessionHasErrors('submission');
        } finally {
            RequiredFile::flushEventListeners();
        }
        $this->assertDatabaseCount('document_requests', 0);
        $this->assertSame('2020', $this->student->fresh()->year_graduated);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_staff_notification_failure_rolls_back_the_entire_submission(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('admin');
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Simulated notification failure'));

        $this->post(route('student.requests.store'), $this->payload())->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('document_requests', 0);
        $this->assertDatabaseCount('required_files', 0);
        $this->assertSame('2020', $this->student->fresh()->year_graduated);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_identical_retry_creates_only_one_request_upload_and_notification(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('admin');
        $this->post(route('student.requests.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('student.requests.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseCount('document_requests', 1);
        $this->assertDatabaseCount('required_files', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('document_request_events', 1);
        $this->assertDatabaseHas('document_request_events', ['action' => 'submitted', 'actor_id' => $this->student->id]);
        $this->assertCount(1, Storage::disk('documents')->allFiles());
    }

    public function test_reusing_a_token_for_different_fields_is_rejected(): void
    {
        $this->post(route('student.requests.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('student.requests.store'), array_replace($this->payload(), ['purpose' => 'Different request']))
            ->assertSessionHasErrors('submission_token');
        $this->assertDatabaseCount('document_requests', 1);
        $this->assertCount(1, Storage::disk('documents')->allFiles());
    }

    public function test_submission_limits_reject_invalid_fields_without_side_effects(): void
    {
        $this->post(route('student.requests.store'), array_replace($this->payload(), [
            'copies' => config('document_requests.max_copies') + 1,
            'purpose' => str_repeat('x', 256),
            'year_graduated' => date('Y') + 1,
        ]))->assertSessionHasErrors(['copies', 'purpose', 'year_graduated']);
        $this->assertDatabaseCount('document_requests', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_ineligible_request_does_not_change_the_profile(): void
    {
        $this->student->update(['student_type' => 'regular']);
        $this->type->update(['alumni_only' => true]);
        $this->post(route('student.requests.store'), $this->payload())->assertSessionHasErrors('document_type_id');
        $this->assertDatabaseCount('document_requests', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_retry_after_rollback_updates_the_profile_and_creates_one_request(): void
    {
        $failOnce = true;
        RequiredFile::creating(function () use (&$failOnce) {
            if ($failOnce) {
                $failOnce = false;
                throw new RuntimeException('First insert failed');
            }
        });
        try {
            $this->post(route('student.requests.store'), $this->payload())->assertSessionHasErrors('submission');
            $this->post(route('student.requests.store'), $this->payload())->assertRedirect(route('student.requests.index'));
        } finally {
            RequiredFile::flushEventListeners();
        }
        $this->assertSame('2021', $this->student->fresh()->year_graduated);
        $this->assertDatabaseCount('document_requests', 1);
        $this->assertDatabaseCount('required_files', 1);
        $this->assertCount(1, Storage::disk('documents')->allFiles());
    }

    public function test_invalid_upload_shape_returns_validation_error_not_a_server_error(): void
    {
        $this->postJson(route('student.requests.store'), array_replace($this->payload(), ['proof_of_payment' => 'not a file']))
            ->assertUnprocessable()->assertJsonValidationErrors('proof_of_payment');
        $this->assertDatabaseCount('document_requests', 0);
    }

    private function payload(): array
    {
        return [
            'submission_token' => $this->token,
            'document_type_id' => $this->type->id,
            'purpose' => 'Employment', 'copies' => 1, 'urgency' => 'regular', 'year_graduated' => '2021',
            'proof_of_payment' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ];
    }
}

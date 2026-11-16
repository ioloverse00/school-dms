<?php

namespace Tests\Feature;

use App\Mail\DocumentStatusUpdated;
use App\Models\DocumentRequest;
use App\Models\DocumentStatusEmail;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StatusEmailReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public static function staffRoles(): array
    {
        return [['admin'], ['registrar']];
    }

    #[DataProvider('staffRoles')]
    public function test_status_update_commits_once_without_contacting_smtp(string $role): void
    {
        Mail::fake();
        $request = $this->requestForStaff($role);
        foreach ([1, 2] as $attempt) {
            $this->put(route($role . '.requests.update', $request->id), ['edit_token' => \App\Support\RequestEditToken::for($request->fresh()), 'status' => 'processing', 'remarks' => 'Review complete'])
                ->assertSessionHasNoErrors()->assertRedirect(route($role . '.requests.show', $request->id));
        }
        $this->assertSame('processing', $request->fresh()->status);
        $this->assertDatabaseCount('document_status_emails', 1);
        $this->assertDatabaseCount('notifications', 1);
        Mail::assertNothingSent();
    }

    public function test_smtp_failure_is_retryable_without_repeating_status_or_in_app_notification(): void
    {
        $request = $this->requestForStaff('admin');
        $this->put(route('admin.requests.update', $request->id), ['edit_token' => \App\Support\RequestEditToken::for($request->fresh()), 'status' => 'processing', 'remarks' => 'Review in progress. Please wait for the next update.']);
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        $this->artisan('notifications:deliver-email')->assertFailed();
        $email = DocumentStatusEmail::firstOrFail();
        $this->assertSame(1, $email->attempts);
        $this->assertNull($email->sent_at);
        $this->assertNull($email->claimed_at);
        $this->assertSame('processing', $request->fresh()->status);
        $this->assertDatabaseCount('notifications', 1);

        Mail::shouldReceive('getDefaultDriver')->andReturn('array');
        Mail::fake();
        $this->travel(3)->minutes();
        $this->artisan('notifications:deliver-email')->assertSuccessful();
        $this->artisan('notifications:deliver-email')->assertSuccessful();
        Mail::assertSent(DocumentStatusUpdated::class, 1);
        $this->assertNotNull($email->fresh()->sent_at);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_email_uses_committed_snapshot_instead_of_later_mutable_status(): void
    {
        Mail::fake();
        $request = $this->requestForStaff('admin');
        $this->put(route('admin.requests.update', $request->id), ['edit_token' => \App\Support\RequestEditToken::for($request->fresh()), 'status' => 'processing', 'remarks' => 'Original remark']);
        $request->update(['status' => 'completed', 'remarks' => 'Later remark']);

        $this->artisan('notifications:deliver-email')->assertSuccessful();
        Mail::assertSent(DocumentStatusUpdated::class, fn ($mail) =>
            $mail->documentRequest->status === 'processing' && $mail->documentRequest->remarks === 'Original remark'
        );
    }

    public function test_outbox_failure_rolls_back_status_and_database_notification(): void
    {
        $request = $this->requestForStaff('admin');
        $this->withoutExceptionHandling();
        DocumentStatusEmail::creating(function () { throw new RuntimeException('Simulated outbox failure'); });
        try {
            $this->put(route('admin.requests.update', $request->id), ['edit_token' => \App\Support\RequestEditToken::for($request->fresh()), 'status' => 'processing', 'remarks' => 'Review in progress. Please wait for the next update.']);
            $this->fail('The simulated write failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated outbox failure', $exception->getMessage());
        } finally {
            DocumentStatusEmail::flushEventListeners();
        }
        $this->assertSame('pending_verification', $request->fresh()->status);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('document_status_emails', 0);
    }

    public function test_an_active_claim_prevents_another_delivery_process_from_sending(): void
    {
        Mail::fake();
        $request = $this->requestForStaff('admin');
        $this->put(route('admin.requests.update', $request->id), ['edit_token' => \App\Support\RequestEditToken::for($request->fresh()), 'status' => 'processing', 'remarks' => 'Review in progress. Please wait for the next update.']);
        DocumentStatusEmail::query()->update(['claimed_at' => now(), 'claim_token' => (string) \Illuminate\Support\Str::uuid()]);
        $this->artisan('notifications:deliver-email')->assertSuccessful();
        Mail::assertNothingSent();
        $this->travel(11)->minutes();
        $this->artisan('notifications:deliver-email')->assertSuccessful();
        Mail::assertSent(DocumentStatusUpdated::class, 1);
    }

    public function test_newer_emails_wait_for_older_deferred_events(): void
    {
        Mail::fake();
        $request = $this->requestForStaff('admin');
        $this->put(route('admin.requests.update', $request->id), ['edit_token' => \App\Support\RequestEditToken::for($request->fresh()), 'status' => 'processing', 'remarks' => 'Review in progress. Please wait for the next update.']);
        $first = DocumentStatusEmail::firstOrFail();
        $first->update(['available_at' => now()->addHour()]);
        $this->put(route('admin.requests.update', $request->id), ['edit_token' => \App\Support\RequestEditToken::for($request->fresh()), 'status' => 'completed', 'remarks' => 'Review in progress. Please wait for the next update.']);

        $this->artisan('notifications:deliver-email')->assertSuccessful();
        Mail::assertNothingSent();
        $this->travel(61)->minutes();
        $this->artisan('notifications:deliver-email')->assertSuccessful();
        Mail::assertSent(DocumentStatusUpdated::class, 1);
        $this->artisan('notifications:deliver-email')->assertSuccessful();
        Mail::assertSent(DocumentStatusUpdated::class, 2);
    }

    private function requestForStaff(string $role): DocumentRequest
    {
        Role::findOrCreate($role);
        $staff = User::factory()->create();
        $staff->assignRole($role);
        $this->actingAs($staff);
        $student = User::factory()->create();
        $type = DocumentType::create(['name' => 'Transcript', 'category' => 'academic', 'weight' => 4]);

        return DocumentRequest::create([
            'request_id' => 'REQ-EMAIL-001', 'user_id' => $student->id, 'document_type_id' => $type->id,
            'purpose' => 'Employment', 'status' => 'pending_verification', 'remarks' => 'Review in progress. Please wait for the next update.',
        ]);
    }
}

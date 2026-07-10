<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Notifications\NewDocumentRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NewDocumentRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_are_notified_when_student_submits_document_request(): void
    {
        Notification::fake();
        Storage::fake('public');

        $admin = $this->userWithRole('admin');
        $registrar = $this->userWithRole('registrar');
        $student = $this->userWithRole('student', [
            'student_id' => '210113570',
            'student_type' => 'regular',
        ]);
        $documentType = DocumentType::create([
            'name' => 'Certificate of Enrollment',
            'category' => 'academic',
            'weight' => 2,
        ]);

        $response = $this->actingAs($student)->post(route('student.requests.store'), [
            'document_type_id' => $documentType->id,
            'purpose' => 'Enrollment verification',
            'copies' => 1,
            'urgency' => 'regular',
            'proof_of_payment' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect(route('student.requests.index'));

        $documentRequest = DocumentRequest::firstOrFail();

        Notification::assertSentTo($admin, NewDocumentRequestNotification::class, function (NewDocumentRequestNotification $notification, array $channels, object $notifiable) use ($documentRequest) {
            return $notification->toDatabase($notifiable)['url'] === route('admin.requests.show', $documentRequest->id);
        });

        Notification::assertSentTo($registrar, NewDocumentRequestNotification::class, function (NewDocumentRequestNotification $notification, array $channels, object $notifiable) use ($documentRequest) {
            return $notification->toDatabase($notifiable)['url'] === route('registrar.requests.show', $documentRequest->id);
        });
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate($role);

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}

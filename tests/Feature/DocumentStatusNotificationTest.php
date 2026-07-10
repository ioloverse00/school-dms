<?php

namespace Tests\Feature;

use App\Mail\DocumentStatusUpdated;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_receives_notification_linking_to_request_show_page(): void
    {
        Notification::fake();
        Mail::fake();

        $admin = $this->userWithRole('admin');
        $student = $this->userWithRole('student', [
            'student_id' => '210113570',
            'student_type' => 'regular',
        ]);
        $documentType = DocumentType::create([
            'name' => 'Certificate of Enrollment',
            'category' => 'academic',
            'weight' => 2,
        ]);
        $documentRequest = DocumentRequest::create([
            'request_id' => 'REQ-2026-ABCDE',
            'user_id' => $student->id,
            'document_type_id' => $documentType->id,
            'purpose' => 'Enrollment verification',
            'copies' => 1,
            'urgency' => 'regular',
            'student_category' => 'regular',
            'priority_score' => 10,
            'status' => 'pending_verification',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.requests.update', $documentRequest->id), [
            'status' => 'processing',
            'remarks' => 'Now processing',
        ]);

        $response->assertRedirect(route('admin.requests.show', $documentRequest->id));

        Notification::assertSentTo($student, DocumentStatusNotification::class, function (DocumentStatusNotification $notification, array $channels, object $notifiable) use ($documentRequest) {
            return $notification->toDatabase($notifiable)['url'] === route('student.requests.show', $documentRequest->id);
        });
    }

    public function test_notification_read_route_redirects_to_payload_url(): void
    {
        Mail::fake();

        $admin = $this->userWithRole('admin');
        $student = $this->userWithRole('student', [
            'student_id' => '210113570',
            'student_type' => 'regular',
        ]);
        $documentType = DocumentType::create([
            'name' => 'Certificate of Enrollment',
            'category' => 'academic',
            'weight' => 2,
        ]);
        $documentRequest = DocumentRequest::create([
            'request_id' => 'REQ-2026-XYZAB',
            'user_id' => $student->id,
            'document_type_id' => $documentType->id,
            'purpose' => 'Enrollment verification',
            'copies' => 1,
            'urgency' => 'regular',
            'student_category' => 'regular',
            'priority_score' => 10,
            'status' => 'pending_verification',
        ]);

        $this->actingAs($admin)->put(route('admin.requests.update', $documentRequest->id), [
            'status' => 'completed',
            'remarks' => 'Done',
        ]);

        $notification = $student->notifications()->latest()->firstOrFail();

        $response = $this->actingAs($student)->get(route('notifications.read', $notification->id));

        $response->assertRedirect(route('student.requests.show', $documentRequest->id));
        $this->assertNotNull($student->notifications()->find($notification->id)->read_at);
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

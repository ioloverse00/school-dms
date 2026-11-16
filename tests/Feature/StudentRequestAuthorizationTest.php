<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentRequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function archiveStates(): array
    {
        return ['active' => [false], 'archived' => [true]];
    }

    #[DataProvider('archiveStates')]
    public function test_students_cannot_view_another_students_request(bool $archived): void
    {
        $viewer = $this->student();
        $owner = $this->student();
        $request = $this->documentRequest($owner, $archived);

        $this->actingAs($viewer)
            ->get(route('student.requests.show', $request->id))
            ->assertNotFound();
    }

    #[DataProvider('archiveStates')]
    public function test_students_can_view_their_own_requests(bool $archived): void
    {
        $owner = $this->student();
        $request = $this->documentRequest($owner, $archived);

        $this->actingAs($owner)
            ->get(route('student.requests.show', $request->id))
            ->assertOk()
            ->assertViewHas('documentRequest', fn (DocumentRequest $shown) => $shown->is($request));
    }

    private function student(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('student');
        $user = User::factory()->create();
        $user->assignRole('student');

        return $user;
    }

    private function documentRequest(User $owner, bool $archived): DocumentRequest
    {
        $type = DocumentType::create([
            'name' => 'Transcript',
            'category' => 'academic',
            'weight' => 4,
        ]);

        return DocumentRequest::create([
            'request_id' => 'REQ-AUTH-001',
            'user_id' => $owner->id,
            'document_type_id' => $type->id,
            'purpose' => 'Private request',
            'status' => 'pending_verification',
            'archived_at' => $archived ? now() : null,
        ]);
    }
}

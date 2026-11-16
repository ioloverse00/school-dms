<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\RecordRequestEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountDeletionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public static function deletionPaths(): array
    {
        return [['admin'], ['registrar'], ['self']];
    }

    #[DataProvider('deletionPaths')]
    public function test_requests_history_and_private_files_survive_blocked_deletion(string $path): void
    {
        Storage::fake('documents');
        $student = $this->user(['student']);
        $request = $this->requestFor($student);
        RecordRequestEvent::record($request, 'submitted', $student);
        Storage::disk('documents')->put('request_files/proof.pdf', 'private proof');
        DB::table('required_files')->insert([
            'document_request_id' => $request->id, 'file_type' => 'proof_of_payment',
            'file_path' => 'request_files/proof.pdf', 'original_name' => 'proof.pdf',
        ]);
        $before = $student->fresh()->getAttributes();
        if ($path === 'self') {
            $this->actingAs($student)->from('/profile')->delete('/profile', ['password' => 'password'])
                ->assertRedirect('/profile')->assertSessionHasErrorsIn('userDeletion', 'account');
            $this->assertAuthenticatedAs($student);
            $this->get('/profile')->assertSee('This account has document requests');
        } else {
            $staff = $this->user([$path]);
            $this->actingAs($staff)->from(route($path.'.students.index'))
                ->delete(route($path.'.students.destroy', $student->id))
                ->assertRedirect(route($path.'.students.index'))->assertSessionHasErrors('account');
            $this->get(route($path.'.students.index'))->assertSee('This account has document requests');
        }
        $this->assertSame($before, $student->fresh()->getAttributes());
        $this->assertDatabaseHas('document_requests', ['id' => $request->id]);
        $this->assertDatabaseCount('document_request_events', 1);
        $this->assertDatabaseCount('required_files', 1);
        Storage::disk('documents')->assertExists('request_files/proof.pdf');
    }

    public function test_last_admin_cannot_self_delete_but_can_leave_when_another_admin_exists(): void
    {
        $admin = $this->user(['admin']);
        $this->actingAs($admin)->from('/profile')->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'account');
        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh());
        $other = $this->user(['admin']);
        $this->delete('/profile', ['password' => 'password'])->assertRedirect('/')->assertSessionHasNoErrors();
        $this->assertGuest();
        $this->assertNull($admin->fresh());
        $this->assertNotNull($other->fresh());
    }

    public function test_staff_with_recorded_actions_cannot_be_deleted_by_admin_or_self(): void
    {
        $admin = $this->user(['admin']);
        $registrar = $this->user(['registrar']);
        $request = $this->requestFor($this->user(['student']));
        RecordRequestEvent::record($request, 'status_changed', $registrar);
        $this->actingAs($admin)->delete(route('admin.registrars.destroy', $registrar->id))
            ->assertSessionHasErrors('account');
        $this->actingAs($registrar)->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'account');
        $this->assertAuthenticatedAs($registrar);
        $this->assertDatabaseHas('document_request_events', ['actor_id' => $registrar->id]);
        $this->assertNotNull($registrar->fresh());
    }

    public static function protectedRegistrarTargets(): array
    {
        return [[['admin']], [['student']], [[]], [['registrar', 'admin']], [['registrar', 'student']]];
    }

    #[DataProvider('protectedRegistrarTargets')]
    public function test_registrar_management_rejects_other_account_types(array $roles): void
    {
        $admin = $this->user(['admin']);
        $target = $this->user($roles);
        $before = $target->fresh()->getAttributes();
        $this->actingAs($admin)->get(route('admin.registrars.edit', $target->id))->assertNotFound();
        $this->put(route('admin.registrars.update', $target->id), [
            'name' => 'Changed', 'email' => 'changed@example.test', 'password' => 'changed-password',
        ])->assertNotFound();
        $this->delete(route('admin.registrars.destroy', $target->id))->assertNotFound();
        $this->assertSame($before, $target->fresh()->getAttributes());
    }

    public function test_unused_registrar_deletion_cleans_account_owned_records_only(): void
    {
        $admin = $this->user(['admin']);
        $target = $this->user(['registrar']);
        foreach ([$admin, $target] as $user) {
            DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'test-token']);
            DB::table('sessions')->insert(['id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
            DB::table('notifications')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'test',
                'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id, 'data' => '{}',
            ]);
        }
        $this->actingAs($admin)->delete(route('admin.registrars.destroy', $target->id))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.registrars.index'));
        $this->assertNull($target->fresh());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $target->id]);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $target->id]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $admin->email]);
        $this->assertDatabaseHas('sessions', ['user_id' => $admin->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id]);
    }

    private function user(array $roles): User
    {
        $user = User::factory()->create();
        foreach ($roles as $role) {
            $user->assignRole(Role::findOrCreate($role));
        }
        return $user;
    }

    private function requestFor(User $user): DocumentRequest
    {
        $type = DocumentType::create(['name' => 'Transcript', 'category' => 'academic', 'weight' => 4]);
        return DocumentRequest::create([
            'request_id' => 'REQ-SAFETY-'.$user->id, 'user_id' => $user->id,
            'document_type_id' => $type->id, 'purpose' => 'Deletion safety', 'status' => 'completed',
        ]);
    }
}

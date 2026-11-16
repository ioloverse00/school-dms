<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentManagementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function protectedTargets(): array
    {
        $cases = [];

        foreach (['admin', 'registrar'] as $actor) {
            foreach ([['admin'], ['registrar'], ['student', 'admin'], ['student', 'registrar'], []] as $roles) {
                $cases[$actor . ' targeting ' . implode('+', $roles ?: ['roleless'])] = [$actor, $roles];
            }
        }

        return $cases;
    }

    #[DataProvider('protectedTargets')]
    public function test_student_management_cannot_access_or_mutate_non_student_or_staff_accounts(string $actorRole, array $targetRoles): void
    {
        $actor = $this->userWithRoles([$actorRole]);
        $target = $this->userWithRoles($targetRoles);
        $original = $target->fresh()->getAttributes();

        $this->actingAs($actor)
            ->get(route($actorRole . '.students.edit', $target->id))
            ->assertNotFound();

        $this->put(route($actorRole . '.students.update', $target->id), [
            'student_id' => 'ATTEMPTED-CHANGE',
            'name' => 'Attempted account takeover',
            'email' => 'attempted-change@example.test',
            'course' => config('programs.default')[0],
            'student_type' => 'regular',
            'password' => 'attempted-password-change',
        ])->assertNotFound();

        $this->assertSame($original, $target->fresh()->getAttributes());

        $this->delete(route($actorRole . '.students.destroy', $target->id))
            ->assertNotFound();

        $this->assertSame($original, $target->fresh()->getAttributes());
    }

    public static function staffRoles(): array
    {
        return ['admin' => ['admin'], 'registrar' => ['registrar']];
    }

    #[DataProvider('staffRoles')]
    public function test_staff_can_still_manage_student_accounts(string $actorRole): void
    {
        $actor = $this->userWithRoles([$actorRole]);
        $student = $this->userWithRoles(['student']);

        $this->actingAs($actor)->get(route($actorRole . '.students.index'))
            ->assertOk()
            ->assertSee('action="' . route($actorRole . '.students.store') . '"', false)
            ->assertSee(url($actorRole . '/students') . "/' + editData.id", false)
            ->assertSee(url($actorRole . '/students') . "/' + deleteId", false);

        $this->get(route($actorRole . '.students.create'))->assertOk()
            ->assertSee('action="' . route($actorRole . '.students.store') . '"', false);

        $this->post(route($actorRole . '.students.store'), [
            'student_id' => 'STUDENT-NEW',
            'name' => 'Created student',
            'email' => 'created-student@example.test',
            'password' => 'new-student-password',
            'course' => config('programs.default')[0],
            'student_type' => 'regular',
        ])->assertSessionHasNoErrors()->assertRedirect(route($actorRole . '.students.index'));
        $created = User::where('student_id', 'STUDENT-NEW')->firstOrFail();
        $this->assertTrue($created->hasRole('student'));

        $this->actingAs($actor)
            ->get(route($actorRole . '.students.edit', $student->id))
            ->assertOk()
            ->assertSee('action="' . route($actorRole . '.students.update', $student->id) . '"', false);

        $this->put(route($actorRole . '.students.update', $student->id), [
            'student_id' => 'STUDENT-001',
            'name' => 'Updated student',
            'email' => $student->email,
            'course' => config('programs.default')[0],
            'student_type' => 'regular',
        ])->assertSessionHasNoErrors()->assertRedirect(route($actorRole . '.students.index'));

        $this->assertSame('Updated student', $student->fresh()->name);

        $this->delete(route($actorRole . '.students.destroy', $student->id))
            ->assertRedirect(route($actorRole . '.students.index'));

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
    }

    private function userWithRoles(array $roles): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'registrar', 'student'] as $role) {
            Role::findOrCreate($role);
        }

        $user = User::factory()->create();
        $user->assignRole($roles);

        return $user;
    }
}

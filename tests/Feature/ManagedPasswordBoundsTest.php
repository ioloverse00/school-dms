<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagedPasswordBoundsTest extends TestCase
{
    use RefreshDatabase;

    public static function badPasswords(): array
    {
        $cases = [];
        foreach ([['admin', 'students'], ['registrar', 'students'], ['admin', 'registrars']] as [$actor, $module]) {
            foreach ([str_repeat('x', 73), str_repeat('é', 37), "Valid\0Password"] as $password) {
                $cases[] = [$actor, $module, $password];
            }
        }

        return $cases;
    }

    #[DataProvider('badPasswords')]
    public function test_invalid_hash_inputs_reject_create_and_update_without_partial_records(string $actor, string $module, string $password): void
    {
        config(['hashing.driver' => 'bcrypt']);
        foreach (['admin', 'registrar', 'student'] as $role) {
            Role::findOrCreate($role);
        }
        $manager = User::factory()->create();
        $manager->assignRole($actor);
        $this->actingAs($manager);
        $payload = ['name' => 'New Account', 'email' => 'new@example.test', 'password' => $password];
        if ($module === 'students') {
            $payload += ['student_id' => 'NEW-001', 'course' => config('programs.default')[0], 'student_type' => 'regular'];
        }
        $count = User::count();
        $this->postJson(route($actor . '.' . $module . '.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame($count, User::count());
        $target = User::factory()->create(['student_id' => 'TARGET-001']);
        $target->assignRole($module === 'students' ? 'student' : 'registrar');
        $original = $target->fresh()->getAttributes();
        $this->putJson(route($actor . '.' . $module . '.update', $target->id), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame($original, $target->fresh()->getAttributes());
    }

    public function test_72_byte_multibyte_password_can_be_created_and_verified(): void
    {
        foreach (['admin', 'registrar'] as $role) {
            Role::findOrCreate($role);
        }
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $password = str_repeat('é', 36);
        $this->actingAs($admin)->post(route('admin.registrars.store'), [
            'name' => 'Boundary Password', 'email' => 'boundary@example.test', 'password' => $password,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue(Hash::check($password, User::where('email', 'boundary@example.test')->firstOrFail()->password));
    }
}

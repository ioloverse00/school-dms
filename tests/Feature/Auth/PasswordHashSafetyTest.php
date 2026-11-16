<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordHashSafetyTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidInputs(): array
    {
        $cases = [];
        foreach (['profile', 'reset', 'activation'] as $path) {
            foreach (['ascii' => str_repeat('a', 73), 'multibyte' => str_repeat('é', 37), 'null' => "Valid\0Password"] as $label => $password) {
                $cases[$path.' '.$label] = [$path, $password];
            }
        }
        return $cases;
    }

    #[DataProvider('invalidInputs')]
    public function test_rejection_preserves_credentials_and_token_and_allows_valid_retry(string $path, string $invalid): void
    {
        config(['hashing.driver' => 'bcrypt']);
        $user = User::factory()->create(['student_id' => 'HASH-SAFE-001']);
        $user->assignRole(Role::findOrCreate('student'));
        $token = Password::broker()->createToken($user);
        $before = $user->fresh()->getAttributes();
        $payload = ['email' => $user->email, 'student_id' => $user->student_id, 'token' => $token,
            'password' => $invalid, 'password_confirmation' => $invalid];

        if ($path === 'profile') {
            $this->actingAs($user)->from('/profile')->put('/password', $payload + ['current_password' => 'password'])
                ->assertRedirect('/profile')->assertSessionHasErrorsIn('updatePassword', 'password');
            $this->assertAuthenticatedAs($user);
        } else {
            $this->post($path === 'reset' ? '/reset-password' : '/register', $payload)->assertSessionHasErrors('password');
            $this->assertGuest();
        }
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
        $this->assertDatabaseCount('users', 1);

        $valid = str_repeat('a', 68) . 'A1!x'; // Exactly 72 bytes with required complexity.
        $payload['password'] = $payload['password_confirmation'] = $valid;
        // Clear validation feedback before retrying the original token.
        $this->flushSession();
        if ($path === 'profile') {
            $this->put('/password', $payload + ['current_password' => 'password'])->assertSessionHasNoErrors();
            $this->assertTrue(Password::broker()->tokenExists($user, $token));
        } else {
            $this->post($path === 'reset' ? '/reset-password' : '/register', $payload)->assertSessionHasNoErrors()
                ->assertRedirect($path === 'reset' ? route('login') : route('student.dashboard'));
            $this->assertFalse(Password::broker()->tokenExists($user, $token));
        }
        $this->assertTrue(Hash::check($valid, $user->fresh()->password));
    }
}

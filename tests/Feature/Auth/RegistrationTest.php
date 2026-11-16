<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\StudentInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'registrar', 'admin'] as $role) Role::findOrCreate($role);
    }

    public function test_registration_screen_explains_invitation_requirement(): void
    {
        $response = $this->get('/register')->assertOk()->assertSee('single-use link')->assertSee('Student ID');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_valid_invitation_activates_existing_student_without_accepting_role_or_identity_changes(): void
    {
        $user = $this->student();
        $payload = $this->activation($user);
        $payload['name'] = 'Attacker supplied name';
        $payload['role'] = 'admin';
        $this->post('/register', $payload)->assertSessionHasNoErrors()->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check($payload['password'], $user->fresh()->password));
        $this->assertSame('School provisioned name', $user->fresh()->name);
        $this->assertFalse($user->fresh()->hasRole('admin'));
        $this->assertDatabaseCount('users', 1);
        $this->assertFalse(Password::broker()->tokenExists($user, $payload['token']));
        $this->get(route('student.dashboard'))->assertOk();
    }

    public function test_public_signup_without_an_invitation_creates_no_account(): void
    {
        $this->post('/register', ['name' => 'Visitor', 'email' => 'visitor@example.test', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors(['student_id', 'token']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_wrong_student_id_cannot_claim_an_invitation(): void
    {
        $user = $this->student();
        $original = $user->password;
        $payload = $this->activation($user);
        $payload['student_id'] = 'SOMEONE-ELSE';
        $this->post('/register', $payload)->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $user = $this->student();
        $payload = $this->activation($user);
        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();
        $this->post('/register', $payload)->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_used_invitation_cannot_be_replayed(): void
    {
        $user = $this->student();
        $payload = $this->activation($user);
        $this->post('/register', $payload)->assertSessionHasNoErrors();
        $this->post(route('logout'));
        $this->post('/register', $payload)->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_invitation_cannot_activate_an_account_with_staff_roles(): void
    {
        $user = $this->student();
        $user->assignRole('admin');
        $original = $user->password;
        $this->post('/register', $this->activation($user))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_roleless_account_cannot_sign_in_to_business_workflows(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_invitation_command_emails_the_provisioned_address_and_never_prints_token(): void
    {
        Notification::fake();
        $user = $this->student();
        $this->artisan('students:invite', ['student_id' => $user->student_id])->assertSuccessful();
        Notification::assertSentTo($user, StudentInvitation::class, function ($notification) use ($user) {
            parse_str(parse_url($notification->toMail($user)->actionUrl, PHP_URL_QUERY), $query);
            return $query['email'] === $user->email && Password::broker()->tokenExists($user, $query['token']);
        });
        $this->artisan('students:invite', ['student_id' => $user->student_id])->assertFailed();
        Notification::assertSentTimes(StudentInvitation::class, 1);
    }

    public function test_invitation_command_refuses_staff_targets(): void
    {
        Notification::fake();
        $user = $this->student();
        $user->assignRole('registrar');
        $this->artisan('students:invite', ['student_id' => $user->student_id])->assertFailed();
        Notification::assertNothingSent();
    }

    public function test_activation_rejects_passwords_that_would_exceed_bcrypt_byte_limit(): void
    {
        $user = $this->student();
        $original = $user->password;
        $payload = $this->activation($user);
        $payload['password'] = $payload['password_confirmation'] = str_repeat('密', 30);
        $this->post('/register', $payload)->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertSame($original, $user->fresh()->password);
    }

    private function student(): User
    {
        $user = User::factory()->create(['student_id' => 'SCHOOL-001', 'name' => 'School provisioned name']);
        $user->assignRole('student');
        return $user;
    }

    private function activation(User $user): array
    {
        return [
            'student_id' => $user->student_id, 'email' => $user->email,
            'token' => Password::broker()->createToken($user),
            'password' => 'new-activation-password', 'password_confirmation' => 'new-activation-password',
        ];
    }
}

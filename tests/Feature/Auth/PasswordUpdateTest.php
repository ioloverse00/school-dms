<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'New-password1!',
                'password_confirmation' => 'New-password1!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('New-password1!', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'New-password1!',
                'password_confirmation' => 'New-password1!',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }

    public function test_each_password_complexity_requirement_is_enforced(): void
    {
        $user = User::factory()->create();
        foreach (['Aa1!abc', 'lowercase1!', 'UPPERCASE1!', 'NoNumbers!', 'NoSymbols12'] as $weakPassword) {
            $this->actingAs($user)->from('/profile')->put('/password', [
                'current_password' => 'password',
                'password' => $weakPassword,
                'password_confirmation' => $weakPassword,
            ])->assertSessionHasErrorsIn('updatePassword', 'password');
            $this->assertTrue(Hash::check('password', $user->fresh()->password));
        }
    }

    public function test_live_current_password_check_does_not_change_the_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('password.check'), ['current_password' => 'wrong'])
            ->assertOk()->assertExactJson(['valid' => false]);
        $this->postJson(route('password.check'), ['current_password' => 'password'])
            ->assertOk()->assertExactJson(['valid' => true])->assertHeader('Cache-Control', 'no-store, private');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}

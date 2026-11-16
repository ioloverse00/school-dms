<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StudentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecureProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeding_is_repeatable_and_never_creates_accounts(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 3);
    }

    public function test_repeated_default_seeding_preserves_custom_catalog_values(): void
    {
        $this->seed(DatabaseSeeder::class);
        $count = DocumentType::count();
        $type = DocumentType::firstOrFail();
        $type->update(['weight' => 17]);
        SystemSetting::set('programs', ['Custom school program']);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 0);
        $this->assertSame($count, DocumentType::count());
        $this->assertSame(17, $type->fresh()->weight);
        $this->assertSame(['Custom school program'], SystemSetting::get('programs'));
    }

    public function test_secure_admin_creation_uses_hidden_confirmed_password(): void
    {
        $this->artisan('app:create-admin', ['email' => 'new-admin@example.test', '--name' => 'New administrator'])
            ->expectsQuestion('Password', 'unique-test-admin-password')
            ->expectsQuestion('Confirm password', 'unique-test-admin-password')
            ->doesntExpectOutputToContain('unique-test-admin-password')
            ->assertSuccessful();
        $admin = User::where('email', 'new-admin@example.test')->firstOrFail();
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue(Hash::check('unique-test-admin-password', $admin->password));
    }

    public function test_admin_command_does_not_promote_or_overwrite_existing_accounts(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.test']);
        $original = $existing->fresh()->getAttributes();
        $this->artisan('app:create-admin', ['email' => $existing->email, '--name' => 'Attempted replacement'])->assertFailed();
        $this->assertSame($original, $existing->fresh()->getAttributes());
        $this->assertFalse($existing->fresh()->hasRole('admin'));
    }

    public function test_invalid_admin_password_does_not_create_any_account(): void
    {
        $this->artisan('app:create-admin', ['email' => 'new-admin@example.test', '--name' => 'New administrator'])
            ->expectsQuestion('Password', 'short')
            ->expectsQuestion('Confirm password', 'different')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_password_is_checked_by_bytes_as_well_as_characters(): void
    {
        $password = str_repeat('密', 30);
        $this->artisan('app:create-admin', ['email' => 'new-admin@example.test', '--name' => 'New administrator'])
            ->expectsQuestion('Password', $password)->expectsQuestion('Confirm password', $password)->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public static function demoCommands(): array
    {
        return [['db:clean-demo'], ['db:generate-students'], ['db:generate-requests']];
    }

    #[DataProvider('demoCommands')]
    public function test_demo_commands_refuse_production_before_any_mutation(string $command): void
    {
        $this->app->instance('env', 'production');
        $this->artisan($command)->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_demo_student_seeder_refuses_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        $this->app->make(StudentSeeder::class)->run();
    }

    public function test_demo_generation_has_no_shared_default_password(): void
    {
        Role::findOrCreate('student');
        $firstPassword = str_repeat('a', 40);
        $secondPassword = str_repeat('b', 40);
        \Illuminate\Support\Str::createRandomStringsUsingSequence([$firstPassword, str_repeat('a', 10), $secondPassword, str_repeat('b', 10)]);
        try {
            $this->artisan('db:generate-students', ['count' => 2])->assertSuccessful();
        } finally {
            \Illuminate\Support\Str::createRandomStringsNormally();
        }
        $users = User::orderBy('id')->get();
        $this->assertCount(2, $users);
        $this->assertTrue(Hash::check($firstPassword, $users[0]->password));
        $this->assertTrue(Hash::check($secondPassword, $users[1]->password));
    }
}

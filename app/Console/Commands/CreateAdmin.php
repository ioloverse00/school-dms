<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {email} {--name= : Administrator display name}';

    protected $description = 'Create a new administrator using a hidden password prompt; never replace an existing account';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $name = $this->option('name') ?: $this->ask('Administrator name');
        if (User::where('email', $email)->exists()) {
            $this->error('An account already uses this email. No account or role was changed.');

            return self::FAILURE;
        }
        $password = $this->secret('Password');
        $confirmation = $this->secret('Confirm password');
        $validation = Validator::make([
            'email' => $email, 'name' => $name,
            'password' => $password, 'password_confirmation' => $confirmation,
        ], [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['bail', 'required', 'string', 'min:12', 'max:72', 'confirmed', function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('The password must not exceed 72 bytes.');
                }
            }],
        ]);
        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($name, $email, $password) {
            Role::findOrCreate('admin', 'web');
            $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);
            $user->assignRole('admin');
        });
        $this->info('Administrator created. Passwords are never printed or stored in command arguments.');

        return self::SUCCESS;
    }
}

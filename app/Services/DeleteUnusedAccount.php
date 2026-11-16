<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class DeleteUnusedAccount
{
    public function handle(User $account, string $errorBag = 'default'): void
    {
        DB::transaction(function () use ($account, $errorBag) {
            // Serialize account deletions before checking how many admins remain.
            Role::where('name', 'admin')->orderBy('id')->lockForUpdate()->get();
            $user = User::whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            $reject = function (string $message) use ($errorBag) {
                throw ValidationException::withMessages(['account' => $message])->errorBag($errorBag);
            };

            if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
                $reject('The last administrator account cannot be deleted.');
            }
            if (DocumentRequest::where('user_id', $user->id)->exists()) {
                $reject('This account has document requests and cannot be deleted. Its requests, history and uploaded files must be retained.');
            }
            if (DocumentRequestEvent::where('actor_id', $user->id)->exists()) {
                $reject('This account has recorded request actions and cannot be deleted. Its staff history must be retained.');
            }

            $user->notifications()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        }, 3);
    }
}

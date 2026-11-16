<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\StudentInvitation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Throwable;

class InviteStudent extends Command
{
    protected $signature = 'students:invite {student_id : Existing institution-provisioned student ID}';

    protected $description = 'Email a single-use activation link to an existing student; never print invitation tokens';

    public function handle(): int
    {
        $student = User::whereHas('roles', fn ($query) => $query->where('name', 'student')->where('guard_name', 'web'))
            ->where('student_id', $this->argument('student_id'))
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', ['admin', 'registrar']))->first();
        if (! $student) {
            $this->error('No eligible student account found.');

            return self::FAILURE;
        }
        try {
            $status = Password::broker()->sendResetLink(['email' => $student->email], function ($user, $token) {
                $user->notify(new StudentInvitation($token));
            });
        } catch (Throwable) {
            $this->error('Invitation could not be delivered. Check mail configuration and retry after the broker throttle interval.');

            return self::FAILURE;
        }
        if ($status !== Password::RESET_LINK_SENT) {
            $this->error('Invitation not sent. Check the account or wait before retrying.');

            return self::FAILURE;
        }
        $this->info('Activation link sent to the provisioned student email. No credentials were printed.');

        return self::SUCCESS;
    }
}

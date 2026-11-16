<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return response()->view('auth.register')
            ->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'password' => ['bail', 'required', 'string', 'min:8', 'max:72', 'confirmed', new \App\Rules\PasswordHashInput()],
        ]);

        $student = User::whereHas('roles', fn ($query) => $query->where('name', 'student')->where('guard_name', 'web'))
            ->where('student_id', $validated['student_id'])->where('email', $validated['email'])
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', ['admin', 'registrar']))->first();
        if (! $student) {
            throw ValidationException::withMessages(['email' => 'The activation link or student details are invalid or expired. Contact your school.']);
        }

        $status = DB::transaction(function () use ($validated) {
            return Password::broker()->reset($validated, function (User $user, string $password) use ($validated) {
                $user = User::lockForUpdate()->findOrFail($user->id);
                if (! $user->hasRole('student') || $user->hasAnyRole(['admin', 'registrar']) || ! Password::broker()->tokenExists($user, $validated['token'])) {
                    throw ValidationException::withMessages(['email' => 'The activation link or student details are invalid or expired. Contact your school.']);
                }
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            });
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'The activation link or student details are invalid or expired. Contact your school.']);
        }
        Auth::login($student->fresh());
        $request->session()->regenerate();

        return redirect()->route('student.dashboard');
    }
}

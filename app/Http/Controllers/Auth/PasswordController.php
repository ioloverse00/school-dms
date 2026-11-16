<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function check(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string', 'max:255']]);
        return response()->json(['valid' => Hash::check($data['current_password'], $request->user()->password)])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['bail', 'required', 'string', 'max:255', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed', new \App\Rules\PasswordHashInput()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}

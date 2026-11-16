<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegistrarController extends Controller
{
    public function index()
    {
        $registrars = User::whereHas('roles', fn ($query) => $query->where('name', 'registrar')->where('guard_name', 'web'))
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', ['admin', 'student']))
            ->paginate(10);
        return view('admin.registrars.index', compact('registrars'));
    }

    public function create()
    {
        return view('admin.registrars.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'max:255', new \App\Rules\PasswordHashInput()],
        ]);

        $registrar = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $registrar->assignRole('registrar');

        return redirect()->route('admin.registrars.index')
            ->with('success', 'Registrar account created successfully!');
    }

    public function edit($id)
    {
        $registrar = $this->findManagedRegistrar($id);
        return view('admin.registrars.edit', compact('registrar'));
    }

    public function update(Request $request, $id)
    {
        $registrar = $this->findManagedRegistrar($id);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $id],
            'password' => ['nullable', 'string', 'min:8', 'max:255', new \App\Rules\PasswordHashInput()],
        ]);

        $registrar->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {
            $registrar->update(['password' => Hash::make($request->password)]);
        }

        return redirect()->route('admin.registrars.index')
            ->with('success', 'Registrar updated successfully!');
    }

    public function destroy($id)
    {
        $registrar = $this->findManagedRegistrar($id);
        app(\App\Services\DeleteUnusedAccount::class)->handle($registrar);
        return redirect()->route('admin.registrars.index')
            ->with('success', 'Registrar deleted successfully!');
    }
    private function findManagedRegistrar($id): User
    {
        return User::whereHas('roles', fn ($query) => $query->where('name', 'registrar')->where('guard_name', 'web'))
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', ['admin', 'student']))
            ->findOrFail($id);
    }
}

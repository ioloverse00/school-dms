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
        $registrars = User::role('registrar')->paginate(10);
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
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
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
        $registrar = User::findOrFail($id);
        return view('admin.registrars.edit', compact('registrar'));
    }

    public function update(Request $request, $id)
    {
        $registrar = User::findOrFail($id);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $id],
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
        $registrar = User::findOrFail($id);
        $registrar->delete();
        return redirect()->route('admin.registrars.index')
            ->with('success', 'Registrar deleted successfully!');
    }
}
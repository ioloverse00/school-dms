<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\StudentCsvExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $selectedStudentType = $this->validatedStudentType($request);
        $search = \App\Support\ListSearch::term($request);
        $students = \App\Support\StudentListing::query($selectedStudentType, $search)
            ->paginate(10)
            ->withQueryString();
        $programs     = SystemSetting::get('programs', config('programs.default', []));
        $studentTypes = \App\Support\StudentCategory::options();
        $routePrefix = $this->routePrefix();

        return view('admin.students.index', compact('students', 'programs', 'studentTypes', 'routePrefix', 'selectedStudentType', 'search'));
    }

    public function export(Request $request)
    {
        return StudentCsvExporter::download($this->validatedStudentType($request), \App\Support\ListSearch::term($request));
    }

    public function create()
    {
        $programs = SystemSetting::get('programs', config('programs.default', []));
        $routePrefix = $this->routePrefix();
        $studentTypes = \App\Support\StudentCategory::options();
        return view('admin.students.create', compact('programs', 'routePrefix', 'studentTypes'));
    }

    public function store(Request $request)
    {
        $programs = SystemSetting::get('programs', config('programs.default', []));

        $request->validate([
            'student_id' => ['required', 'string', 'max:255', 'unique:users'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'max:255', new \App\Rules\PasswordHashInput()],
            'course' => ['required', 'string', Rule::in($programs)],
            'year_graduated' => ['nullable', 'digits:4', 'integer', 'min:1990', 'max:' . date('Y')],
            'student_type' => ['required', Rule::in(array_keys(\App\Support\StudentCategory::labels()))],
        ]);

        $student = User::create([
            'student_id' => $request->student_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'course' => $request->course,
            'year_graduated' => $request->year_graduated,
            'student_type' => $request->student_type,
        ]);

        $student->assignRole('student');

        return redirect()->route($this->routePrefix() . '.students.index')
            ->with('success', 'Student account created successfully!');
    }

    public function edit($id)
    {
        $user = $this->findManagedStudent($id);
        $programs = SystemSetting::get('programs', config('programs.default', []));
        $routePrefix = $this->routePrefix();
        $studentTypes = \App\Support\StudentCategory::options();
        return view('admin.students.edit', compact('user', 'programs', 'routePrefix', 'studentTypes'));
    }

    public function update(Request $request, $id)
    {
        $user = $this->findManagedStudent($id);
        $programs = SystemSetting::get('programs', config('programs.default', []));

        $request->validate([
            'student_id' => ['required', 'string', 'max:255', 'unique:users,student_id,' . $id],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $id],
            'password' => ['nullable', 'string', 'min:8', 'max:255', new \App\Rules\PasswordHashInput()],
            'course' => ['required', 'string', Rule::in($programs)],
            'year_graduated' => ['nullable', 'digits:4', 'integer', 'min:1990', 'max:' . date('Y')],
            'student_type' => ['required', Rule::in(array_keys(\App\Support\StudentCategory::labels()))],
        ]);

        $user->update([
            'student_id' => $request->student_id,
            'name' => $request->name,
            'email' => $request->email,
            'course' => $request->course,
            'year_graduated' => $request->year_graduated,
            'student_type' => $request->student_type,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return redirect()->route($this->routePrefix() . '.students.index')
            ->with('success', 'Student updated successfully!');
    }

    public function destroy($id)
    {
        $user = $this->findManagedStudent($id);
        app(\App\Services\DeleteUnusedAccount::class)->handle($user);
        return redirect()->route($this->routePrefix() . '.students.index')
            ->with('success', 'Student deleted successfully!');
    }

    private function routePrefix(): string
    {
        return request()->routeIs('registrar.*') ? 'registrar' : 'admin';
    }

    private function findManagedStudent($id): User
    {
        // Staff accounts must stay outside student administration, even with a student role.
        return User::role('student')
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', ['admin', 'registrar']))
            ->findOrFail($id);
    }

    private function validatedStudentType(Request $request): ?string
    {
        $studentType = $request->query('student_type');

        return in_array($studentType, array_keys(\App\Support\StudentCategory::labels()), true) ? $studentType : null;
    }
}

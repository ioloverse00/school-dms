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
    private const STUDENT_TYPES = [
        'regular',
        'graduating',
        'alumni',
    ];

    public function index(Request $request)
    {
        $selectedStudentType = $this->validatedStudentType($request);
        $students     = User::role('student')
            ->withCount('documentRequests')
            ->when($selectedStudentType, fn ($query) => $query->where('student_type', $selectedStudentType))
            ->paginate(10)
            ->withQueryString();
        $programs     = SystemSetting::get('programs', config('programs.default', []));
        $studentTypes = SystemSetting::get('student_types', [
            ['value' => 'regular',    'label' => 'Regular Student'],
            ['value' => 'graduating', 'label' => 'Graduating Student'],
            ['value' => 'alumni',     'label' => 'Alumni'],
        ]);
        $routePrefix = request()->routeIs('registrar.*') ? 'registrar' : 'admin';

        return view('admin.students.index', compact('students', 'programs', 'studentTypes', 'routePrefix', 'selectedStudentType'));
    }

    public function export(Request $request)
    {
        return StudentCsvExporter::download($this->validatedStudentType($request));
    }

    public function create()
    {
        $programs = SystemSetting::get('programs', config('programs.default', []));
        return view('admin.students.create', compact('programs'));
    }

    public function store(Request $request)
    {
        $programs = SystemSetting::get('programs', config('programs.default', []));

        $request->validate([
            'student_id' => ['required', 'string', 'unique:users'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'course' => ['required', 'string', Rule::in($programs)],
            'year_graduated' => ['nullable', 'string'],
            'student_type' => ['required', 'in:regular,graduating,alumni'],
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

        return redirect()->route('admin.students.index')
            ->with('success', 'Student account created successfully!');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $programs = SystemSetting::get('programs', config('programs.default', []));
        return view('admin.students.edit', compact('user', 'programs'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $programs = SystemSetting::get('programs', config('programs.default', []));

        $request->validate([
            'student_id' => ['required', 'string', 'unique:users,student_id,' . $id],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $id],
            'course' => ['required', 'string', Rule::in($programs)],
            'year_graduated' => ['nullable', 'string'],
            'student_type' => ['required', 'in:regular,graduating,alumni'],
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

        return redirect()->route('admin.students.index')
            ->with('success', 'Student updated successfully!');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return redirect()->route('admin.students.index')
            ->with('success', 'Student deleted successfully!');
    }

    private function validatedStudentType(Request $request): ?string
    {
        $studentType = $request->query('student_type');

        return in_array($studentType, self::STUDENT_TYPES, true) ? $studentType : null;
    }
}

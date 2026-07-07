<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SettingsController extends Controller
{
    private array $defaultPrograms = [];

    public function __construct()
    {
        $this->defaultPrograms = config('programs.default', []);
    }

    private array $defaultStudentTypes = [
        ['value' => 'regular',    'label' => 'Regular Student'],
        ['value' => 'graduating', 'label' => 'Graduating Student'],
        ['value' => 'alumni',     'label' => 'Alumni'],
    ];

    private array $defaultStudentWeights = [
        'regular'   => 2,
        'graduating' => 5,
        'alumni'    => 3,
    ];

    private array $defaultUrgencyLevels = [
        'regular'    => 1,
        'urgent'     => 2,
        'very_urgent' => 3,
    ];

    public function index()
    {
        $programs       = SystemSetting::get('programs', $this->defaultPrograms);
        $studentTypes   = SystemSetting::get('student_types', $this->defaultStudentTypes);
        $documentTypes  = DocumentType::orderBy('category')->orderBy('name')->get();
        $studentWeights = SystemSetting::get('student_category_weights', $this->defaultStudentWeights);
        $urgencyLevels  = SystemSetting::get('urgency_level_values', $this->defaultUrgencyLevels);
        $urgencyWeight  = SystemSetting::get('urgency_weight', 5);
        $aiForecastPrompt = SystemSetting::get('ai_forecast_prompt', '');

        return view('admin.settings.index', compact('programs', 'studentTypes', 'documentTypes', 'studentWeights', 'urgencyLevels', 'urgencyWeight', 'aiForecastPrompt'));
    }

    public function programs(Request $request)
    {
        $programs = SystemSetting::get('programs', $this->defaultPrograms);

        $groupedPrograms = ['BS' => [], 'AB' => [], 'Bachelor' => [], 'Masteral' => [], 'Doctoral' => [], 'Others' => []];
        foreach ($programs as $p) {
            if (str_starts_with($p, 'BS ') || $p === 'BS') {
                $groupedPrograms['BS'][] = $p;
            } elseif (str_starts_with($p, 'AB ') || $p === 'AB') {
                $groupedPrograms['AB'][] = $p;
            } elseif (str_starts_with($p, 'Bachelor')) {
                $groupedPrograms['Bachelor'][] = $p;
            } elseif (stripos($p, 'Master') !== false) {
                $groupedPrograms['Masteral'][] = $p;
            } elseif (stripos($p, 'Doctor') !== false || stripos($p, 'PhD') !== false || stripos($p, 'Juris') !== false) {
                $groupedPrograms['Doctoral'][] = $p;
            } else {
                $groupedPrograms['Others'][] = $p;
            }
        }

        // Flatten in grouped order for paginated table
        $sorted = array_merge(...array_values($groupedPrograms));
        $categoryMap = [];
        foreach ($groupedPrograms as $key => $group) {
            foreach ($group as $p) $categoryMap[$p] = $key;
        }

        $perPage = 10;
        $page    = $request->integer('page', 1);
        $items   = array_slice($sorted, ($page - 1) * $perPage, $perPage);
        $paginator = new LengthAwarePaginator($items, count($sorted), $perPage, $page, [
            'path'  => $request->url(),
            'query' => $request->query(),
        ]);

        return view('admin.settings.programs', compact('programs', 'paginator', 'categoryMap'));
    }

    public function studentTypes()
    {
        $studentTypes = SystemSetting::get('student_types', $this->defaultStudentTypes);
        return view('admin.settings.student-types', compact('studentTypes'));
    }

    public function documentWeightsPage()
    {
        $documentTypes = DocumentType::orderBy('category')->orderBy('name')->get();
        return view('admin.settings.document-management.document-weights', compact('documentTypes'));
    }

    public function studentWeightsPage()
    {
        $studentWeights = SystemSetting::get('student_category_weights', $this->defaultStudentWeights);
        return view('admin.settings.document-management.student-weights', compact('studentWeights'));
    }

    public function urgencyLevelsPage()
    {
        $urgencyLevels = SystemSetting::get('urgency_level_values', $this->defaultUrgencyLevels);
        $urgencyWeight = SystemSetting::get('urgency_weight', 5);
        return view('admin.settings.document-management.urgency-levels', compact('urgencyLevels', 'urgencyWeight'));
    }

    // --- Programs ---

    public function addProgram(Request $request)
    {
        $request->validate(['program' => 'required|string|max:100']);

        $programs = SystemSetting::get('programs', $this->defaultPrograms);
        $name     = trim($request->program);

        if (!in_array($name, $programs)) {
            $programs[] = $name;
            sort($programs);
            SystemSetting::set('programs', $programs);
        }

        return back()->with('success', "Program \"$name\" added.");
    }

    public function deleteProgram(Request $request)
    {
        $request->validate(['program' => 'required|string']);

        $programs = SystemSetting::get('programs', $this->defaultPrograms);
        $programs = array_values(array_filter($programs, fn($p) => $p !== $request->program));
        SystemSetting::set('programs', $programs);

        return back()->with('success', 'Program removed.');
    }

    // --- Student Types ---

    public function addStudentType(Request $request)
    {
        $request->validate([
            'type_value' => 'required|string|max:50|alpha_dash',
            'type_label' => 'required|string|max:100',
        ]);

        $types = SystemSetting::get('student_types', $this->defaultStudentTypes);
        $value = strtolower(trim($request->type_value));

        if (!collect($types)->firstWhere('value', $value)) {
            $types[] = ['value' => $value, 'label' => trim($request->type_label)];
            SystemSetting::set('student_types', $types);
        }

        return back()->with('success', "Student type \"{$request->type_label}\" added.");
    }

    public function deleteStudentType(Request $request)
    {
        $request->validate(['type_value' => 'required|string']);

        $types = SystemSetting::get('student_types', $this->defaultStudentTypes);
        $types = array_values(array_filter($types, fn($t) => $t['value'] !== $request->type_value));
        SystemSetting::set('student_types', $types);

        return back()->with('success', 'Student type removed.');
    }

    // --- Document Management ---

    public function updateDocumentWeights(Request $request)
    {
        $weights = $request->input('weights', []);
        foreach ($weights as $id => $weight) {
            DocumentType::where('id', $id)->update(['weight' => max(1, (int) $weight)]);
        }
        return back()->with('success', 'Document type weights updated.');
    }

    public function updateStudentWeights(Request $request)
    {
        $weights = $request->input('weights', []);
        $weights = array_map('intval', $weights);
        SystemSetting::set('student_category_weights', $weights);
        return back()->with('success', 'Student category weights updated.');
    }

    public function updateUrgencyLevels(Request $request)
    {
        $levels = $request->input('levels', []);
        $levels = array_map('intval', $levels);
        SystemSetting::set('urgency_level_values', $levels);

        return back()->with('success', 'Urgency levels updated.');
    }

    // --- AI Forecast Prompt ---

    public function updateAiForecastPrompt(Request $request)
    {
        $request->validate([
            'prompt' => ['nullable', 'string', 'max:2000'],
        ]);

        SystemSetting::set('ai_forecast_prompt', (string) $request->input('prompt', ''));

        return back()->with('success', 'AI forecast prompt updated.');
    }
}

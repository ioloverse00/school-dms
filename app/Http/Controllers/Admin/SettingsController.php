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
        $studentTypes   = \App\Support\StudentCategory::options();
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

        // Sort the complete list, independently of degree classification.
        $sorted = $programs;
        natcasesort($sorted);
        $sorted = array_values($sorted);
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
        return $this->studentWeightsPage();
    }

    public function documentWeightsPage()
    {
        $documentTypes = DocumentType::withCount('documentRequests')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('admin.settings.document-management.document-weights', compact('documentTypes'));
    }

    public function studentWeightsPage()
    {
        $studentWeights = SystemSetting::get('student_category_weights', $this->defaultStudentWeights);
        $urgencyLevels = SystemSetting::get('urgency_level_values', $this->defaultUrgencyLevels);
        $urgencyWeight = SystemSetting::get('urgency_weight', 5);
        $studentTypes = \App\Support\StudentCategory::options();
        $unsupportedTypes = \App\Support\StudentCategory::unsupported();
        $documentTypes = DocumentType::orderBy('name')->get();
        $studentWeights = array_replace(array_fill_keys(array_keys(\App\Support\StudentCategory::labels()), 1), $studentWeights);
        $studentWeights = array_intersect_key($studentWeights, \App\Support\StudentCategory::labels());
        return view('admin.settings.document-management.priority-settings', compact('studentWeights', 'urgencyLevels', 'urgencyWeight', 'studentTypes', 'unsupportedTypes', 'documentTypes'));
    }

    public function urgencyLevelsPage()
    {
        return redirect()->route('admin.settings.document-management.student-weights');
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

        if (\App\Models\User::where('course', $request->program)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['program' => 'This program is assigned to existing accounts and cannot be removed.']);
        }

        $programs = SystemSetting::get('programs', $this->defaultPrograms);
        $programs = array_values(array_filter($programs, fn($p) => $p !== $request->program));
        SystemSetting::set('programs', $programs);

        return back()->with('success', 'Program removed.');
    }

    // --- Student Types ---

    public function addStudentType(Request $request)
    {
        if ($request->input('mode') === 'create') {
            $data = $request->validate([
                'type_value' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
                'type_label' => ['required', 'string', 'max:100'],
                'weight' => ['required', 'integer', 'between:1,20'],
            ]);
            \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
                $types = \App\Support\StudentCategory::configured();
                if (array_key_exists($data['type_value'], \App\Support\StudentCategory::labels()) || collect($types)->contains('value', $data['type_value'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['type_value' => 'That category code already exists.']);
                }
                if (trim($data['type_label']) === '' || collect(\App\Support\StudentCategory::labels())->contains(fn($label) => mb_strtolower(trim($label)) === mb_strtolower(trim($data['type_label'])))) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['type_label' => 'Choose a unique, non-empty category label.']);
                }
                $types[] = ['value' => $data['type_value'], 'label' => trim($data['type_label']), 'custom' => true];
                $weights = SystemSetting::get('student_category_weights', $this->defaultStudentWeights);
                $weights[$data['type_value']] = (int) $data['weight'];
                SystemSetting::set('student_types', $types);
                SystemSetting::set('student_category_weights', $weights);
            });
            return back()->with('success', 'Student category added.');
        }
        $validated = $request->validate([
            'type_value' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(\App\Support\StudentCategory::labels()))],
            'type_label' => ['required', 'string', 'max:100'],
        ]);

        $types = SystemSetting::get('student_types', \App\Support\StudentCategory::options());
        if (! is_array($types)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['type_value' => 'Review the invalid stored category configuration before editing.']);
        }
        foreach ($types as $type) {
            if (! is_array($type) || ! is_string($type['value'] ?? null) || ! is_string($type['label'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['type_value' => 'Review the invalid stored category configuration before editing.']);
            }
        }
        $found = false;
        foreach ($types as &$type) {
            if ($type['value'] === $validated['type_value']) {
                $type['label'] = trim($validated['type_label']);
                $found = true;
            }
        }
        unset($type);
        if (! $found) {
            $types[] = ['value' => $validated['type_value'], 'label' => trim($validated['type_label'])];
        }
        SystemSetting::set('student_types', $types);

        return back()->with('success', 'Student category label saved. Its code and priority rules are unchanged.');
    }

    public function deleteStudentType(Request $request)
    {
        $validated = $request->validate(['type_value' => ['required', 'string', 'max:50']]);
        $value = $validated['type_value'];
        if (array_key_exists($value, \App\Support\StudentCategory::LABELS)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['type_value' => 'The three supported categories are required and cannot be removed. You can edit their display labels.']);
        }
        if (\App\Models\User::where('student_type', $value)->exists() || \App\Models\DocumentRequest::where('student_category', $value)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['type_value' => 'This category is referenced by existing records and cannot be removed.']);
        }
        $types = SystemSetting::get('student_types', []);
        if (! is_array($types) || array_filter($types, fn ($type) => ! is_array($type) || ! is_string($type['value'] ?? null)) !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['type_value' => 'Review the invalid stored category configuration before editing.']);
        }
        SystemSetting::set('student_types', array_values(array_filter($types, fn ($type) => $type['value'] !== $value)));

        return back()->with('success', 'Unused unsupported category removed.');
    }

    // --- Document Management ---

    public function updateDocumentWeights(Request $request)
    {
        $validated = $request->validate([
            'weights' => ['required', 'array', 'min:1'],
            'weights.*' => ['required', 'integer', 'between:1,20'],
        ]);
        $weights = $validated['weights'];
        $ids = array_keys($weights);
        foreach ($ids as $id) {
            if (! ctype_digit((string) $id)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['weights' => 'Unknown document type.']);
            }
        }
        if (DocumentType::whereIn('id', $ids)->count() !== count($ids)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['weights' => 'Unknown document type.']);
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($weights) {
            foreach ($weights as $id => $weight) {
                DocumentType::where('id', $id)->update(['weight' => (int) $weight]);
            }
        });
        return back()->with('success', 'Document type weights updated.');
    }

    public function addDocumentType(Request $request)
    {
        $validated = $request->validate([
            '_modal' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:100', 'unique:document_types,name'],
            'category' => ['required', 'string', 'in:academic,certification'],
            'weight' => ['required', 'integer', 'min:1', 'max:20'],
            'alumni_only' => ['nullable', 'boolean'],
        ]);

        DocumentType::create([
            'name' => trim($validated['name']),
            'category' => $validated['category'],
            'weight' => (int) $validated['weight'],
            'alumni_only' => $request->boolean('alumni_only'),
        ]);

        return back()->with('success', "Document type \"{$validated['name']}\" added.");
    }

    public function updateDocumentType(Request $request, DocumentType $documentType)
    {
        $validated = $request->validate([
            '_modal' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:100', 'unique:document_types,name,' . $documentType->id],
            'category' => ['required', 'string', 'in:academic,certification'],
            'weight' => ['required', 'integer', 'min:1', 'max:20'],
            'alumni_only' => ['nullable', 'boolean'],
        ]);

        $documentType->update([
            'name' => trim($validated['name']),
            'category' => $validated['category'],
            'weight' => (int) $validated['weight'],
            'alumni_only' => $request->boolean('alumni_only'),
        ]);

        return back()->with('success', "Document type \"{$validated['name']}\" updated.");
    }

    public function deleteDocumentType(DocumentType $documentType)
    {
        if ($documentType->documentRequests()->exists()) {
            return back()->withErrors([
                'document_type' => 'This document type already has requests and cannot be deleted.',
            ]);
        }

        $name = $documentType->name;
        $documentType->delete();

        return back()->with('success', "Document type \"{$name}\" deleted.");
    }

    public function updateStudentWeights(Request $request)
    {
        $validated = $request->validate([
            'weights' => ['required', 'array:'.implode(',', array_keys(\App\Support\StudentCategory::labels()))],
            'weights.*' => ['required', 'integer', 'between:1,20'],
            'weights.regular' => ['required', 'integer', 'between:1,20'],
            'weights.graduating' => ['required', 'integer', 'between:1,20'],
            'weights.alumni' => ['required', 'integer', 'between:1,20'],
        ]);
        $weights = $validated['weights'];
        foreach (array_keys(\App\Support\StudentCategory::labels()) as $category) {
            if (!array_key_exists($category, $weights)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['weights' => 'Provide a weight for every student category.']);
            }
        }
        $weights = array_map('intval', $weights);
        SystemSetting::set('student_category_weights', $weights);
        return back()->with('success', 'Student category weights updated.');
    }

    public function updateUrgencyLevels(Request $request)
    {
        $validated = $request->validate([
            'levels' => ['required', 'array:regular,urgent,very_urgent'],
            'levels.regular' => ['required', 'integer', 'between:1,10'],
            'levels.urgent' => ['required', 'integer', 'between:1,10'],
            'levels.very_urgent' => ['required', 'integer', 'between:1,10'],
        ]);
        $levels = $validated['levels'];
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

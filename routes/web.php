<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Student\DocumentRequestController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentRequestController as AdminDocumentRequestController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Registrar\DashboardController as RegistrarDashboardController;
use App\Http\Controllers\Registrar\DocumentRequestController as RegistrarDocumentRequestController;
use App\Http\Controllers\Admin\RegistrarController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\AiForecastController;
use App\Models\RequiredFile;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return redirect()->route('login');
});

require __DIR__.'/auth.php';

// Profile routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Redirect after login based on role
Route::middleware('auth')->get('/dashboard', function () {
    if (auth()->user()->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }
    if (auth()->user()->hasRole('registrar')) {
        return redirect()->route('registrar.dashboard');
    }
    return redirect()->route('student.dashboard');
})->name('dashboard');

// Student routes
Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
    Route::resource('requests', DocumentRequestController::class);
});

// Registrar routes
Route::middleware(['auth', 'role:registrar|admin'])->prefix('registrar')->name('registrar.')->group(function () {
    Route::get('/dashboard', [RegistrarDashboardController::class, 'index'])->name('dashboard');
    Route::get('/requests', [RegistrarDocumentRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/export/csv', [RegistrarDocumentRequestController::class, 'export'])->name('requests.export');
    Route::get('/requests/{id}', [RegistrarDocumentRequestController::class, 'show'])->name('requests.show');
    Route::put('/requests/{id}', [RegistrarDocumentRequestController::class, 'update'])->name('requests.update');
    Route::patch('/requests/{id}/archive', [RegistrarDocumentRequestController::class, 'archive'])->name('requests.archive');
    Route::patch('/requests/{id}/unarchive', [RegistrarDocumentRequestController::class, 'unarchive'])->name('requests.unarchive');
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/export/csv', [StudentController::class, 'export'])->name('students.export');
    Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{student}', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
});

// Admin routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/ai-forecast', AiForecastController::class)->name('ai-forecast');
    Route::get('/requests', [AdminDocumentRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/export/csv', [AdminDocumentRequestController::class, 'export'])->name('requests.export');
    Route::get('/requests/{documentRequest}', [AdminDocumentRequestController::class, 'show'])->name('requests.show');
    Route::put('/requests/{documentRequest}', [AdminDocumentRequestController::class, 'update'])->name('requests.update');
    Route::patch('/requests/{documentRequest}/archive', [AdminDocumentRequestController::class, 'archive'])->name('requests.archive');
    Route::patch('/requests/{documentRequest}/unarchive', [AdminDocumentRequestController::class, 'unarchive'])->name('requests.unarchive');
    
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/export/csv', [StudentController::class, 'export'])->name('students.export');
    Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{student}', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

    Route::get('/registrars', [RegistrarController::class, 'index'])->name('registrars.index');
    Route::get('/registrars/create', [RegistrarController::class, 'create'])->name('registrars.create');
    Route::post('/registrars', [RegistrarController::class, 'store'])->name('registrars.store');
    Route::get('/registrars/{id}', [RegistrarController::class, 'edit'])->name('registrars.edit');
    Route::put('/registrars/{id}', [RegistrarController::class, 'update'])->name('registrars.update');
    Route::delete('/registrars/{id}', [RegistrarController::class, 'destroy'])->name('registrars.destroy');

    // System Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/programs', [SettingsController::class, 'programs'])->name('settings.programs');
    Route::get('/settings/student-types', [SettingsController::class, 'studentTypes'])->name('settings.student-types');
    Route::get('/settings/document-management/document-weights', [SettingsController::class, 'documentWeightsPage'])->name('settings.document-management.document-weights');
    Route::get('/settings/document-management/student-weights', [SettingsController::class, 'studentWeightsPage'])->name('settings.document-management.student-weights');
    Route::get('/settings/document-management/urgency-levels', [SettingsController::class, 'urgencyLevelsPage'])->name('settings.document-management.urgency-levels');
    Route::post('/settings/programs', [SettingsController::class, 'addProgram'])->name('settings.programs.add');
    Route::delete('/settings/programs', [SettingsController::class, 'deleteProgram'])->name('settings.programs.delete');
    Route::post('/settings/student-types', [SettingsController::class, 'addStudentType'])->name('settings.student-types.add');
    Route::delete('/settings/student-types', [SettingsController::class, 'deleteStudentType'])->name('settings.student-types.delete');
    Route::post('/settings/document-weights', [SettingsController::class, 'updateDocumentWeights'])->name('settings.document-weights.update');
    Route::post('/settings/student-weights', [SettingsController::class, 'updateStudentWeights'])->name('settings.student-weights.update');
    Route::post('/settings/urgency-levels', [SettingsController::class, 'updateUrgencyLevels'])->name('settings.urgency-levels.update');
    Route::post('/settings/ai-forecast-prompt', [SettingsController::class, 'updateAiForecastPrompt'])->name('settings.ai-forecast-prompt.update');
});

// Notification routes
Route::middleware('auth')->group(function () {
    Route::get('/files/{requiredFile}', function (RequiredFile $requiredFile) {
        $user = auth()->user();
        $documentRequest = $requiredFile->documentRequest()->with('user')->firstOrFail();

        if (
            $user->hasRole('admin') ||
            $user->hasRole('registrar') ||
            ($user->hasRole('student') && $documentRequest->user_id === $user->id)
        ) {
            return Storage::disk('public')->response($requiredFile->file_path, $requiredFile->original_name);
        }

        abort(403);
    })->name('files.view');

    Route::get('/notifications/{id}/read', function ($id) {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if (!empty($notification->data['url'])) {
            return redirect($notification->data['url'])
                ->with('highlight', $notification->data['request_id'] ?? null);
        }

        if (auth()->user()->hasRole('student')) {
            return redirect()->route('student.requests.index')
                ->with('highlight', $notification->data['request_id']);
        }

        return redirect()->route('registrar.requests.index')
            ->with('highlight', $notification->data['request_id']);
    })->name('notifications.read');

    Route::post('/notifications/mark-all-read', function () {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    })->name('notifications.markAllRead');
});

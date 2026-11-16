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
    if (auth()->user()->hasRole('student')) {
        return redirect()->route('student.dashboard');
    }
    abort(403, 'Your account has no assigned role. Contact your school.');
})->name('dashboard');

// Student routes
Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
    Route::resource('requests', DocumentRequestController::class);
});

// Registrar routes
Route::middleware(['auth', 'role:registrar|admin'])->prefix('registrar')->name('registrar.')->group(function () {
    Route::get('/reports', \App\Http\Controllers\ReportsController::class)->name('reports');
    Route::get('/dashboard', [RegistrarDashboardController::class, 'index'])->name('dashboard');
    Route::get('/academic-calendar', [\App\Http\Controllers\Admin\AcademicCalendarController::class, 'index'])->name('academic-calendar');
    Route::get('/ai-forecast', AiForecastController::class)->name('ai-forecast');
    Route::post('/ai-forecast', AiForecastController::class)->middleware(['role:admin', 'throttle:3,1'])->name('ai-forecast.generate');
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
    Route::get('/reports', \App\Http\Controllers\ReportsController::class)->name('reports');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/ai-forecast', AiForecastController::class)->name('ai-forecast');
    Route::post('/ai-forecast', AiForecastController::class)->middleware('throttle:3,1')->name('ai-forecast.generate');
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
    Route::get('/settings/academic-calendar', [\App\Http\Controllers\Admin\AcademicCalendarController::class, 'index'])->name('settings.academic-calendar');
    Route::post('/settings/academic-calendar', [\App\Http\Controllers\Admin\AcademicCalendarController::class, 'store'])->name('settings.academic-calendar.save');
    Route::get('/settings/programs', [SettingsController::class, 'programs'])->name('settings.programs');
    Route::get('/settings/student-types', [SettingsController::class, 'studentTypes'])->name('settings.student-types');
    Route::get('/settings/document-management/document-weights', [SettingsController::class, 'documentWeightsPage'])->name('settings.document-management.document-weights');
    Route::get('/settings/document-management/student-weights', [SettingsController::class, 'studentWeightsPage'])->name('settings.document-management.student-weights');
    Route::get('/settings/document-management/urgency-levels', [SettingsController::class, 'urgencyLevelsPage'])->name('settings.document-management.urgency-levels');
    Route::post('/settings/programs', [SettingsController::class, 'addProgram'])->name('settings.programs.add');
    Route::delete('/settings/programs', [SettingsController::class, 'deleteProgram'])->name('settings.programs.delete');
    Route::post('/settings/student-types', [SettingsController::class, 'addStudentType'])->name('settings.student-types.add');
    Route::delete('/settings/student-types', [SettingsController::class, 'deleteStudentType'])->name('settings.student-types.delete');
    Route::post('/settings/document-types', [SettingsController::class, 'addDocumentType'])->name('settings.document-types.add');
    Route::put('/settings/document-types/{documentType}', [SettingsController::class, 'updateDocumentType'])->name('settings.document-types.update');
    Route::delete('/settings/document-types/{documentType}', [SettingsController::class, 'deleteDocumentType'])->name('settings.document-types.delete');
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
            abort_unless(Storage::disk('documents')->exists($requiredFile->file_path), 404);

            return Storage::disk('documents')->response(
                $requiredFile->file_path,
                $requiredFile->original_name,
                ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']
            );
        }

        abort(403);
    })->name('files.view');

    Route::get('/notifications/panel', function (\Illuminate\Http\Request $request) {
        $notificationFilter = $request->query('notification_filter') === 'unread' ? 'unread' : 'all';
        $unreadCount = $request->user()->unreadNotifications()->count();
        $portalNotifications = $request->user()->notifications()->when($notificationFilter === 'unread', fn ($query) => $query->whereNull('read_at'))->latest()->limit(8)->get();
        return response()->json(['html' => view('partials.notification-panel', compact('notificationFilter', 'unreadCount', 'portalNotifications'))->render(), 'unread' => $unreadCount])->header('Cache-Control', 'no-store');
    })->name('notifications.panel');

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
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        return back()->with('success', 'All notifications marked as read.');
    })->name('notifications.markAllRead');
    Route::post('/notifications/mark-all-unread', function () {
        auth()->user()->notifications()->whereNotNull('read_at')->update(['read_at' => null]);
        return back()->with('success', 'All notifications marked as unread.');
    })->name('notifications.markAllUnread');
    Route::post('/notifications/{id}/mark-read', function ($id) {
        auth()->user()->notifications()->findOrFail($id)->markAsRead();
        return back()->with('success', 'Notification marked as read.');
    })->name('notifications.markRead');
    Route::post('/notifications/{id}/mark-unread', function ($id) {
        auth()->user()->notifications()->findOrFail($id)->markAsUnread();
        return back()->with('success', 'Notification marked as unread.');
    })->name('notifications.markUnread');
});

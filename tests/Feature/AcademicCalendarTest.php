<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AcademicCalendar;
use App\Services\AiForecastCache;
use App\Services\SyntheticForecastDataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicCalendarTest extends TestCase
{
    use RefreshDatabase;
    private const YEAR = '11111111-1111-4111-8111-111111111111';

    private function loginAs(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create(); $user->assignRole($role); $this->actingAs($user);
        if ($role === 'admin' && !SystemSetting::where('key', AcademicCalendar::KEY)->exists()) {
            SystemSetting::set(AcademicCalendar::KEY, ['revision' => 0, 'years' => [self::YEAR => ['name' => '2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-05-31', 'archived' => false]], 'terms' => [], 'events' => [], 'history' => []]);
        }
        return $user;
    }

    private function term(array $changes = []): array
    {
        return array_merge(['revision' => 0, 'kind' => 'term', 'name' => 'First semester', 'year_id' => self::YEAR, 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-31'], $changes);
    }

    public function test_admin_can_save_edit_and_archive_semesters_with_history(): void
    {
        $admin = $this->loginAs('admin');
        $this->post(route('admin.settings.academic-calendar.save'), $this->term())->assertSessionHasNoErrors();
        $calendar = app(AcademicCalendar::class)->get(); $id = array_key_first($calendar['terms']);
        $this->post(route('admin.settings.academic-calendar.save'), $this->term(['id' => $id, 'revision' => 1, 'archived' => 1, 'name' => 'Semester 1']))->assertSessionHasNoErrors();
        $calendar = app(AcademicCalendar::class)->get();
        $this->assertSame(2, $calendar['revision']); $this->assertTrue($calendar['terms'][$id]['archived']);
        $this->assertSame('First semester', $calendar['history'][1]['before']['name']);
        $this->assertSame($admin->id, $calendar['history'][1]['actor_id']);
        $this->get(route('admin.settings.academic-calendar', ['panel' => 'semesters']))->assertOk()->assertSee('Semester 1');
    }

    public function test_roles_enforce_read_only_registrar_and_no_student_access(): void
    {
        $this->loginAs('registrar');
        $this->get(route('registrar.academic-calendar'))->assertOk()->assertDontSee('Save semester');
        $this->post(route('admin.settings.academic-calendar.save'), $this->term())->assertForbidden();
        $this->loginAs('student');
        $this->get(route('registrar.academic-calendar'))->assertForbidden();
        $this->get(route('admin.settings.academic-calendar'))->assertForbidden();
        $this->assertNull(SystemSetting::get(AcademicCalendar::KEY));
    }

    public function test_invalid_dates_duplicates_and_stale_edits_do_not_change_calendar(): void
    {
        $this->loginAs('admin'); $url = route('admin.settings.academic-calendar.save');
        $this->postJson($url, $this->term(['ends_on' => '2026-07-01']))->assertUnprocessable();
        $this->post($url, $this->term())->assertSessionHasNoErrors();
        $snapshot = app(AcademicCalendar::class)->get();
        $this->postJson($url, $this->term(['name' => 'Another']))->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->postJson($url, $this->term(['revision' => 1]))->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertSame($snapshot, app(AcademicCalendar::class)->get());
    }

    public function test_events_can_overlap_but_cannot_escape_semester_and_shrinking_term_is_rejected(): void
    {
        $this->loginAs('admin'); $url = route('admin.settings.academic-calendar.save');
        $this->post($url, $this->term());
        $id = array_key_first(app(AcademicCalendar::class)->get()['terms']);
        $event = ['kind' => 'event', 'revision' => 1, 'term_id' => $id, 'name' => 'Enrollment', 'type' => 'enrollment', 'starts_on' => '2026-08-01', 'ends_on' => '2026-08-14'];
        $this->post($url, $event)->assertSessionHasNoErrors();
        $this->post($url, array_merge($event, ['revision' => 2, 'type' => 'holiday', 'office_closed' => 1]))->assertSessionHasNoErrors();
        $this->postJson($url, array_merge($event, ['revision' => 3, 'ends_on' => '2027-01-01']))->assertUnprocessable();
        $this->postJson($url, $this->term(['id' => $id, 'revision' => 3, 'starts_on' => '2026-09-01']))->assertUnprocessable();
        $this->assertSame(3, app(AcademicCalendar::class)->get()['revision']);
    }

    public function test_graduation_uses_academic_year_bounds_and_preserves_edit_integrity(): void
    {
        $this->loginAs('admin'); $url = route('admin.settings.academic-calendar.save');
        $this->post($url, $this->term())->assertSessionHasNoErrors();
        $id = array_key_first(app(AcademicCalendar::class)->get()['terms']);
        $event = ['kind' => 'event', 'revision' => 1, 'term_id' => $id, 'name' => 'Graduation', 'type' => 'graduation', 'starts_on' => '2027-05-04', 'ends_on' => '2027-05-07'];
        $this->post($url, $event)->assertSessionHasNoErrors();
        $eventId = array_key_first(app(AcademicCalendar::class)->get()['events']);
        $this->postJson($url, array_merge($event, ['id' => $eventId, 'revision' => 2, 'type' => 'exams']))->assertUnprocessable();
        $this->postJson($url, array_merge($event, ['revision' => 2, 'ends_on' => '2027-06-01']))->assertUnprocessable();
        $this->post($url, $this->term(['id' => $id, 'revision' => 2, 'ends_on' => '2026-11-30']))->assertSessionHasNoErrors();
        $this->postJson($url, ['kind' => 'year', 'id' => self::YEAR, 'revision' => 3, 'name' => '2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-04-30'])->assertUnprocessable();
        $this->assertSame(3, app(AcademicCalendar::class)->get()['revision']);
    }

    public function test_calendar_validation_uses_alerts_without_unrelated_revision_advice(): void
    {
        $this->loginAs('admin');
        $this->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['term_id' => 'Invalid event dates.']))])
            ->get(route('admin.settings.academic-calendar', ['form' => 'event']))
            ->assertOk()->assertSee('alert alert-danger', false)->assertSee('Invalid event dates.')->assertDontSee('Reload this page before editing again.');
    }

    public function test_enrollment_can_precede_classes_but_stays_within_year_and_semester_end(): void
    {
        $this->loginAs('admin'); $url = route('admin.settings.academic-calendar.save');
        $this->post($url, $this->term(['starts_on' => '2026-06-22', 'ends_on' => '2026-10-24']))->assertSessionHasNoErrors();
        $id = array_key_first(app(AcademicCalendar::class)->get()['terms']);
        $event = ['kind' => 'event', 'revision' => 1, 'name' => 'Enrollment', 'term_id' => $id, 'type' => 'enrollment', 'starts_on' => '2026-06-08', 'ends_on' => '2026-06-20'];
        $this->post($url, $event)->assertSessionHasNoErrors();
        $this->postJson($url, array_merge($event, ['revision' => 2, 'starts_on' => '2026-05-31']))->assertUnprocessable();
        $this->postJson($url, array_merge($event, ['revision' => 2, 'ends_on' => '2026-10-25']))->assertUnprocessable();
        $this->postJson($url, array_merge($event, ['revision' => 2, 'type' => 'exams']))->assertUnprocessable();
        $this->post($url, $this->term(['id' => $id, 'revision' => 2, 'starts_on' => '2026-06-23', 'ends_on' => '2026-10-24']))->assertSessionHasNoErrors();
    }

    public function test_calendar_edits_make_forecast_cache_stale(): void
    {
        $this->loginAs('admin'); $cache = app(AiForecastCache::class);
        $cache->put(['generated_at' => now()->toIso8601String()]);
        $this->assertTrue($cache->isFresh($cache->get(), 60));
        $this->post(route('admin.settings.academic-calendar.save'), $this->term());
        $this->assertFalse($cache->isFresh($cache->get(), 60));
    }

    public function test_synthetic_dataset_is_reproducible_chronological_labeled_and_does_not_write_database(): void
    {
        $generator = new SyntheticForecastDataset; $before = SystemSetting::count();
        $data = $generator->build(42);
        $this->assertSame($data, $generator->build(42));
        $this->assertNotSame($data['rows'], $generator->build(43)['rows']);
        $this->assertSame($before, SystemSetting::count());
        $this->assertCount(6, $data['calendar']['terms']);
        $bySplit = collect($data['rows'])->groupBy('split');
        $this->assertLessThan($bySplit['validation']->min('week_start'), $bySplit['train']->max('week_start'));
        $this->assertLessThan($bySplit['test']->min('week_start'), $bySplit['validation']->max('week_start'));
        foreach ($data['rows'] as $row) {
            $this->assertSame('synthetic', $row['source']);
            $this->assertGreaterThanOrEqual(0, $row['requests']);
            $this->assertLessThanOrEqual(5, $row['working_days']);
        }
    }

    public function test_dataset_command_requires_isolation_and_refuses_overwrite(): void
    {
        $originalStorage = app()->storagePath();
        app()->useStoragePath($originalStorage.'/framework/testing/calendar-'.\Illuminate\Support\Str::uuid());
        try {
        $this->artisan('forecast:demo-dataset', ['--seed' => 99123])->assertSuccessful();
        $file = storage_path('app/private/forecast-demo/seed-99123/weekly-requests.csv');
        $this->assertFileExists($file); $hash = hash_file('sha256', $file);
        $this->artisan('forecast:demo-dataset', ['--seed' => 99123])->assertFailed();
        $this->assertSame($hash, hash_file('sha256', $file));
        $this->artisan('forecast:demo-dataset', ['--seed' => '../invalid'])->assertFailed();
        app()->detectEnvironment(fn () => 'local');
        $this->artisan('forecast:demo-dataset', ['--seed' => 99124])->assertFailed();
        } finally {
            app()->useStoragePath($originalStorage);
        }
    }

    public function test_month_panel_handles_leap_year_and_invalid_months(): void
    {
        $this->loginAs('admin');
        app(AcademicCalendar::class)->save(['revision' => 0, 'kind' => 'year', 'name' => '2023-2024', 'starts_on' => '2023-06-01', 'ends_on' => '2024-05-31'], auth()->id());
        $this->get(route('admin.settings.academic-calendar', ['month' => '2024-02']))->assertOk()
            ->assertSee('February 2024')->assertSee('datetime="2024-02-29"', false)
            ->assertSee('month=2024-01', false)->assertSee('month=2024-03', false);
        $this->getJson(route('admin.settings.academic-calendar', ['month' => '2024-13']))->assertUnprocessable();
        $this->getJson(route('admin.settings.academic-calendar', ['month' => ['bad']]))->assertUnprocessable();
    }

    public function test_calendar_only_renders_the_editor_requested_by_the_admin(): void
    {
        $this->loginAs('admin');
        $this->get(route('admin.settings.academic-calendar'))->assertOk()->assertSee('calendar-month-grid', false)->assertDontSee('id="term-name"', false)->assertDontSee('id="event-name"', false)->assertDontSee('calendar-history', false);
        $this->get(route('admin.settings.academic-calendar', ['form' => 'event']))->assertOk()->assertSee('id="event-name"', false)->assertDontSee('id="term-name"', false)->assertDontSee('calendar-month-grid', false);
        $this->get(route('admin.settings.academic-calendar', ['form' => 'term']))->assertOk()->assertSee('id="term-name"', false)->assertDontSee('id="event-name"', false);
    }

    public function test_calendar_shows_multiday_events_and_keeps_registrar_read_only(): void
    {
        $admin = $this->loginAs('admin'); $service = app(AcademicCalendar::class);
        $service->save($this->term(), $admin->id);
        $id = array_key_first($service->get()['terms']);
        $service->save(['kind' => 'event', 'revision' => 1, 'name' => 'Office holiday', 'term_id' => $id, 'type' => 'holiday', 'starts_on' => '2026-08-31', 'ends_on' => '2026-09-02', 'office_closed' => true], $admin->id);
        $this->get(route('admin.settings.academic-calendar', ['month' => '2026-09']))->assertOk()->assertSee('Office holiday')->assertSee('Office closed');
        $this->loginAs('registrar');
        $this->get(route('registrar.academic-calendar', ['month' => '2026-09']))->assertOk()->assertSee('Office holiday')->assertDontSee('edit_event=', false)->assertDontSee('Save event');
    }

    public function test_year_filter_limits_navigation_and_semester_dates(): void
    {
        $this->loginAs('admin');
        $url = route('admin.settings.academic-calendar', ['year' => self::YEAR, 'month' => '2026-06']);
        $this->get($url)->assertOk()->assertSee('June 2026')->assertDontSee('month=2026-05', false)->assertSee('month=2026-07', false);
        $this->get(route('admin.settings.academic-calendar', ['year' => self::YEAR, 'month' => '2027-05']))->assertOk()->assertSee('May 2027')->assertDontSee('month=2027-06', false);
        $this->get(route('admin.settings.academic-calendar', ['year' => self::YEAR, 'month' => '2029-11']))->assertOk()->assertSee('June 2026');
        $this->postJson(route('admin.settings.academic-calendar.save'), $this->term(['starts_on' => '2026-05-01']))->assertUnprocessable()->assertJsonValidationErrors('year_id');
    }

    public function test_year_rename_updates_term_labels_but_dates_cannot_exclude_terms(): void
    {
        $admin = $this->loginAs('admin'); $service = app(AcademicCalendar::class);
        $service->save($this->term(), $admin->id);
        $year = ['kind' => 'year', 'id' => self::YEAR, 'revision' => 1, 'name' => 'AY 2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-05-31'];
        $this->postJson(route('admin.settings.academic-calendar.save'), array_merge($year, ['starts_on' => '2026-09-01']))->assertUnprocessable();
        $this->post(route('admin.settings.academic-calendar.save'), $year)->assertSessionHasNoErrors();
        $this->assertSame('AY 2026-2027', collect($service->get()['terms'])->first()['academic_year']);
    }

    public function test_legacy_year_labels_are_grouped_without_mutating_saved_records_on_get(): void
    {
        $this->loginAs('admin');
        $legacy = ['revision' => 5, 'terms' => ['old-term' => ['academic_year' => '2026-2027', 'name' => 'First semester', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-31', 'archived' => false]], 'events' => [], 'history' => []];
        SystemSetting::set(AcademicCalendar::KEY, $legacy);
        $calendar = app(AcademicCalendar::class)->get();
        $this->assertCount(1, $calendar['years']);
        $this->assertSame('2026-06-01', collect($calendar['years'])->first()['starts_on']);
        $this->assertSame('2027-05-31', collect($calendar['years'])->first()['ends_on']);
        $this->assertSame($legacy, SystemSetting::get(AcademicCalendar::KEY));
    }
}

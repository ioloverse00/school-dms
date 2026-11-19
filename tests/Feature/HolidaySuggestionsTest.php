<?php

namespace Tests\Feature;

use App\Models\{SystemSetting, User};
use App\Services\{AcademicCalendar, HolidaySuggestions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HolidaySuggestionsTest extends TestCase
{
    use RefreshDatabase;
    private const YEAR = '11111111-1111-4111-8111-111111111111';
    private const TERM = '22222222-2222-4222-8222-222222222222';
    private const URL = 'https://pco.gov.ph/issuances/test-proclamation/';

    private function setupCalendar(string $role = 'admin'): array
    {
        config(['holidays.provider' => 'openai', 'holidays.reference_pages' => []]);
        Role::findOrCreate($role); $user = User::factory()->create(); $user->assignRole($role); $this->actingAs($user);
        $calendar = ['revision' => 0, 'years' => [self::YEAR => ['name' => '2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-05-31', 'archived' => false]], 'terms' => [self::TERM => ['name' => 'First semester', 'year_id' => self::YEAR, 'academic_year' => '2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2026-12-31', 'archived' => false]], 'events' => [], 'history' => []];
        SystemSetting::set(AcademicCalendar::KEY, $calendar);
        return $calendar;
    }

    private function response(array $changes = []): array
    {
        $holiday = array_merge(['name' => 'Test national holiday', 'date' => '2026-06-12', 'classification' => 'regular', 'source_url' => self::URL], $changes);
        return ['status' => 'completed', 'output' => [['type' => 'web_search_call', 'status' => 'completed', 'action' => ['sources' => [['url' => self::URL]]]], ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['coverage_note' => 'Some dates await proclamation.', 'holidays' => [$holiday]])]]]]];
    }

    public function test_disabled_lookup_shows_setup_state_and_never_calls_provider(): void
    {
        $before = $this->setupCalendar(); config(['openai.holiday_suggestions_enabled' => false]); Http::fake();
        $this->get(route('admin.settings.academic-calendar', ['panel' => 'holidays', 'year' => self::YEAR]))->assertOk()->assertSee('Holiday updates are not configured yet.');
        $this->postJson(route('admin.settings.academic-calendar.holidays.check'), ['year_id' => self::YEAR])->assertUnprocessable();
        Http::assertNothingSent(); $this->assertSame($before, SystemSetting::get(AcademicCalendar::KEY));
    }

    public function test_only_national_ncr_and_quezon_city_scopes_are_kept(): void
    {
        $calendar = $this->setupCalendar();
        foreach (['national', 'ncr', 'quezon_city', 'quezon_province', 'cebu'] as $scope) {
            $check = app(HolidaySuggestions::class)->validateResponse($this->response(['scope' => $scope]), $calendar['years'][self::YEAR]);
            $this->assertCount(in_array($scope, ['national', 'ncr', 'quezon_city'], true) ? 1 : 0, $check['holidays']);
            if ($check['holidays']) $this->assertSame($scope, $check['holidays'][0]['scope']);
        }
    }

    public function test_gazette_is_accepted_as_a_cited_primary_source(): void
    {
        $calendar = $this->setupCalendar();
        $url = 'https://www.officialgazette.gov.ph/nationwide-holidays/2026/';
        $response = $this->response(['source_url' => $url]);
        $response['output'][0]['action']['sources'] = [['url' => $url]];
        $check = app(HolidaySuggestions::class)->validateResponse($response, $calendar['years'][self::YEAR]);
        $this->assertSame($url, $check['holidays'][0]['source_url']);
        $check['checked_at'] = now()->toIso8601String();
        SystemSetting::set(HolidaySuggestions::KEY, [self::YEAR => $check]);
        $this->get(route('admin.settings.academic-calendar', ['panel' => 'holidays', 'year' => self::YEAR]))
            ->assertOk()->assertSee('Official Gazette source')->assertDontSee('PCO · Government source');
    }

    public function test_source_backed_lookup_does_not_save_events_until_admin_reviews(): void
    {
        $before = $this->setupCalendar(); config(['openai.holiday_suggestions_enabled' => true, 'openai.api_key' => 'fake-test-key', 'openai.model' => 'test-model']);
        Http::fake(['api.openai.com/*' => Http::response($this->response())]);
        $this->post(route('admin.settings.academic-calendar.holidays.check'), ['year_id' => self::YEAR])->assertSessionHasNoErrors();
        $this->assertSame($before, SystemSetting::get(AcademicCalendar::KEY));
        Http::assertSent(fn($request) => $request['store'] === false && $request['tool_choice'] === 'required' && str_contains($request['input'], '2027-05-31'));
        $check = app(HolidaySuggestions::class)->saved(self::YEAR);
        $this->get(route('admin.settings.academic-calendar', ['panel' => 'holidays', 'year' => self::YEAR]))->assertOk()->assertSee('PCO · Government source')->assertSee('Test national holiday');
        $input = ['year_id' => self::YEAR, 'term_id' => self::TERM, 'suggestion_id' => $check['holidays'][0]['id'], 'revision' => 0];
        $this->post(route('admin.settings.academic-calendar.holidays.apply'), $input)->assertSessionHasNoErrors();
        $saved = app(AcademicCalendar::class)->get(); $this->assertCount(1, $saved['events']);
        $this->assertTrue(array_values($saved['events'])[0]['office_closed']);
        $eventId = array_key_first($saved['events']);
        app(AcademicCalendar::class)->save(array_merge($saved['events'][$eventId], ['kind' => 'event', 'id' => $eventId, 'revision' => $saved['revision'], 'office_closed' => false]), auth()->id());
        $this->assertTrue(app(AcademicCalendar::class)->get()['events'][$eventId]['office_closed']);
        $this->postJson(route('admin.settings.academic-calendar.holidays.apply'), $input + ['name' => 'Tampered'])->assertUnprocessable();
        $this->assertCount(1, app(AcademicCalendar::class)->get()['events']);
    }

    public function test_untrusted_sources_and_invalid_dates_are_rejected(): void
    {
        $calendar = $this->setupCalendar();
        foreach ([['source_url' => 'https://pco.gov.ph.evil.test/fake'], ['date' => '2026-02-30']] as $changes) {
            try { app(HolidaySuggestions::class)->validateResponse($this->response($changes), $calendar['years'][self::YEAR]); $this->fail('Invalid suggestion accepted'); }
            catch (\RuntimeException $error) { $this->assertTrue(true); }
        }
        $pending = app(HolidaySuggestions::class)->validateResponse($this->response(['date' => null]), $calendar['years'][self::YEAR]);
        $this->assertNull($pending['holidays'][0]['date']);
    }

    public function test_holiday_can_be_added_without_any_semester_and_appears_in_calendar(): void
    {
        $calendar = $this->setupCalendar();
        $calendar['terms'] = [];
        SystemSetting::set(AcademicCalendar::KEY, $calendar);
        config(['openai.holiday_suggestions_enabled' => true, 'openai.api_key' => 'fake', 'openai.model' => 'test']);
        Http::fake(['api.openai.com/*' => Http::response($this->response(['classification' => 'special_non_working']))]);
        $this->post(route('admin.settings.academic-calendar.holidays.check'), ['year_id' => self::YEAR])->assertSessionHasNoErrors();
        $check = app(HolidaySuggestions::class)->saved(self::YEAR);
        $this->post(route('admin.settings.academic-calendar.holidays.apply'), ['year_id' => self::YEAR, 'suggestion_id' => $check['holidays'][0]['id'], 'revision' => 0])->assertSessionHasNoErrors();
        $event = array_values(app(AcademicCalendar::class)->get()['events'])[0];
        $this->assertNull($event['term_id']);
        $this->assertSame(self::YEAR, $event['year_id']);
        $this->assertTrue($event['office_closed']);
        $this->get(route('admin.settings.academic-calendar', ['year' => self::YEAR, 'month' => '2026-06']))->assertOk()->assertSee('Test national holiday')->assertSee('Academic year holiday');
    }

    public function test_failed_lookup_preserves_last_successful_snapshot(): void
    {
        $this->setupCalendar(); config(['openai.holiday_suggestions_enabled' => true, 'openai.api_key' => 'fake', 'openai.model' => 'test']);
        $old = [self::YEAR => ['holidays' => [], 'checked_at' => '2026-01-01T00:00:00Z']]; SystemSetting::set(HolidaySuggestions::KEY, $old);
        Http::fake(['api.openai.com/*' => Http::response([], 503)]);
        $this->postJson(route('admin.settings.academic-calendar.holidays.check'), ['year_id' => self::YEAR])->assertUnprocessable()->assertJsonValidationErrors('holidays');
        $this->assertSame($old, SystemSetting::get(HolidaySuggestions::KEY));
    }

    public function test_annual_results_are_filtered_and_encoded_citation_suffix_is_preserved(): void
    {
        $calendar = $this->setupCalendar();
        $response = $this->response(['date' => '2027-06-01']);
        $this->assertCount(0, app(HolidaySuggestions::class)->validateResponse($response, $calendar['years'][self::YEAR])['holidays']);
        $response = $this->response();
        $response['output'][0]['action']['sources'][0]['url'] = self::URL.'%20';
        $check = app(HolidaySuggestions::class)->validateResponse($response, $calendar['years'][self::YEAR]);
        $this->assertSame(self::URL.'%20', $check['holidays'][0]['source_url']);
    }

    public function test_registrars_cannot_check_or_apply_holidays(): void
    {
        $this->setupCalendar('registrar'); Http::fake();
        foreach (['check', 'apply'] as $action) $this->postJson(route('admin.settings.academic-calendar.holidays.'.$action), ['year_id' => self::YEAR])->assertForbidden();
        Http::assertNothingSent();
    }

}

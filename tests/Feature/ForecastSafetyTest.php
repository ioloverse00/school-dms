<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ForecastOutputValidator;
use App\Services\DocumentTrendForecaster;
use App\Services\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ForecastSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 31)->setTime(0, 0));
        config(['gemini.enabled' => true, 'gemini.api_key' => 'test-secret', 'gemini.model' => 'test-model']);
        Http::preventStrayRequests();
        foreach (['admin', 'registrar', 'student'] as $role) { Role::findOrCreate($role); }
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role));
        return $user;
    }

    private function validForecast(): array
    {
        $type = DocumentType::firstOrCreate(['name' => 'Transcript'], ['category' => 'academic', 'weight' => 1]);
        return [
            'forecast_months' => 2, 'months' => ['2026-04', '2026-05'], 'overall_forecast' => [10, 20],
            'per_document_type' => [['document_type_id' => $type->id, 'name' => 'Provider name', 'forecast' => [10, 20]]],
            'volume_forecast' => ['period' => '2026-04..2026-05', 'total_requests' => 30, 'notes' => []],
            'peak_period_analysis' => ['upcoming_peaks_30_60_days' => [], 'historical_peaks' => [], 'why_peaks_happen' => []],
            'top_documents' => [], 'processing_workload' => ['notes' => []],
            'staffing_recommendations' => ['normal_period' => [], 'peak_period' => []], 'insights' => [], 'assumptions' => [],
        ];
    }

    private function fakeOutput(array $output): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($output)]]]]]])]);
    }

    public function test_generation_uses_saved_calendar_dates_instead_of_old_fixed_dates(): void
    {
        SystemSetting::set('academic_calendar', ['revision' => 7, 'years' => [], 'terms' => [], 'history' => [], 'events' => [
            'graduation' => ['name' => 'UAT graduation', 'type' => 'graduation', 'starts_on' => '2026-05-04', 'ends_on' => '2026-05-07', 'office_closed' => false, 'archived' => false],
            'past' => ['name' => 'Historical enrollment', 'type' => 'enrollment', 'starts_on' => '2025-06-08', 'ends_on' => '2025-06-20', 'office_closed' => false, 'archived' => true],
        ]]);
        $this->fakeOutput($this->validForecast());
        app(DocumentTrendForecaster::class)->forecastWithHistory(24, 2);
        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];
            return str_contains($prompt, 'UAT graduation') && str_contains($prompt, '2026-05-04')
                && str_contains($prompt, 'Historical enrollment')
                && !str_contains($prompt, 'The school year runs from June 15');
        });
    }

    public function test_get_never_generates_even_with_force_and_registrar_can_read(): void
    {
        $this->actingAs($this->staff('registrar'))->getJson('/registrar/ai-forecast?force=1')
            ->assertStatus(202)->assertJsonPath('pending', true);
        Http::assertNothingSent();
        $this->assertDatabaseMissing('system_settings', ['key' => 'ai_document_forecast_cache']);
    }

    public function test_generation_is_explicit_cached_shared_and_uses_header_key(): void
    {
        $this->fakeOutput($this->validForecast());
        $admin = $this->staff('admin');
        $this->actingAs($admin)->postJson('/admin/ai-forecast?months=2')
            ->assertOk()->assertJsonPath('forecast.per_document_type.0.name', 'Transcript')
            ->assertJsonPath('forecast.summary.future_requests_total', 30);
        $this->actingAs($this->staff('registrar'))->getJson('/registrar/ai-forecast?months=2')->assertOk()->assertJsonPath('cached', true);
        $this->actingAs($admin)->postJson('/admin/ai-forecast?months=2')->assertOk()->assertJsonPath('cached', true);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => !str_contains($request->url(), 'test-secret') && $request->hasHeader('x-goog-api-key', 'test-secret'));
        $this->travel(13)->hours();
        $this->getJson('/admin/ai-forecast?months=2')->assertOk()->assertJsonPath('stale', true);
        Http::assertSentCount(1);
    }

    public function test_lock_cooldown_and_parameter_validation_prevent_provider_work(): void
    {
        $this->actingAs($this->staff('admin'));
        $this->postJson('/admin/ai-forecast', ['months' => 37, 'history' => 1, 'notes' => str_repeat('a', 2001)])
            ->assertUnprocessable()->assertJsonValidationErrors(['months', 'history', 'notes']);
        $lock = Cache::lock('ai-forecast-generation', 180); $lock->get();
        $this->postJson('/admin/ai-forecast')->assertStatus(409);
        $lock->release();
        Cache::put('ai-forecast-cooldown', true, 60);
        $this->postJson('/admin/ai-forecast')->assertStatus(429);
        Http::assertNothingSent();
    }

    public function test_provider_failure_is_safe_does_not_overwrite_cache_and_releases_lock(): void
    {
        SystemSetting::set('ai_document_forecast_cache', ['original' => true]);
        Http::fake(['*' => Http::response('test-secret confidential-provider-body', 503)]);
        $this->actingAs($this->staff('admin'))->postJson('/admin/ai-forecast')
            ->assertStatus(503)->assertJsonPath('message', 'Forecast unavailable. Try again later.')
            ->assertJsonStructure(['reference'])->assertDontSee('test-secret')->assertDontSee('confidential');
        $this->assertSame(['original' => true], SystemSetting::get('ai_document_forecast_cache'));
        $lock = Cache::lock('ai-forecast-generation', 180);
        $this->assertTrue($lock->get()); $lock->release();
        Http::assertSentCount(1);
    }

    public function test_students_cannot_read_or_generate_staff_forecasts(): void
    {
        $this->actingAs($this->staff('student'));
        foreach (['admin', 'registrar'] as $prefix) {
            $this->getJson('/'.$prefix.'/ai-forecast')->assertForbidden();
            $this->postJson('/'.$prefix.'/ai-forecast')->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_schema_rejects_bad_values_unknown_ids_lengths_months_and_inconsistent_totals(): void
    {
        $valid = $this->validForecast();
        $types = [['document_type_id' => $valid['per_document_type'][0]['document_type_id'], 'name' => 'Transcript']];
        $bad = [];
        $value = $valid; $value['overall_forecast'][0] = -1; $bad[] = $value;
        $value = $valid; $value['overall_forecast'][0] = 10.5; $bad[] = $value;
        $value = $valid; $value['months'] = ['2026-05', '2026-04']; $bad[] = $value;
        $value = $valid; $value['per_document_type'][0]['document_type_id'] = 9999; $bad[] = $value;
        $value = $valid; $value['per_document_type'][] = $value['per_document_type'][0]; $bad[] = $value;
        $value = $valid; $value['per_document_type'][0]['forecast'] = [10]; $bad[] = $value;
        $value = $valid; $value['overall_forecast'][0] = 9; $bad[] = $value;
        $value = $valid; $value['volume_forecast']['total_requests'] = 29; $bad[] = $value;
        $value = $valid; $value['insights'] = ['unexpected' => new \stdClass()]; $bad[] = $value;
        $value = $valid; $value['per_document_type'][0]['forecast'] = ['10', 20]; $bad[] = $value;
        foreach ($bad as $output) {
            try {
                app(ForecastOutputValidator::class)->validate($output, $types, 2, $valid['months']);
                $this->fail('Malformed forecast was accepted.');
            } catch (\RuntimeException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
    }

    public function test_invalid_provider_output_is_not_persisted(): void
    {
        $output = $this->validForecast(); $output['overall_forecast'] = [-5, 20];
        $this->fakeOutput($output);
        $this->actingAs($this->staff('admin'))->postJson('/admin/ai-forecast?months=2')->assertStatus(503);
        $this->assertDatabaseMissing('system_settings', ['key' => 'ai_document_forecast_cache']);
    }

    public function test_mixed_text_json_parser_handles_quotes_escapes_and_braces_in_strings(): void
    {
        $data = ['note' => 'Quoted "}" and [brackets] with backslash \\ then {', 'nested' => [['value' => 1]]];
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Here is JSON: '.json_encode($data).' End.']]]]]])]);
        $this->assertSame($data, app(GeminiClient::class)->generateJson('Test'));
    }

    public function test_month_end_history_has_no_skipped_or_duplicate_months_and_prompt_is_contiguous(): void
    {
        $this->fakeOutput($this->validForecast());
        $forecaster = app(DocumentTrendForecaster::class);
        $history = $forecaster->perDocumentTypeHistory(6);
        $this->assertSame(['2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03'], $history[0]['history']['labels']);
        $forecaster->forecast(6, 2);
        Http::assertSent(fn ($request) => str_contains($request['contents'][0]['parts'][0]['text'], '["2026-04","2026-05"]'));
    }

    public function test_february_workload_uses_february_days_even_when_today_is_the_31st(): void
    {
        config(['forecasting.business_days_only' => false, 'forecasting.peak_day_multiplier' => 1.4]);
        $summary = app(\App\Services\ForecastSummaryBuilder::class)->build([
            'months' => ['2026-02'], 'overall_forecast' => [280], 'per_document_type' => [],
        ]);
        $this->assertSame(14, $summary['expected_processing_workload']['peak_day']['estimated_requests']);
        $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        foreach ([1 => 'Sun', 2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat'] as $raw => $name) {
            $this->assertSame($name, $names[\App\Services\ForecastSummaryBuilder::mysqlWeekdayIndex($raw)]);
        }
    }

    public function test_month_rollover_preserves_previous_forecast_as_stale_without_a_provider_call(): void
    {
        $this->fakeOutput($this->validForecast());
        $this->actingAs($this->staff('admin'))->postJson('/admin/ai-forecast?months=2')->assertOk();
        $this->travel(1)->days();
        $this->getJson('/admin/ai-forecast?months=2')->assertOk()->assertJsonPath('stale', true)->assertJsonPath('forecast.months', ['2026-04', '2026-05']);
        Http::assertSentCount(1);
    }

    public function test_disabled_forecasting_and_corrupt_cache_do_not_trigger_provider_work(): void
    {
        config(['gemini.enabled' => false]);
        SystemSetting::set('ai_document_forecast_cache', ['forecast' => ['months' => ['bad']]]);
        $this->actingAs($this->staff('admin'))->getJson('/admin/ai-forecast')->assertStatus(202);
        $this->postJson('/admin/ai-forecast')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_registrar_can_read_forecasts_but_cannot_trigger_generation(): void
    {
        $this->fakeOutput($this->validForecast());
        $this->actingAs($this->staff('registrar'))->postJson('/registrar/ai-forecast?months=2')->assertForbidden();
        $this->getJson('/registrar/ai-forecast?months=2')->assertStatus(202);
        $this->get('/registrar/dashboard')->assertOk()->assertSee(route('registrar.ai-forecast'), false)
            ->assertDontSee('id="aiForecastGenerate"', false)->assertSee('Updates daily at 12 AM and 12 PM');
        Http::assertNothingSent();
    }

    public function test_catalog_changes_during_generation_do_not_replace_the_input_snapshot(): void
    {
        $output = $this->validForecast();
        $originalId = $output['per_document_type'][0]['document_type_id'];
        Http::fake(function () use ($output) {
            DocumentType::create(['name' => 'New document during generation', 'category' => 'academic', 'weight' => 1]);
            return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($output)]]]]]]);
        });
        $this->actingAs($this->staff('admin'))->postJson('/admin/ai-forecast?months=2&include_history=1')
            ->assertOk()->assertJsonCount(1, 'history.per_document_type')
            ->assertJsonPath('history.per_document_type.0.document_type_id', $originalId);
        $saved = SystemSetting::get('ai_document_forecast_cache');
        $this->assertCount(1, $saved['history']['per_document_type']);
        $this->assertSame(array_fill(0, 24, 0), $saved['history']['overall']['values']);
        $this->getJson('/admin/ai-forecast?months=2&include_history=1')->assertOk()
            ->assertJsonPath('cached', true)->assertJsonCount(1, 'history.per_document_type');
        Http::assertSentCount(1);
    }

    public function test_generation_derives_totals_and_uses_fast_json_mode_for_flash(): void
    {
        $output = $this->validForecast();
        $output['overall_forecast'] = [999, 999]; $output['volume_forecast']['total_requests'] = 1;
        config(['gemini.model' => 'gemini-2.5-flash']);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($output)]]]]]])]);
        $this->actingAs($this->staff('admin'))->postJson('/admin/ai-forecast?months=2')->assertOk()
            ->assertJsonPath('forecast.overall_forecast', [10, 20])->assertJsonPath('forecast.volume_forecast.total_requests', 30);
        Http::assertSent(fn($request) => $request['generationConfig']['responseMimeType'] === 'application/json'
            && $request['generationConfig']['thinkingConfig']['thinkingBudget'] === 0
            && $request['generationConfig']['responseSchema']['type'] === 'OBJECT'
            && $request['generationConfig']['responseSchema']['properties']['months']['description'] === 'Exactly 2 entries.'
            && !isset($request['generationConfig']['responseSchema']['properties']['months']['minItems'])
            && in_array('per_document_type', $request['generationConfig']['responseSchema']['required'], true));
    }

    public function test_failure_logs_distinguish_safe_reasons_without_secret_or_body_disclosure(): void
    {
        $output = $this->validForecast();
        $invalid = $output; unset($invalid['volume_forecast']['notes']);
        $cases = [
            ['configuration', 'provider_configuration', null, null],
            ['provider', 'provider_http', Http::response('private-body test-secret', 429), 429],
            ['parsing', 'response_json', Http::response(['candidates' => [['content' => ['parts' => [['text' => 'private-body test-secret']]]]]]), null],
            ['validation', 'schema_invalid', Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($invalid)]]]]]]), null],
        ];
        $this->actingAs($this->staff('admin'));
        $contexts = [];
        $providerResponse = null;
        Http::fake(function () use (&$providerResponse) { return $providerResponse; });
        \Illuminate\Support\Facades\Log::shouldReceive('warning')->times(count($cases))
            ->andReturnUsing(function ($message, $context) use (&$contexts) { $contexts[] = $context; });
        foreach ($cases as [$stage, $reason, $response, $status]) {
            Cache::flush();
            config(['gemini.api_key' => $response ? 'test-secret' : '']);
            $providerResponse = $response;
            $response = $this->postJson('/admin/ai-forecast?months=2')->assertStatus(503)
                ->assertDontSee('test-secret')->assertDontSee('private-body');
            $context = end($contexts);
            $this->assertSame($reason, $context['reason']);
            $this->assertSame($stage, $context['stage']);
            $this->assertSame($response->json('reference'), $context['reference']);
            $this->assertSame($status, $context['http_status'] ?? null);
            $this->assertStringNotContainsString('test-secret', json_encode($context));
            $this->assertStringNotContainsString('private-body', json_encode($context));
        }
    }

    public function test_cli_uses_the_same_safe_diagnostics(): void
    {
        config(['gemini.api_key' => '']);
        \Illuminate\Support\Facades\Log::shouldReceive('warning')->once()->withArgs(fn ($message, $context) =>
            $message === 'Forecast command failed.' && $context['reason'] === 'provider_configuration'
            && $context['stage'] === 'configuration' && isset($context['reference'])
            && !str_contains(json_encode($context), 'test-secret'));
        $this->artisan('ai:forecast-documents', ['--months' => 2])->assertExitCode(1);
        Http::assertNothingSent();
    }
}


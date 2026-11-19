<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\{DocumentTrendForecaster, ForecastRefreshSchedule};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ForecastScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_runs_twice_daily_and_next_time_rolls_over(): void
    {
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $event = collect($events)->first(fn($event) => str_contains($event->command ?? '', 'ai:refresh-forecast'));
        $this->assertNotNull($event); $this->assertSame('0 0,12 * * *', $event->expression);
        $this->travelTo(now()->setTime(11, 59)); $this->assertSame(12, \Carbon\Carbon::parse(ForecastRefreshSchedule::next())->hour);
        $this->travelTo(now()->setTime(12, 0)); $this->assertSame(now()->addDay()->startOfDay()->toIso8601String(), ForecastRefreshSchedule::next());
    }

    public function test_disabled_command_does_not_call_provider_or_change_cache(): void
    {
        config(['openai.forecasting_enabled' => false]);
        SystemSetting::set('ai_document_forecast_cache', ['previous' => true]);
        $this->mock(DocumentTrendForecaster::class)->shouldNotReceive('forecastWithHistory');
        $this->artisan('ai:refresh-forecast')->assertSuccessful();
        $this->assertSame(['previous'=>true], SystemSetting::get('ai_document_forecast_cache'));
    }

    public function test_success_saves_shared_parameters_and_failure_keeps_previous_result(): void
    {
        config(['openai.forecasting_enabled'=>true, 'openai.api_key'=>'fake']);
        $months = \App\Services\ForecastOutputValidator::months(12);
        $forecast = ['months'=>$months, 'overall_forecast'=>array_fill(0,12,10), 'per_document_type'=>[]];
        $mock = $this->mock(DocumentTrendForecaster::class);
        $mock->shouldReceive('forecastWithHistory')->once()->with(24,12,ForecastRefreshSchedule::parameters())->andReturn(['forecast'=>$forecast,'history'=>['per_document_type'=>[]]]);
        $this->artisan('ai:refresh-forecast')->assertSuccessful();
        $saved = SystemSetting::get('ai_document_forecast_cache');
        $this->assertSame(ForecastRefreshSchedule::parameters(), $saved['params']);
        $this->assertArrayHasKey('summary', $saved['forecast']);
        $this->assertArrayHasKey('calendar_revision', $saved);
        // The shared cooldown prevents a second call even through the CLI.
        $this->artisan('ai:refresh-forecast')->assertSuccessful();
        Cache::forget('ai-forecast-cooldown');
        $this->mock(DocumentTrendForecaster::class)->shouldReceive('forecastWithHistory')->once()->andThrow(new \RuntimeException('private provider data'));
        $this->artisan('ai:refresh-forecast')->assertFailed();
        $this->assertSame($saved, SystemSetting::get('ai_document_forecast_cache'));
        $lock = Cache::lock('ai-forecast-generation',180); $this->assertTrue($lock->get()); $lock->release();
    }
}

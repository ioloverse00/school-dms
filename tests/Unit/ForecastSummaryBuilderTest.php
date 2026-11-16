<?php

namespace Tests\Unit;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\ForecastSummaryBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastSummaryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_summary_metrics_from_forecast_and_history(): void
    {
        $this->travelTo(Carbon::parse('2026-05-15 12:00:00'));
        $user = User::factory()->create();

        $typeA = DocumentType::create(['name' => 'Transcript', 'category' => 'Academic', 'weight' => 1, 'alumni_only' => false]);
        $typeB = DocumentType::create(['name' => 'Good Moral', 'category' => 'Certification', 'weight' => 1, 'alumni_only' => false]);

        // Seed some historical request timestamps so peak weekday/hour lists are non-empty.
        $wed2pm = Carbon::parse('2026-05-06 14:00:00');
        $tue10am = Carbon::parse('2026-05-05 10:00:00');
        for ($i = 0; $i < 5; $i++) {
            $req = DocumentRequest::create([
                'request_id' => 'REQ-WED-' . $i,
                'user_id' => $user->id,
                'document_type_id' => $typeA->id,
                'purpose' => 'Test',
                'copies' => 1,
                'urgency' => 'regular',
                'student_category' => 'regular',
                'priority_score' => 0,
                'status' => 'completed',
                'remarks' => null,
            ]);
            $req->created_at = $wed2pm->copy()->addMinutes($i);
            $req->updated_at = $req->created_at;
            $req->save();
        }
        for ($i = 0; $i < 2; $i++) {
            $req = DocumentRequest::create([
                'request_id' => 'REQ-TUE-' . $i,
                'user_id' => $user->id,
                'document_type_id' => $typeB->id,
                'purpose' => 'Test',
                'copies' => 1,
                'urgency' => 'regular',
                'student_category' => 'regular',
                'priority_score' => 0,
                'status' => 'completed',
                'remarks' => null,
            ]);
            $req->created_at = $tue10am->copy()->addMinutes($i);
            $req->updated_at = $req->created_at;
            $req->save();
        }

        $forecast = [
            'months' => ['2026-06', '2026-07'],
            'overall_forecast' => [100, 150],
            'per_document_type' => [
                ['document_type_id' => $typeA->id, 'name' => $typeA->name, 'forecast' => [60, 90]],
                ['document_type_id' => $typeB->id, 'name' => $typeB->name, 'forecast' => [40, 60]],
            ],
        ];

        $builder = app(ForecastSummaryBuilder::class);
        $summary = $builder->build($forecast, 2);

        $this->assertSame(250, $summary['future_requests_total']);
        $this->assertSame('2026-07', $summary['peak_request_periods']['months'][0]['month']);
        $this->assertSame(150, $summary['peak_request_periods']['months'][0]['requests']);
        $this->assertSame($typeA->id, $summary['most_requested_documents'][0]['document_type_id']);
        $this->assertSame(150, $summary['most_requested_documents'][0]['forecast_total']);

        $this->assertNotEmpty($summary['peak_request_periods']['weekdays']);
        $this->assertNotEmpty($summary['peak_request_periods']['hours']);
        $this->assertIsInt($summary['expected_processing_workload']['total_minutes']);
        $this->assertArrayHasKey('monthly_surge_recommendations', $summary['staffing_needs']);
    }
}

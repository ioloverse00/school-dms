<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, DocumentType, DocumentRequest, SystemSetting};
use App\Services\StaffingCapacity;
use Spatie\Permission\Models\Role;

class StaffingCapacityTest extends TestCase
{
    use RefreshDatabase;

    private function registrar(): User
    {
        Role::findOrCreate('registrar');
        $user = User::factory()->create();
        $user->assignRole('registrar');
        return $user;
    }

    public function test_count_matches_registrar_list_and_updates_after_account_changes(): void
    {
        $service = app(StaffingCapacity::class);
        SystemSetting::set('staffing_capacity', ['support' => 999]);
        $this->assertSame(0, $service->assess('2027-06', 250)['registrar_count']);
        $this->assertSame(1, $service->assess('2027-06', 250)['additional_staff']);
        $first = $this->registrar();
        $this->registrar();
        foreach (['admin', 'student'] as $role) {
            Role::findOrCreate($role);
            $mixed = $this->registrar();
            $mixed->assignRole($role);
        }
        $this->assertSame(2, $service->assess('2027-06', 250)['registrar_count']);
        $this->assertSame('sufficient', $service->assess('2027-06', 250)['assessment_status']);
        $first->delete();
        $this->assertSame(1, $service->assess('2027-06', 250)['registrar_count']);
        $this->assertSame(6, $service->defaults()['productive_hours']);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.settings.staffing-capacity'));
    }

    public function test_workload_backlog_rounding_and_closures(): void
    {
        $service = app(StaffingCapacity::class);
        $this->registrar();
        $this->assertSame(26, $service->assess('2027-06', 936)['working_days']);
        $this->assertSame(0, $service->assess('2027-06', 936)['additional_staff']);
        $this->assertSame(1, $service->assess('2027-06', 937)['additional_staff']);
        $type = DocumentType::create(['name' => 'Transcript', 'category' => 'academic', 'weight' => 1]);
        $user = User::factory()->create();
        foreach (['processing', 'pending_verification', 'ready_for_release', 'completed', 'rejected'] as $index => $status) {
            DocumentRequest::create(['request_id' => 'CAP-'.$index, 'user_id' => $user->id, 'document_type_id' => $type->id, 'purpose' => 'Test', 'copies' => 1, 'urgency' => 'regular', 'student_category' => 'regular', 'priority_score' => 1, 'status' => $status, 'archived_at' => $status === 'pending_verification' ? now() : null]);
        }
        $this->assertSame(2, $service->assess('2027-06', 936)['backlog_requests']);
        $this->assertSame(1, $service->assess('2027-06', 936)['additional_staff']);
        $calendar = ['revision' => 1, 'terms' => [], 'years' => [], 'history' => [], 'events' => ['closure' => ['starts_on' => '2027-06-01', 'ends_on' => '2027-06-01', 'office_closed' => true]]];
        SystemSetting::set('academic_calendar', $calendar);
        $this->assertSame(25, $service->assess('2027-06', 100)['working_days']);
        $calendar['events']['closure']['ends_on'] = '2027-06-30';
        SystemSetting::set('academic_calendar', $calendar);
        $result = $service->assess('2027-06', 100);
        $this->assertSame('no_working_days', $result['assessment_status']);
        $this->assertNull($result['additional_staff']);
    }
}

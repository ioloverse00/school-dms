<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagementValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public static function badWeights(): array
    {
        return [
            ['student-weights', ['weights' => 'invalid']],
            ['student-weights', ['weights' => ['regular' => -1, 'graduating' => 5, 'alumni' => 3]]],
            ['student-weights', ['weights' => ['regular' => 2]]],
            ['student-weights', ['weights' => ['regular' => 2, 'graduating' => 5, 'alumni' => 3, 'unexpected' => 5]]],
            ['urgency-levels', ['levels' => ['regular' => 1, 'urgent' => 2, 'very_urgent' => 11]]],
            ['urgency-levels', ['levels' => ['regular' => 1, 'urgent' => 2]]],
            ['document-weights', ['weights' => ['invalid-id' => 3]]],
            ['document-weights', ['weights' => ['99999' => 3]]],
        ];
    }

    #[DataProvider('badWeights')]
    public function test_malformed_settings_do_not_write_any_settings(string $endpoint, array $payload): void
    {
        $original = SystemSetting::orderBy('id')->get()->toArray();
        $this->postJson(route('admin.settings.' . $endpoint . '.update'), $payload)->assertUnprocessable();
        $this->assertSame($original, SystemSetting::orderBy('id')->get()->toArray());
    }

    public function test_out_of_range_document_weights_do_not_partially_update(): void
    {
        $first = DocumentType::create(['name' => 'First', 'category' => 'academic', 'weight' => 3]);
        $second = DocumentType::create(['name' => 'Second', 'category' => 'academic', 'weight' => 4]);
        $this->postJson(route('admin.settings.document-weights.update'), ['weights' => [$first->id => 5, $second->id => 21]])
            ->assertUnprocessable();
        $this->assertSame(3, $first->fresh()->weight);
        $this->assertSame(4, $second->fresh()->weight);
    }

    public function test_custom_category_is_selectable_and_has_a_configurable_weight(): void
    {
        $url = route('admin.settings.student-types.add');
        $this->post($url, ['mode' => 'create', 'type_value' => 'transferee', 'type_label' => 'Transferee', 'weight' => 4])->assertSessionHasNoErrors();
        $this->assertSame('Transferee', \App\Support\StudentCategory::labels()['transferee']);
        $this->assertSame(4, SystemSetting::get('student_category_weights')['transferee']);
        $this->assertSame([], \App\Support\StudentCategory::unsupported());
        $snapshot = SystemSetting::get('student_types');
        $this->postJson($url, ['mode' => 'create', 'type_value' => 'transferee', 'type_label' => 'Duplicate', 'weight' => 3])->assertUnprocessable();
        $this->assertSame($snapshot, SystemSetting::get('student_types'));
        $this->postJson(route('admin.settings.student-weights.update'), ['weights' => ['regular' => 2, 'graduating' => 5, 'alumni' => 3]])->assertUnprocessable();
        $this->post(route('admin.settings.student-weights.update'), ['weights' => ['regular' => 2, 'graduating' => 5, 'alumni' => 3, 'transferee' => 6]])->assertSessionHasNoErrors();
        $this->assertSame(6, SystemSetting::get('student_category_weights')['transferee']);
        $this->get(route('admin.students.create'))->assertOk()->assertSee('Transferee');
        $this->postJson($url, ['mode' => 'create', 'type_value' => 'bad.code', 'type_label' => 'Invalid', 'weight' => 21])->assertUnprocessable();
    }

    public function test_priority_settings_consolidates_all_weight_forms_and_category_management(): void
    {
        DocumentType::create(['name' => 'Priority document', 'category' => 'academic', 'weight' => 3]);
        foreach (['admin.settings.document-management.student-weights', 'admin.settings.student-types'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Priority settings')->assertSee('Add category')
                ->assertSee('Save category weights')->assertSee('Save urgency levels')->assertSee('Save document weights')->assertSee('Priority document');
        }
        $this->get(route('admin.settings.document-management.urgency-levels'))->assertRedirect(route('admin.settings.document-management.student-weights'));
        $this->get(route('admin.settings.document-management.document-weights'))->assertOk()->assertSee('Edit priority weights')->assertDontSee('Save Weights');
    }

    public function test_valid_complete_settings_are_saved_as_integers(): void
    {
        $this->post(route('admin.settings.student-weights.update'), ['weights' => ['regular' => '2', 'graduating' => '5', 'alumni' => '3']])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(['regular' => 2, 'graduating' => 5, 'alumni' => 3], SystemSetting::get('student_category_weights'));
    }

    public function test_student_password_and_year_validation_precedes_any_update(): void
    {
        Role::findOrCreate('student');
        $student = User::factory()->create(['student_id' => 'VALIDATION-001']);
        $student->assignRole('student');
        $original = $student->fresh()->getAttributes();
        $this->putJson(route('admin.students.update', $student->id), [
            'student_id' => $student->student_id, 'name' => 'Should not persist', 'email' => $student->email,
            'course' => config('programs.default')[0], 'student_type' => 'alumni',
            'password' => 'short', 'year_graduated' => 'not-a-year',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password', 'year_graduated']);
        $this->assertSame($original, $student->fresh()->getAttributes());
    }

    public function test_registrar_password_validation_precedes_any_update(): void
    {
        Role::findOrCreate('registrar');
        $registrar = User::factory()->create();
        $registrar->assignRole('registrar');
        $original = $registrar->fresh()->getAttributes();
        $this->putJson(route('admin.registrars.update', $registrar->id), [
            'name' => 'Should not persist', 'email' => $registrar->email, 'password' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame($original, $registrar->fresh()->getAttributes());
    }
}

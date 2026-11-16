<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\StudentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryAndProgramIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'registrar', 'student'] as $role) {
            Role::findOrCreate($role);
        }
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_unsupported_categories_are_rejected_without_changing_configuration(): void
    {
        $existing = StudentCategory::options();
        SystemSetting::set('student_types', $existing);
        $this->postJson(route('admin.settings.student-types.add'), ['type_value' => 'transferee', 'type_label' => 'Transferee'])
            ->assertUnprocessable()->assertJsonValidationErrors('type_value');
        $this->assertSame($existing, SystemSetting::get('student_types'));
    }

    public static function requiredCategories(): array
    {
        return [['regular'], ['graduating'], ['alumni']];
    }

    #[DataProvider('requiredCategories')]
    public function test_required_categories_cannot_be_deleted(string $category): void
    {
        $existing = StudentCategory::options();
        SystemSetting::set('student_types', $existing);
        $this->deleteJson(route('admin.settings.student-types.delete'), ['type_value' => $category])->assertUnprocessable();
        $this->assertSame($existing, SystemSetting::get('student_types'));
    }

    public function test_custom_labels_are_used_by_forms_filters_and_csv_while_codes_stay_fixed(): void
    {
        $student = User::factory()->create(['student_id' => 'CATEGORY-001', 'student_type' => 'regular']);
        $student->assignRole('student');
        $this->post(route('admin.settings.student-types.add'), ['type_value' => 'regular', 'type_label' => '=Current Students'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['regular', 'graduating', 'alumni'], array_column(StudentCategory::options(), 'value'));
        foreach (['admin.students.index', 'admin.students.create'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('=Current Students')->assertSee('value="regular"', false);
        }
        $this->get(route('admin.students.edit', $student->id))->assertOk()->assertSee('=Current Students');
        $csv = $this->get(route('admin.students.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=Current Students", $csv);
        $this->assertSame('regular', $student->fresh()->student_type);
    }

    public function test_legacy_unsupported_options_are_retained_for_review_but_not_offered_for_new_students(): void
    {
        SystemSetting::set('student_types', [['value' => 'transferee', 'label' => 'Legacy Transferee']]);
        $this->assertSame(['regular', 'graduating', 'alumni'], array_column(StudentCategory::options(), 'value'));
        $this->get(route('admin.settings.student-types'))->assertOk()->assertSee('Legacy Transferee')->assertSee('Legacy unsupported entries');
        $this->get(route('admin.students.create'))->assertOk()->assertDontSee('Legacy Transferee');
        $this->post(route('admin.settings.student-types.add'), ['type_value' => 'regular', 'type_label' => 'Current Students'])->assertSessionHasNoErrors();
        $this->assertContains(['value' => 'transferee', 'label' => 'Legacy Transferee'], SystemSetting::get('student_types'));
        $this->delete(route('admin.settings.student-types.delete'), ['type_value' => 'transferee'])->assertSessionHasNoErrors();
        $this->assertSame([], StudentCategory::unsupported());
    }

    public function test_malformed_catalog_is_not_silently_overwritten_by_label_edit(): void
    {
        SystemSetting::set('student_types', 'malformed');
        $this->get(route('admin.students.create'))->assertOk();
        $this->postJson(route('admin.settings.student-types.add'), ['type_value' => 'regular', 'type_label' => 'Current'])
            ->assertUnprocessable();
        $this->assertSame('malformed', SystemSetting::get('student_types'));
    }

    public function test_assigned_programs_cannot_be_removed_but_unused_programs_can(): void
    {
        SystemSetting::set('programs', ['Custom Masters', 'Unused Program']);
        $student = User::factory()->create(['course' => 'Custom Masters']);
        $this->deleteJson(route('admin.settings.programs.delete'), ['program' => 'Custom Masters'])->assertUnprocessable();
        $this->assertSame(['Custom Masters', 'Unused Program'], SystemSetting::get('programs'));
        $this->assertSame('Custom Masters', $student->fresh()->course);
        $this->delete(route('admin.settings.programs.delete'), ['program' => 'Unused Program'])->assertSessionHasNoErrors();
        $this->assertSame(['Custom Masters'], SystemSetting::get('programs'));
    }

    public static function preservationMigrations(): array
    {
        return [['2026_05_09_000000_normalize_programs_and_user_courses.php'], ['2026_11_12_000001_preserve_existing_program_catalog.php']];
    }

    #[DataProvider('preservationMigrations')]
    public function test_course_migrations_preserve_custom_catalog_and_all_user_values_and_are_repeatable(string $file): void
    {
        SystemSetting::set('programs', ['Custom Masters', 'BSIT']);
        foreach (['Custom Masters', 'BSIT', 'Unlisted Course', 'Case Course', 'case course', null, ''] as $course) {
            User::factory()->create(['course' => $course]);
        }
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        $migration = require database_path('migrations/' . $file);
        $migration->up();
        $catalog = SystemSetting::get('programs');
        foreach (['Custom Masters', 'BSIT', 'Unlisted Course', 'Case Course', 'case course', ...config('programs.default')] as $program) {
            $this->assertContains($program, $catalog);
        }
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
        $migration->up();
        $this->assertSame($catalog, SystemSetting::get('programs'));
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
    }

    public function test_invalid_program_catalog_stops_preservation_without_replacing_data(): void
    {
        SystemSetting::set('programs', ['Good Course', ['invalid' => 'entry']]);
        $before = SystemSetting::get('programs');
        $user = User::factory()->create(['course' => 'Legacy Course']);
        try {
            app(\App\Services\PreserveProgramCatalog::class)->handle();
            $this->fail('Invalid configuration should require review.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('invalid structure', $exception->getMessage());
        }
        $this->assertSame($before, SystemSetting::get('programs'));
        $this->assertSame('Legacy Course', $user->fresh()->course);
    }
}

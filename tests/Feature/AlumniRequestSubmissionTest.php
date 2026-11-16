<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlumniRequestSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_alumni_form_submits_graduation_year_and_private_proof(): void
    {
        Notification::fake();
        Storage::fake('documents');
        Role::findOrCreate('student');
        Role::findOrCreate('admin');
        Role::findOrCreate('registrar');
        $student = User::factory()->create(['student_type' => 'alumni', 'year_graduated' => '2020']);
        $student->assignRole('student');
        $type = DocumentType::create(['name' => 'Diploma Copy', 'category' => 'academic', 'weight' => 3, 'alumni_only' => true]);

        $page = $this->actingAs($student)->get(route('student.requests.create'))->assertOk();
        $dom = new DOMDocument();
        @$dom->loadHTML($page->getContent());
        $xpath = new DOMXPath($dom);
        $form = $xpath->query('//form[@id="document-request-form"]')->item(0);
        $year = $xpath->query('//input[@name="year_graduated"]')->item(0);
        $this->assertNotNull($form);
        $this->assertNotNull($year);
        $this->assertSame($form->getAttribute('id'), $year->getAttribute('form'));
        $this->assertSame(route('student.requests.store'), $form->getAttribute('action'));

        // Serialize controls associated with the form, including those outside its subtree.
        $fields = [];
        foreach ($xpath->query('//input[@form="document-request-form"] | //form[@id="document-request-form"]//input[@name]') as $input) {
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        $this->post($form->getAttribute('action'), array_merge($fields, [
            'document_type_id' => $type->id,
            'purpose' => 'Employment',
            'copies' => 1,
            'urgency' => 'regular',
            'proof_of_payment' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasNoErrors()->assertRedirect(route('student.requests.index'));

        $request = DocumentRequest::firstOrFail();
        $this->assertSame($student->id, $request->user_id);
        $this->assertSame('alumni', $request->student_category);
        Storage::disk('documents')->assertExists($request->requiredFiles()->firstOrFail()->file_path);
        $this->get(route('student.requests.show', $request))->assertOk();
    }
}

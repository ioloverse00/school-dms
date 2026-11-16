<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use App\Support\RequestEditToken;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaleRequestEditTest extends TestCase
{
    use RefreshDatabase;

    public static function staffRoles(): array
    {
        return [['admin'], ['registrar']];
    }

    #[DataProvider('staffRoles')]
    public function test_second_staff_update_is_preserved_and_stale_draft_is_not_rebound_to_current_token(string $role): void
    {
        Mail::fake();
        [$first, $second, $request] = $this->fixture($role);
        $this->actingAs($first);
        $token = $this->formToken($role, $request);
        $this->actingAs($second)->put(route($role . '.requests.update', $request->id), [
            'edit_token' => $token, 'status' => 'processing', 'remarks' => 'Reviewed by second staff',
        ])->assertSessionHasNoErrors();

        $this->actingAs($first)->from(route($role . '.requests.show', $request->id))
            ->put(route($role . '.requests.update', $request->id), [
                'edit_token' => $token, 'status' => 'completed', 'remarks' => 'Stale completion draft',
            ])->assertRedirect(route($role . '.requests.show', $request->id))->assertSessionHasErrors('edit_token');
        $this->assertSame('processing', $request->fresh()->status);
        $this->assertSame('Reviewed by second staff', $request->fresh()->remarks);
        $this->assertDatabaseCount('document_request_events', 1);
        $this->assertDatabaseCount('document_status_emails', 1);
        $this->assertDatabaseCount('notifications', 1);
        Mail::assertNothingSent();

        $page = $this->get(route($role . '.requests.show', $request->id))->assertOk()
            ->assertSee('Your unsaved draft (for reference)')->assertSee('Stale completion draft');
        $dom = new DOMDocument();
        @$dom->loadHTML($page->getContent());
        $xpath = new DOMXPath($dom);
        $this->assertSame('processing', $xpath->query('//select[@name="status"]/option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame('Reviewed by second staff', $xpath->query('//textarea[@name="remarks"]')->item(0)->textContent);
        $current = RequestEditToken::for($request->fresh());
        $this->assertNotSame($token, $current);
        foreach ($xpath->query('//input[@name="edit_token"]') as $input) {
            $this->assertSame($current, $input->getAttribute('value'));
        }
    }

    #[DataProvider('staffRoles')]
    public function test_archive_and_unarchive_invalidate_open_status_forms(string $role): void
    {
        [$first, $second, $request] = $this->fixture($role);
        $this->actingAs($first);
        $old = $this->formToken($role, $request);
        $this->actingAs($second)->patch(route($role . '.requests.archive', $request->id), ['edit_token' => $old])->assertSessionHasNoErrors();
        $this->actingAs($first)->put(route($role . '.requests.update', $request->id), [
            'edit_token' => $old, 'status' => 'processing', 'remarks' => 'Review in progress. Please wait for the next update.',
        ])->assertSessionHasErrors('edit_token');
        $this->patch(route($role . '.requests.unarchive', $request->id), ['edit_token' => $old])->assertSessionHasErrors('edit_token');
        $this->assertNotNull($request->fresh()->archived_at);
        $this->patch(route($role . '.requests.unarchive', $request->id), ['edit_token' => RequestEditToken::for($request->fresh())])->assertSessionHasNoErrors();
        $this->assertNull($request->fresh()->archived_at);
        $this->put(route($role . '.requests.update', $request->id), ['edit_token' => $old, 'status' => 'completed', 'remarks' => 'Please collect your document.'])->assertSessionHasErrors('edit_token');
        $this->assertSame('pending_verification', $request->fresh()->status);
        $this->assertSame(['archived', 'unarchived'], $request->events()->orderBy('id')->pluck('action')->all());
        $this->assertDatabaseCount('document_status_emails', 0);
    }

    #[DataProvider('staffRoles')]
    public function test_status_changes_invalidate_an_open_archive_form(string $role): void
    {
        [$first, $second, $request] = $this->fixture($role);
        $this->actingAs($first);
        $old = $this->formToken($role, $request);
        $this->actingAs($second)->put(route($role . '.requests.update', $request->id), [
            'edit_token' => $old, 'status' => 'processing', 'remarks' => 'Review in progress. Please wait for the next update.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($first)->patch(route($role . '.requests.archive', $request->id), ['edit_token' => $old])->assertSessionHasErrors('edit_token');
        $this->assertNull($request->fresh()->archived_at);
        $this->assertDatabaseCount('document_request_events', 1);
    }

    public function test_change_and_revert_within_one_second_still_invalidates_the_original_form(): void
    {
        $this->travelTo(now()->startOfSecond());
        [$first, , $request] = $this->fixture('admin');
        $this->actingAs($first);
        $old = RequestEditToken::for($request);
        $timestamp = $request->getRawOriginal('updated_at');
        foreach (['processing', 'pending_verification'] as $status) {
            $this->put(route('admin.requests.update', $request->id), [
                'edit_token' => RequestEditToken::for($request->fresh()), 'status' => $status, 'remarks' => 'Review in progress. Please wait for the next update.',
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame($timestamp, $request->fresh()->getRawOriginal('updated_at'));
        $this->put(route('admin.requests.update', $request->id), ['edit_token' => $old, 'status' => 'completed', 'remarks' => 'Please collect your document.'])->assertSessionHasErrors('edit_token');
        $this->assertSame('pending_verification', $request->fresh()->status);
        $this->assertDatabaseCount('document_request_events', 2);
    }

    public function test_missing_forged_and_foreign_request_tokens_cannot_bypass_the_check(): void
    {
        [$first, , $request] = $this->fixture('admin');
        $this->actingAs($first);
        $other = $request->replicate();
        $other->request_id = 'REQ-OTHER';
        $other->save();
        foreach ([null, str_repeat('0', 64), RequestEditToken::for($other)] as $token) {
            $this->put(route('admin.requests.update', $request->id), ['status' => 'completed', 'remarks' => 'Please collect your document.', 'edit_token' => $token])->assertSessionHasErrors('edit_token');
            $this->patch(route('admin.requests.archive', $request->id), ['edit_token' => $token])->assertSessionHasErrors('edit_token');
        }
        $this->assertSame('pending_verification', $request->fresh()->status);
        $this->assertNull($request->fresh()->archived_at);
        $this->assertDatabaseCount('document_request_events', 0);
        $this->assertDatabaseCount('document_status_emails', 0);
    }

    public function test_ordinary_validation_keeps_original_token_so_a_later_change_still_conflicts(): void
    {
        [$first, $second, $request] = $this->fixture('admin');
        $this->actingAs($first);
        $token = RequestEditToken::for($request);
        $this->put(route('admin.requests.update', $request->id), [
            'edit_token' => $token, 'status' => 'not-a-status', 'remarks' => 'Draft',
        ])->assertSessionHasErrors('status');
        $draft = session('_old_input');
        $validationErrors = session('errors');
        $this->actingAs($second)->put(route('admin.requests.update', $request->id), [
            'edit_token' => $token, 'status' => 'processing', 'remarks' => 'Latest',
        ])->assertSessionHasNoErrors();
        $this->actingAs($first)->withSession(['_old_input' => $draft, 'errors' => $validationErrors]);
        $page = $this->get(route('admin.requests.show', $request->id))->assertOk();
        $this->assertStringContainsString('value="' . $token . '"', $page->getContent());
        $this->assertNotSame($token, RequestEditToken::for($request->fresh()));
        $this->put(route('admin.requests.update', $request->id), [
            'edit_token' => $token, 'status' => 'completed', 'remarks' => 'Draft',
        ])->assertSessionHasErrors('edit_token');
        $this->assertSame('Latest', $request->fresh()->remarks);
    }

    #[DataProvider('staffRoles')]
    public function test_current_unchanged_form_does_not_claim_an_email_was_queued(string $role): void
    {
        [$first, , $request] = $this->fixture($role);
        $this->actingAs($first)->put(route($role . '.requests.update', $request->id), [
            'edit_token' => RequestEditToken::for($request), 'status' => 'pending_verification', 'remarks' => 'Review in progress. Please wait for the next update.',
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'No changes to save. The request is already up to date.');
        $this->assertDatabaseCount('document_status_emails', 0);
        $this->assertDatabaseCount('document_request_events', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    private function formToken(string $role, DocumentRequest $request): string
    {
        $page = $this->get(route($role . '.requests.show', $request->id))->assertOk();
        $dom = new DOMDocument();
        @$dom->loadHTML($page->getContent());
        $inputs = (new DOMXPath($dom))->query('//input[@name="edit_token"]');
        $this->assertCount(2, $inputs);
        $this->assertSame($inputs->item(0)->getAttribute('value'), $inputs->item(1)->getAttribute('value'));

        return $inputs->item(0)->getAttribute('value');
    }

    private function fixture(string $role): array
    {
        Role::findOrCreate($role);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $first->assignRole($role);
        $second->assignRole($role);
        $student = User::factory()->create();
        $type = DocumentType::create(['name' => 'Transcript', 'category' => 'academic', 'weight' => 4]);
        $request = DocumentRequest::create([
            'request_id' => 'REQ-EDIT-001', 'user_id' => $student->id, 'document_type_id' => $type->id,
            'purpose' => 'Employment', 'status' => 'pending_verification', 'remarks' => 'Review in progress. Please wait for the next update.',
        ]);

        return [$first, $second, $request];
    }
}

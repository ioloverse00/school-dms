<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestEvent;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RequestOperationsTest extends TestCase
{
    use RefreshDatabase;

    public static function staffRoles(): array
    {
        return [['admin'], ['registrar']];
    }

    #[DataProvider('staffRoles')]
    public function test_dashboard_kpi_counts_match_filtered_listing_totals(string $role): void
    {
        $this->staff($role);
        foreach (['pending_verification', 'processing', 'ready_for_release', 'completed', 'rejected'] as $status) {
            $this->request(['status' => $status]);
            $this->request(['status' => $status, 'archived_at' => now()]);
        }
        $dashboard = $this->get(route($role . '.dashboard'))->assertOk();
        foreach (['totalRequests' => null, 'pendingRequests' => 'pending_review', 'processingRequests' => 'processing', 'readyRequests' => 'ready_for_release'] as $metric => $filter) {
            $url = route($role . '.requests.index', $filter ? ['status' => $filter] : []);
            $dashboard->assertSee('href="'.$url.'"', false);
            $this->get($url)->assertOk()->assertViewHas('requests', fn ($rows) => $rows->total() === $dashboard->viewData($metric));
        }
        $this->get(route($role . '.requests.index', ['status' => 'pending_review']))
            ->assertViewHas('requests', fn ($rows) => $rows->total() === 1 && $rows->every(fn ($row) => in_array($row->status, \App\Support\RequestStatus::PENDING, true)));
    }

    #[DataProvider('staffRoles')]
    public function test_counts_and_queue_exclude_archived_and_finished_requests(string $role): void
    {
        $this->staff($role);
        $old = $this->request(['status' => 'pending_verification', 'priority_score' => 8, 'created_at' => now()->subDays(2)]);
        $new = $this->request(['priority_score' => 8]);
        $this->request(['status' => 'completed', 'priority_score' => 999]);
        $this->request(['status' => 'rejected', 'priority_score' => 998]);
        $this->request(['status' => 'pending_verification', 'priority_score' => 997, 'archived_at' => now()]);
        $this->request(['status' => 'completed', 'priority_score' => 996, 'archived_at' => now()]);

        $this->get(route($role . '.dashboard'))->assertOk()
            ->assertViewHas('totalRequests', 3)->assertViewHas('pendingRequests', 2)
            ->assertViewHas('completedRequests', 2)
            ->assertSee('All-time completed records, including archived requests.')
            ->assertViewHas('recentRequests', fn ($queue) => $queue->modelKeys() === [$old->id, $new->id]);
    }

    #[DataProvider('staffRoles')]
    public function test_pending_verification_filter_and_export_use_same_search_and_order(string $role): void
    {
        $this->staff($role);
        $old = $this->request(['status' => 'pending_verification', 'purpose' => 'matching need', 'created_at' => now()->subDay()]);
        $new = $this->request(['status' => 'pending_verification', 'purpose' => 'matching need']);
        $hidden = $this->request(['status' => 'pending_verification', 'purpose' => 'matching need', 'archived_at' => now()]);
        $otherStatus = $this->request(['purpose' => 'matching need', 'status' => 'processing']);
        $different = $this->request(['status' => 'pending_verification', 'purpose' => 'unrelated']);
        $query = ['status' => 'pending_verification', 'q' => 'matching'];
        $this->get(route($role . '.requests.index', $query))->assertOk()
            ->assertViewHas('requests', fn ($rows) => $rows->modelKeys() === [$old->id, $new->id])
            ->assertSee('Pending Verification')->assertSee('name="q"', false);
        $csv = $this->get(route($role . '.requests.export', $query))->assertOk()->streamedContent();
        $this->assertStringContainsString($old->request_id, $csv);
        $this->assertStringContainsString($new->request_id, $csv);
        $this->assertLessThan(strpos($csv, $new->request_id), strpos($csv, $old->request_id));
        foreach ([$hidden, $otherStatus, $different] as $excluded) {
            $this->assertStringNotContainsString($excluded->request_id, $csv);
        }
        $this->get(route($role . '.requests.index', $query + ['archived' => 1]))->assertOk()
            ->assertViewHas('requests', fn ($rows) => $rows->modelKeys() === [$hidden->id]);
    }

    #[DataProvider('staffRoles')]
    public function test_search_covers_all_pages_and_treats_wildcards_as_literal_text(string $role): void
    {
        $this->staff($role);
        for ($i = 0; $i < 12; $i++) {
            $this->request(['priority_score' => 50]);
        }
        $target = $this->request(['purpose' => 'Scholarship 50%_!', 'priority_score' => 1]);
        $this->get(route($role . '.requests.index', ['q' => '50%_!']))->assertOk()
            ->assertViewHas('requests', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $target->id);
        $this->get(route($role . '.requests.index', ['q' => str_repeat('a', 201)]))->assertSessionHasErrors('q');
        $this->get(route($role . '.requests.export', ['q' => ['invalid']]))->assertSessionHasErrors('q');
    }

    #[DataProvider('staffRoles')]
    public function test_student_search_and_export_keep_role_and_category_filters(string $role): void
    {
        $this->staff($role);
        Role::findOrCreate('student');
        $student = User::factory()->create(['name' => 'Needle Student', 'student_type' => 'alumni']);
        $student->assignRole('student');
        $other = User::factory()->create(['name' => 'Needle Regular', 'student_type' => 'regular']);
        $other->assignRole('student');
        $staff = User::factory()->create(['name' => 'Needle Staff', 'student_type' => 'alumni']);
        $staff->assignRole($role);
        $query = ['q' => 'Needle', 'student_type' => 'alumni'];
        $this->get(route($role . '.students.index', $query))->assertOk()
            ->assertViewHas('students', fn ($rows) => $rows->modelKeys() === [$student->id]);
        $csv = $this->get(route($role . '.students.export', $query))->assertOk()->streamedContent();
        $this->assertStringContainsString('Needle Student', $csv);
        $this->assertStringNotContainsString('Needle Regular', $csv);
        $this->assertStringNotContainsString('Needle Staff', $csv);
    }

    #[DataProvider('staffRoles')]
    public function test_history_records_actor_before_after_and_ignores_repeated_actions(string $role): void
    {
        Mail::fake();
        $actor = $this->staff($role);
        $request = $this->request();
        foreach ([1, 2] as $attempt) {
            $this->put(route($role . '.requests.update', $request->id), ['edit_token' => \App\Support\RequestEditToken::for($request->fresh()), 'status' => 'processing', 'remarks' => 'Checked'])->assertSessionHasNoErrors();
        }
        $event = $request->events()->sole();
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame($actor->name, $event->actor_name);
        $this->assertSame('pending_verification', $event->from_status);
        $this->assertSame('processing', $event->to_status);
        $this->assertSame('Checked', $event->new_remarks);
        $this->put(route($role . '.requests.update', $request->id), ['edit_token' => \App\Support\RequestEditToken::for($request->fresh()), 'status' => 'processing', 'remarks' => 'Corrected'])->assertSessionHasNoErrors();
        $this->assertSame('remarks_updated', $request->events()->latest('id')->first()->action);
        foreach ([1, 2] as $attempt) {
            $this->patch(route($role . '.requests.archive', $request->id), ['edit_token' => \App\Support\RequestEditToken::for($request->fresh())])->assertRedirect();
        }
        foreach ([1, 2] as $attempt) {
            $this->patch(route($role . '.requests.unarchive', $request->id), ['edit_token' => \App\Support\RequestEditToken::for($request->fresh())])->assertRedirect();
        }
        $this->assertSame(['status_updated', 'remarks_updated', 'archived', 'unarchived'], $request->events()->orderBy('id')->pluck('action')->all());
        $this->get(route($role . '.requests.show', $request->id))->assertOk()->assertSee('Request History')->assertSee('Corrected');
        Mail::assertNothingSent();
    }

    public function test_history_failure_rolls_back_status_notification_and_outbox(): void
    {
        $this->staff('admin');
        $request = $this->request();
        DocumentRequestEvent::creating(fn () => throw new \RuntimeException('History unavailable'));
        try {
            app(\App\Services\UpdateDocumentRequestStatus::class)->handle($request->id, ['edit_token' => \App\Support\RequestEditToken::for($request->fresh()), 'status' => 'processing'], auth()->user());
            $this->fail('Expected event failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('History unavailable', $exception->getMessage());
        } finally {
            DocumentRequestEvent::flushEventListeners();
        }
        $this->assertSame('pending_verification', $request->fresh()->status);
        $this->assertDatabaseCount('document_request_events', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('document_status_emails', 0);
    }

    public function test_student_search_and_history_cannot_expose_another_students_request(): void
    {
        Role::findOrCreate('student');
        $student = User::factory()->create();
        $student->assignRole('student');
        $own = $this->request(['user_id' => $student->id, 'purpose' => 'Shared term']);
        $other = $this->request(['purpose' => 'Shared term']);
        $this->actingAs($student)->get(route('student.requests.index', ['q' => 'Shared']))->assertOk()
            ->assertViewHas('requests', fn ($rows) => $rows->modelKeys() === [$own->id]);
        $this->get(route('student.requests.show', $other->id))->assertNotFound();
        $this->get(route('student.requests.show', $own->id))->assertOk()->assertSee('Request History');
    }

    public function test_history_is_paginated_and_student_view_hides_staff_identity(): void
    {
        $staff = $this->staff('registrar');
        $student = User::factory()->create();
        $student->assignRole('student');
        $request = $this->request(['user_id' => $student->id]);
        for ($i = 0; $i < 22; $i++) {
            $request->events()->create([
                'action' => 'remarks_updated', 'actor_id' => $staff->id, 'actor_name' => 'Private Staff Identity',
                'from_status' => $request->status, 'to_status' => $request->status,
                'new_remarks' => 'Remark ' . $i,
            ]);
        }
        $this->get(route('registrar.requests.show', $request->id))->assertOk()
            ->assertViewHas('history', fn ($history) => $history->count() === 20 && $history->total() === 22)
            ->assertSee('Private Staff Identity');
        $this->actingAs($student)->get(route('student.requests.show', [$request->id, 'history_page' => 2]))->assertOk()
            ->assertViewHas('history', fn ($history) => $history->count() === 2)
            ->assertDontSee('Private Staff Identity');
    }

    public function test_search_by_related_student_or_document_still_obeys_archive_filter(): void
    {
        $this->staff('admin');
        $request = $this->request();
        $request->user->update(['name' => 'Exact Student Needle']);
        $archived = $this->request(['archived_at' => now()]);
        $archived->user->update(['name' => 'Exact Student Needle']);
        foreach (['Exact Student Needle', 'Transcript'] as $term) {
            $this->get(route('admin.requests.index', ['q' => $term]))->assertOk()
                ->assertViewHas('requests', fn ($rows) => $rows->modelKeys() === [$request->id]);
        }
    }

    #[DataProvider('staffRoles')]
    public function test_staff_document_type_filter_matches_csv_and_keeps_archive_scope(string $role): void
    {
        $this->staff($role);
        $match = $this->request(['status' => 'processing']);
        $otherType = DocumentType::create(['name' => 'Other document', 'category' => 'academic', 'weight' => 2]);
        $other = $this->request(['document_type_id' => $otherType->id, 'status' => 'processing']);
        $archived = $this->request(['status' => 'processing', 'archived_at' => now()]);
        $filters = ['document_type_id' => $match->document_type_id, 'status' => 'processing'];
        $this->get(route($role . '.requests.index', $filters))->assertOk()
            ->assertViewHas('requests', fn ($rows) => $rows->modelKeys() === [$match->id]);
        $csv = $this->get(route($role . '.requests.export', $filters))->assertOk()->streamedContent();
        $this->assertStringContainsString($match->request_id, $csv);
        $this->assertStringNotContainsString($other->request_id, $csv);
        $this->assertStringNotContainsString($archived->request_id, $csv);
    }

    public function test_student_filters_combine_and_only_return_owned_requests(): void
    {
        $student = $this->staff('student');
        $match = $this->request(['user_id' => $student->id, 'status' => 'processing', 'urgency' => 'urgent']);
        $this->request(['user_id' => $student->id, 'status' => 'completed', 'urgency' => 'urgent']);
        $this->request(['user_id' => $student->id, 'status' => 'processing', 'urgency' => 'regular']);
        $this->request(['status' => 'processing', 'urgency' => 'urgent']);
        $this->get(route('student.requests.index', [
            'status' => 'processing', 'urgency' => 'urgent', 'document_type_id' => $match->document_type_id, 'q' => 'Employment',
        ]))->assertOk()->assertViewHas('requests', fn ($rows) => $rows->modelKeys() === [$match->id]);
        $this->getJson(route('student.requests.index', ['status' => 'invalid']))->assertUnprocessable();
    }

    #[DataProvider('staffRoles')]
    public function test_status_updates_require_remarks_and_reject_retired_status(string $role): void
    {
        $this->staff($role);
        $request = $this->request();
        $payload = ['status' => 'processing', 'edit_token' => \App\Support\RequestEditToken::for($request)];
        $this->put(route($role . '.requests.update', $request->id), $payload + ['remarks' => '   '])
            ->assertSessionHasErrors('remarks');
        $this->assertSame('pending_verification', $request->fresh()->status);
        $payload['status'] = 'pending_requirements';
        $this->put(route($role . '.requests.update', $request->id), $payload + ['remarks' => 'Next step'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseCount('document_request_events', 0);
    }

    #[DataProvider('staffRoles')]
    public function test_completed_requests_are_in_archive_and_archive_ignores_status_filter(string $role): void
    {
        $this->staff($role);
        $completed = $this->request(['status' => 'completed']);
        $archived = $this->request(['status' => 'processing', 'archived_at' => now()]);
        $active = $this->request(['status' => 'processing']);
        $this->get(route($role . '.requests.index'))->assertOk()
            ->assertViewHas('requests', fn ($rows) => $rows->modelKeys() === [$active->id])
            ->assertDontSee('<option value="completed"', false);
        $this->get(route($role . '.requests.index', ['archived' => 1, 'status' => 'rejected']))->assertOk()
            ->assertViewHas('requests', fn ($rows) => $rows->total() === 2 && $rows->contains('id', $completed->id) && $rows->contains('id', $archived->id))
            ->assertDontSee('name="status"', false);
        $csv = $this->get(route($role . '.requests.export', ['archived' => 1, 'status' => 'rejected']))->assertOk()->streamedContent();
        $this->assertStringContainsString($completed->request_id, $csv);
        $this->assertStringContainsString($archived->request_id, $csv);
        $this->assertStringNotContainsString($active->request_id, $csv);
    }

    private function staff(string $role): User
    {
        Role::findOrCreate('student');
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function request(array $attributes = []): DocumentRequest
    {
        $type = DocumentType::firstOrCreate(['name' => 'Transcript'], ['category' => 'academic', 'weight' => 4]);

        return DocumentRequest::create($attributes + [
            'request_id' => 'REQ-' . \Illuminate\Support\Str::ulid(), 'user_id' => User::factory()->create()->id,
            'document_type_id' => $type->id, 'purpose' => 'Employment', 'copies' => 1,
            'urgency' => 'regular', 'student_category' => 'regular', 'priority_score' => 8, 'status' => 'pending_verification',
        ]);
    }
}

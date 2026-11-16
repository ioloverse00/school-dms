<?php

namespace App\Support;

use App\Models\DocumentRequest;
use Illuminate\Database\Eloquent\Builder;

class DocumentRequestListing
{
    public static function query(bool $archived, ?string $status = null, string $search = '', ?int $documentTypeId = null): Builder
    {
        return DocumentRequest::with(['user', 'documentType'])
            ->when($documentTypeId, fn ($q) => $q->where('document_type_id', $documentTypeId))
            ->when($archived, fn ($q) => $q->where(fn ($group) => $group->whereNotNull('archived_at')->orWhere('status', 'completed')))
            ->when(! $archived, fn ($q) => $q->whereNull('archived_at')->where('status', '!=', 'completed'))
            ->when(! $archived && $status === 'pending_review', fn ($q) => $q->whereIn('status', RequestStatus::PENDING))
            ->when(! $archived && $status && $status !== 'pending_review', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => self::search($q, $search))
            ->orderByDesc('priority_score')->orderBy('created_at')->orderBy('id');
    }

    public static function documentTypeFilter(\Illuminate\Http\Request $request): ?int
    {
        $validated = $request->validate(['document_type_id' => ['nullable', 'integer', 'exists:document_types,id']]);
        return isset($validated['document_type_id']) ? (int) $validated['document_type_id'] : null;
    }

    public static function search(Builder $query, string $term, bool $includeStudent = true): void
    {
        $query->where(function (Builder $group) use ($term, $includeStudent) {
            ListSearch::columns($group, ['request_id', 'purpose', 'status'], $term);
            $group->orWhereHas('documentType', fn ($q) => ListSearch::columns($q, ['name'], $term));
            if ($includeStudent) {
                $group->orWhereHas('user', fn ($q) => ListSearch::columns($q, ['name', 'student_id', 'email'], $term));
            }
        });
    }
}

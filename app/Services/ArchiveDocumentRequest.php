<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArchiveDocumentRequest
{
    public function handle(int $id, bool $archive, User $actor, string $editToken): void
    {
        DB::transaction(function () use ($id, $archive, $actor, $editToken) {
            $request = DocumentRequest::lockForUpdate()->findOrFail($id);
            \App\Support\RequestEditToken::assertCurrent($request, $editToken);
            if (($request->archived_at !== null) === $archive) {
                return;
            }
            $request->update(['archived_at' => $archive ? now() : null]);
            RecordRequestEvent::record($request, $archive ? 'archived' : 'unarchived', $actor, $request->status, $request->remarks);
        }, 3);
    }
}

<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\DocumentStatusEmail;
use App\Notifications\DocumentStatusNotification;
use Illuminate\Support\Facades\DB;

class UpdateDocumentRequestStatus
{
    public function handle(int $id, array $data, ?\App\Models\User $actor = null): bool
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $request = DocumentRequest::with(['user', 'documentType'])->lockForUpdate()->findOrFail($id);
            \App\Support\RequestEditToken::assertCurrent($request, $data['edit_token'] ?? '');
            $remarks = $data['remarks'] ?? null;
            if ($request->status === $data['status'] && $request->remarks === $remarks) {
                return false;
            }

            $oldStatus = $request->status;
            $oldRemarks = $request->remarks;
            $request->update(['status' => $data['status'], 'remarks' => $remarks]);
            RecordRequestEvent::record($request, $oldStatus !== $request->status ? 'status_updated' : 'remarks_updated', $actor, $oldStatus, $oldRemarks);
            $request->user->notify(new DocumentStatusNotification($request));
            DocumentStatusEmail::create([
                'document_request_id' => $request->id,
                'recipient' => $request->user->email,
                'snapshot' => [
                    'request_id' => $request->request_id, 'purpose' => $request->purpose,
                    'status' => $request->status, 'remarks' => $request->remarks,
                    'student_name' => $request->user->name, 'document_name' => $request->documentType->name,
                ],
                'available_at' => now(),
            ]);

            return true;
        }, 3);
    }
}

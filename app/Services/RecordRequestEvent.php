<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\User;

class RecordRequestEvent
{
    public static function record(DocumentRequest $request, string $action, ?User $actor, ?string $fromStatus = null, ?string $oldRemarks = null): void
    {
        $request->events()->create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $request->status,
            'old_remarks' => $oldRemarks,
            'new_remarks' => $request->remarks,
        ]);
    }
}

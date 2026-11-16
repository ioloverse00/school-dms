<?php

namespace App\Support;

class RequestStatus
{
    public const LABELS = [
        'pending_verification' => 'Pending Verification',
        'processing' => 'Processing',
        'ready_for_release' => 'Ready for Release',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
    ];

    public const PENDING = ['pending_verification'];
    public const ACTIONABLE = ['pending_verification', 'processing', 'ready_for_release'];
}

<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public DocumentRequest $documentRequest)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'request_id' => $this->documentRequest->request_id,
            'document' => $this->documentRequest->documentType->name,
            'status' => $this->documentRequest->status,
            'message' => 'Your request ' . $this->documentRequest->request_id . ' status has been updated to: ' . ucwords(str_replace('_', ' ', $this->documentRequest->status)),
        ];
    }
}
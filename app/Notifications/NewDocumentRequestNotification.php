<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewDocumentRequestNotification extends Notification
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
            'student' => $this->documentRequest->user->name,
            'url' => $notifiable->hasRole('admin')
                ? route('admin.requests.show', $this->documentRequest->id)
                : route('registrar.requests.show', $this->documentRequest->id),
            'message' => 'New ' . $this->documentRequest->documentType->name . ' request submitted by ' . $this->documentRequest->user->name . '.',
        ];
    }
}

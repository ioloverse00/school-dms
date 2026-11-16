<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentStatusEmail extends Model
{
    protected $fillable = ['document_request_id', 'recipient', 'snapshot', 'available_at'];

    protected $casts = ['snapshot' => 'array', 'available_at' => 'datetime', 'claimed_at' => 'datetime', 'sent_at' => 'datetime'];

    public function requestSnapshot(): DocumentRequest
    {
        $data = $this->snapshot;
        $request = new DocumentRequest();
        $request->forceFill(collect($data)->only(['request_id', 'purpose', 'status', 'remarks'])->all());
        $request->setRelation('user', new User(['name' => $data['student_name']]));
        $request->setRelation('documentType', new DocumentType(['name' => $data['document_name']]));

        return $request;
    }
}

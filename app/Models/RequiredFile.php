<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequiredFile extends Model
{
    protected $fillable = [
        'document_request_id',
        'file_type',
        'file_path',
        'original_name',
    ];

    public function documentRequest() {
        return $this->belongsTo(DocumentRequest::class);
    }
}

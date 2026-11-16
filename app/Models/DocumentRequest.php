<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    protected $fillable = [
        'request_id',
        'submission_token',
        'submission_hash',
        'user_id',
        'document_type_id',
        'purpose',
        'copies',
        'urgency',
        'student_category',
        'priority_score',
        'status',
        'remarks',
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function events()
    {
        return $this->hasMany(DocumentRequestEvent::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documentType()
    {
        return $this->belongsTo(\App\Models\DocumentType::class);
    }

    public function requiredFiles()
    {
        return $this->hasMany(\App\Models\RequiredFile::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = [
        'name',
        'category',
        'weight',
        'alumni_only',
    ];
    
    public function documentRequests() {
        return $this->hasMany(DocumentRequest::class);
    }
}

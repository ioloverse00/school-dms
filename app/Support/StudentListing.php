<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class StudentListing
{
    public static function query(?string $type = null, string $search = ''): Builder
    {
        return User::role('student')->withCount('documentRequests')
            ->when($type, fn ($q) => $q->where('student_type', $type))
            ->when($search !== '', fn ($q) => ListSearch::columns($q, ['name', 'student_id', 'email', 'course'], $search))
            ->orderBy('name')->orderBy('id');
    }
}

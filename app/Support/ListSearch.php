<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListSearch
{
    public static function term(Request $request): string
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:200']]);

        return trim($data['q'] ?? '');
    }

    public static function columns(Builder $query, array $columns, string $term): void
    {
        $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
        $query->where(function (Builder $group) use ($columns, $pattern) {
            // Column names are internal constants, never request input.
            foreach ($columns as $column) {
                $group->orWhereRaw($column . " LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }
}

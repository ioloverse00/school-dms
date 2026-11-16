<?php

namespace App\Support;

class RequestUrgency
{
    public const LABELS = ['regular' => 'Normal', 'urgent' => 'Urgent', 'very_urgent' => 'Very Urgent'];

    public static function label(string $value): string
    {
        return self::LABELS[$value] ?? ucwords(str_replace('_', ' ', $value));
    }
}

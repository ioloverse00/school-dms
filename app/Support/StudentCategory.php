<?php

namespace App\Support;

use App\Models\SystemSetting;

class StudentCategory
{
    public const LABELS = ['regular' => 'Regular Student', 'graduating' => 'Graduating Student', 'alumni' => 'Alumni'];

    public static function options(): array
    {
        $labels = self::LABELS;
        foreach (self::configured() as $option) {
            if (is_array($option) && is_string($option['value'] ?? null) && (isset($labels[$option['value']]) || ($option['custom'] ?? false) === true)
                && is_string($option['label'] ?? null) && trim($option['label']) !== '') {
                $labels[$option['value']] = $option['label'];
            }
        }

        return array_map(fn ($value, $label) => compact('value', 'label'), array_keys($labels), array_values($labels));
    }

    public static function labels(): array
    {
        return array_column(self::options(), 'label', 'value');
    }

    public static function configured(): array
    {
        $value = SystemSetting::get('student_types', []);

        return is_array($value) ? $value : [];
    }

    public static function unsupported(): array
    {
        return array_values(array_filter(self::configured(), fn ($option) => is_array($option)
            && is_string($option['value'] ?? null) && ! array_key_exists($option['value'], self::LABELS) && ($option['custom'] ?? false) !== true
            && is_string($option['label'] ?? null)));
    }
}

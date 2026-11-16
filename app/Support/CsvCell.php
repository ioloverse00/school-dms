<?php

namespace App\Support;

class CsvCell
{
    public static function escape(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/\A(?:[\t\r\n]|[\s\x{FEFF}\p{Z}\p{C}]*[=+\-@])/u', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}

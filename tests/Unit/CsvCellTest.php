<?php

namespace Tests\Unit;

use App\Support\CsvCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvCellTest extends TestCase
{
    public static function dangerousText(): array
    {
        return [['=1+1'], ['+SUM(1,2)'], ['-1+1'], ['@SUM(1,2)'], ["\t=1+1"], ["\rplain text"], ["\n=1+1"], ['   =1+1'], ["\u{FEFF}=1+1"], ["\u{00A0}@SUM(1,2)"], ["\0=1+1"]];
    }

    #[DataProvider('dangerousText')]
    public function test_formula_prefixes_and_leading_controls_are_escaped(string $value): void
    {
        $this->assertSame("'" . $value, CsvCell::escape($value));
    }

    public function test_ordinary_text_nulls_and_real_numbers_are_preserved(): void
    {
        foreach (['001234', 'Doe, Jane', 'Plain "quoted" text', "Already 'text", '', null, 2, -2, 1.5] as $value) {
            $this->assertSame($value, CsvCell::escape($value));
        }
    }
}

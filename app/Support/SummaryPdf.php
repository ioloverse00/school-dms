<?php

namespace App\Support;

/** Self-contained A4 table report; never loads remote assets or executes HTML. */
class SummaryPdf
{
    public static function render(array $lines, array $metadata = []): string
    {
        $escape = static function ($value): string {
            $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string) $value);
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], preg_replace('/[\x00-\x1F\x7F]/', ' ', $text));
        };
        $text = static fn ($x, $y, $size, $value, $bold = false) => "BT /".($bold ? 'F2' : 'F1')." $size Tf 1 0 0 1 $x $y Tm (".$escape($value).") Tj ET\n";
        $rows = []; $summary = []; $filters = [];
        foreach ($lines as $line) {
            $cells = is_array($line) ? $line : explode(' | ', (string) $line, 3);
            $cells = array_pad($cells, 3, '');
            if ($cells[0] === 'Summary') { $summary[(string) $cells[1]] = $cells[2]; continue; }
            if ($cells[0] === 'Filters') { $filters[(string) $cells[1]] = $cells[2]; continue; }
            $wrapped = [];
            foreach ([1 => 49, 2 => 39] as $column => $width) {
                $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string) $cells[$column]);
                $wrapped[] = explode("\n", wordwrap(str_replace(["\r", "\n", "\t"], ' ', $value), $width, "\n", true));
            }
            $rows[] = ['section' => (string) $cells[0], 'cells' => $wrapped, 'height' => max(22, max(array_map('count', $wrapped)) * 12 + 10)];
        }
        $filterLines = [];
        foreach ($filters as $label => $value) {
            if (!in_array($label, ['From', 'To', 'Period'], true)) $filterLines[] = $label.': '.$value;
        }
        $filterText = explode("\n", wordwrap(implode('  /  ', $filterLines), 105, "\n", true));
        $overviewHeight = ($summary || $filters) ? 134 + count($filterText) * 12 : 0;
        $pages = []; $page = []; $height = $overviewHeight;
        $section = null;
        foreach ($rows as $row) {
            $heading = $section !== $row['section'] || !$page;
            $needed = $row['height'] + ($heading ? 34 : 0);
            if ($page && $height + $needed > 580) {
                $pages[] = $page; $page = []; $height = 0; $heading = true;
            }
            $row['heading'] = $heading;
            $page[] = $row; $height += $row['height'] + ($heading ? 34 : 0);
            $section = $row['section'];
        }
        $pages[] = $page;
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($pages as $i => $page) {
            $pageId = 5 + $i * 2; $streamId = $pageId + 1; $kids[] = "$pageId 0 R";
            $stream = "0.17 0.21 0.48 rg\n";
            $stream .= $text(40, 790, 10, 'SCHOOL DMS / OFFICE OF THE REGISTRAR', true);
            $stream .= $text(40, 758, 24, 'Document Services Report', true);
            $stream .= "0.17 0.21 0.48 RG 2 w 40 742 m 555 742 l S\n";
            $stream .= "0.15 0.18 0.24 rg\n";
            $generatedBy = wordwrap((string) ($metadata['generated_by'] ?? 'School DMS'), 85, "\n", true);
            $period = isset($filters['From'], $filters['To'])
                ? \Carbon\CarbonImmutable::parse($filters['From'])->format('F j, Y').' to '.\Carbon\CarbonImmutable::parse($filters['To'])->format('F j, Y')
                : ($filters['Period'] ?? 'Selected reporting period');
            $stream .= $text(40, 724, 10, 'Reporting period: '.$period);
            $stream .= $text(40, 707, 8, 'Counts show the current status of requests submitted during this period.');
            $y = 688;
            if ($i === 0 && $overviewHeight) {
                foreach (['Total requests', 'Copies requested', 'Archived requests'] as $card => $label) {
                    $x = 40 + $card * 175;
                    $stream .= "0.95 0.96 0.98 rg $x 602 165 74 re f\n0.17 0.21 0.48 rg\n";
                    $stream .= $text($x + 12, 654, 9, $label);
                    $stream .= $text($x + 12, 620, 26, number_format((int) ($summary[$label] ?? 0)), true);
                }
                $stream .= "0.35 0.39 0.45 rg\n";
                $stream .= $text(40, 582, 9, 'REPORT FILTERS', true);
                foreach ($filterText as $line => $value) $stream .= $text(40, 566 - $line * 12, 8, $value);
                $y -= $overviewHeight;
            }
            $sectionTitles = ['Filters' => 'Report scope', 'Summary' => 'Request summary', 'Status' => 'Requests by status', 'Month' => 'Monthly submissions', 'Document' => 'Most requested document types', 'Student category' => 'Requestor categories - most requests first', 'Source' => 'Data source'];
            foreach ($page as $index => $row) {
                if ($row['heading']) {
                    $y -= 12;
                    $headingBottom = $y - 22;
                    $stream .= "0.91 0.94 0.95 rg 40 $headingBottom 515 22 re f\n0.17 0.21 0.48 rg\n";
                    $stream .= $text(50, $y - 15, 10, $sectionTitles[$row['section']] ?? $row['section'], true);
                    $stream .= $text(340, $y - 15, 9, $row['section'] === 'Filters' ? 'Selection' : ($row['section'] === 'Summary' ? 'Total' : 'Requests'), true);
                    $y = $headingBottom;
                }
                $bottom = $y - $row['height'];
                if ($index % 2 === 0) $stream .= "0.97 0.98 0.99 rg 40 $bottom 515 {$row['height']} re f\n";
                $stream .= "0.82 0.86 0.9 RG 0.4 w 40 $bottom m 555 $bottom l S\n0.15 0.18 0.24 rg\n";
                foreach ($row['cells'] as $column => $parts) foreach ($parts as $j => $part) {
                    // Convert wrapped WinAnsi text back before the shared PDF escaping step.
                    $stream .= $text([50, 340][$column], $y - 14 - $j * 12, 9, iconv('Windows-1252', 'UTF-8', $part), $column === 1);
                }
                $y = $bottom;
            }
            $stream .= "0.72 0.77 0.83 RG 40 65 m 555 65 l S\n0.35 0.39 0.45 rg\n";
            $stream .= $text(40, 51, 8, 'Generated by: '.explode("\n", $generatedBy)[0]);
            $stream .= $text(475, 49, 8, 'Page '.($i + 1).' of '.count($pages));
            $stream .= $text(40, 37, 7, 'Generated: '.($metadata['generated_at'] ?? 'Not specified'));
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $streamId 0 R >>";
            $objects[$streamId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
        }
        $objects[2] = '<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ', $kids).'] >>';
        ksort($objects); $pdf = "%PDF-1.4\n"; $offsets = [];
        foreach ($objects as $id => $object) { $offsets[$id] = strlen($pdf); $pdf .= "$id 0 obj\n$object\nendobj\n"; }
        $xref = strlen($pdf); $size = count($objects) + 1;
        $pdf .= "xref\n0 $size\n0000000000 65535 f \n";
        foreach ($offsets as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
        return $pdf."trailer\n<< /Size $size /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}

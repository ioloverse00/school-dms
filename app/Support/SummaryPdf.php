<?php

namespace App\Support;

/** Small paginated text report. Does not execute HTML or load external assets. */
class SummaryPdf
{
    public static function render(array $lines): string
    {
        $wrapped = [];
        foreach ($lines as $line) {
            $line = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $line);
            foreach (explode("\n", wordwrap(str_replace(["\r", "\t"], ' ', $line), 88, "\n", true)) as $part) $wrapped[] = $part;
        }
        $pages = array_chunk($wrapped, 48);
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        $kids = [];
        foreach ($pages as $i => $page) {
            $pageId = 4 + $i * 2; $streamId = $pageId + 1; $kids[] = "$pageId 0 R";
            $stream = "BT /F1 11 Tf 48 790 Td 15 TL\n";
            $page[] = 'Page '.($i + 1).' of '.count($pages);
            foreach ($page as $line) {
                $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], preg_replace('/[\x00-\x1F\x7F]/', '', $line));
                $stream .= "($escaped) Tj T*\n";
            }
            $stream .= "ET\n";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $streamId 0 R >>";
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

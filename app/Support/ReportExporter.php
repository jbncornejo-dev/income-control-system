<?php

namespace App\Support;

use Illuminate\Http\Response;

class ReportExporter
{
    public static function downloadExcel(string $filename, string $title, array $sheets): Response
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<?mso-application progid=\"Excel.Sheet\"?>\n"
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:o="urn:schemas-microsoft-com:office:office" '
            . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
            . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n"
            . '<Styles>'
            . '<Style ss:ID="Default" ss:Name="Normal"><Font ss:FontName="Calibri" ss:Size="11"/></Style>'
            . '<Style ss:ID="Title"><Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1" ss:Color="#1D3653"/></Style>'
            . '<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1D3653" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#10243B"/></Borders></Style>'
            . '<Style ss:ID="Cell"><Alignment ss:Vertical="Top" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E1E8"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E1E8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9E1E8"/></Borders></Style>'
            . '</Styles>' . "\n";

        foreach ($sheets as $sheet) {
            $name = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', (string) ($sheet['name'] ?? 'Reporte')), 0, 31) ?: 'Reporte';
            $columnCount = count($sheet['headers'] ?? []);
            $xml .= '<Worksheet ss:Name="' . self::xml($name) . '"><Table>';
            if ($columnCount > 0) {
                for ($i = 0; $i < $columnCount; $i++) {
                    $xml .= '<Column ss:AutoFitWidth="1" ss:Width="120"/>';
                }
            }
            $xml .= '<Row><Cell ss:StyleID="Title" ss:MergeAcross="' . max(0, $columnCount - 1) . '"><Data ss:Type="String">' . self::xml($title) . '</Data></Cell></Row>';
            if (! empty($sheet['headers'])) {
                $xml .= '<Row>';
                foreach ($sheet['headers'] as $header) {
                    $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">' . self::xml((string) $header) . '</Data></Cell>';
                }
                $xml .= '</Row>';
            }
            foreach ($sheet['rows'] ?? [] as $row) {
                $xml .= '<Row>';
                foreach ($row as $value) {
                    $type = is_int($value) || is_float($value) ? 'Number' : 'String';
                    $xml .= '<Cell ss:StyleID="Cell"><Data ss:Type="' . $type . '">' . self::xml((string) ($value ?? '')) . '</Data></Cell>';
                }
                $xml .= '</Row>';
            }
            $xml .= '</Table></Worksheet>';
        }

        return new Response($xml . '</Workbook>', 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function downloadPdf(string $filename, string $title, array $subtitleLines, array $sections): Response
    {
        $page = [];
        $page[] = ['kind' => 'title', 'text' => $title];
        foreach ($subtitleLines as $line) {
            $page[] = ['kind' => 'subtitle', 'text' => $line];
        }

        foreach ($sections as $section) {
            $headers = array_values($section['headers'] ?? []);
            $page[] = ['kind' => 'section', 'text' => (string) ($section['heading'] ?? 'Reporte')];
            if ($headers !== []) {
                $page[] = ['kind' => 'table', 'headers' => $headers, 'row' => $headers, 'isHeader' => true];
            }

            $hayFilas = false;
            foreach ($section['rows'] ?? [] as $row) {
                $hayFilas = true;
                $page[] = ['kind' => 'table', 'headers' => $headers, 'row' => array_values($row), 'isHeader' => false];
            }
            if (! $hayFilas) {
                $page[] = ['kind' => 'table', 'headers' => $headers, 'row' => ['Sin registros.'], 'isHeader' => false];
            }
        }
        $pages = array_chunk($page, 22) ?: [[]];
        $lastHeaders = [];
        foreach ($pages as $index => $pageChunk) {
            if (! empty($pageChunk) && ($pageChunk[0]['kind'] ?? null) === 'table' && ! ($pageChunk[0]['isHeader'] ?? false) && $lastHeaders !== []) {
                array_unshift($pageChunk, [
                    'kind' => 'table',
                    'headers' => $lastHeaders,
                    'row' => $lastHeaders,
                    'isHeader' => true,
                ]);
            }
            foreach ($pageChunk as $entry) {
                if (($entry['kind'] ?? null) === 'table' && ($entry['isHeader'] ?? false)) {
                    $lastHeaders = $entry['headers'];
                }
            }
            $pages[$index] = $pageChunk;
        }
        $objects = [];
        $pageIds = [];
        $contentIds = [];
        $nextId = 5;
        foreach ($pages as $index => $page) {
            $pageIds[$index] = $nextId++;
            $contentIds[$index] = $nextId++;
            $stream = '';
            $y = 570;
            $tableX = 30;
            $tableWidth = 732;

            foreach ($page as $entry) {
                if ($entry['kind'] === 'title') {
                    $stream .= "BT\n/F1 16 Tf 1 0 0 1 30 {$y} Tm\n(" . self::pdf($entry['text']) . ") Tj\nET\n";
                    $y -= 24;
                } elseif ($entry['kind'] === 'subtitle') {
                    $stream .= "BT\n/F1 9 Tf 1 0 0 1 30 {$y} Tm\n(" . self::pdf($entry['text']) . ") Tj\nET\n";
                    $y -= 14;
                } elseif ($entry['kind'] === 'section') {
                    $y -= 8;
                    $stream .= "BT\n/F1 11 Tf 1 0 0 1 30 {$y} Tm\n(" . self::pdf($entry['text']) . ") Tj\nET\n";
                    $y -= 16;
                } elseif ($entry['kind'] === 'table') {
                    $headers = $entry['headers'];
                    $values = $entry['row'];
                    $count = max(1, count($headers), count($values));
                    $width = $tableWidth / $count;
                    $isHeader = $entry['isHeader'] ?? false;
                    $wrappedValues = [];
                    $maxLines = 1;
                    foreach ($values as $column => $value) {
                        $wrappedValues[$column] = self::wrapPdfText(
                            trim(preg_replace('/\s+/', ' ', (string) ($value ?? ''))),
                            max(8, (int) floor(($width - 8) / 4.2))
                        );
                        $maxLines = max($maxLines, count($wrappedValues[$column]));
                    }
                    $height = max(22, ($maxLines * 9) + 8);

                    if ($isHeader) {
                        $stream .= '0.11 0.21 0.33 rg ' . $tableX . ' ' . ($y - $height + 4) . ' ' . $tableWidth . ' ' . $height . " re f\n";
                    }
                    $stream .= '0.75 0.75 0.75 RG 0.5 w ' . $tableX . ' ' . ($y - $height + 4) . ' ' . $tableWidth . ' ' . $height . " re S\n";
                    for ($column = 1; $column < $count; $column++) {
                        $x = $tableX + ($width * $column);
                        $stream .= $x . ' ' . ($y - $height + 4) . ' m ' . $x . ' ' . ($y + 4) . " l S\n";
                    }
                    foreach ($values as $column => $value) {
                        $color = $isHeader ? '1 1 1' : '0.1 0.1 0.1';
                        foreach ($wrappedValues[$column] as $line => $text) {
                            $stream .= "BT\n{$color} rg\n/F1 7 Tf 1 0 0 1 "
                                . ($tableX + ($width * $column) + 4) . ' '
                                . ($y - 10 - ($line * 9))
                                . " Tm\n(" . self::pdf($text) . ") Tj\nET\n";
                        }
                    }
                    $y -= $height;
                }
            }
            $stream = "q\n{$stream}Q";
            $objects[$contentIds[$index]] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
            $objects[$pageIds[$index]] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 792 612] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentIds[$index]} 0 R >>";
        }
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageIds)) . '] /Count ' . count($pageIds) . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        for ($id = 1; $id <= max(array_keys($objects)); $id++) {
            if (! isset($objects[$id])) {
                continue;
            }
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$objects[$id]}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= max(array_keys($objects)); $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function wrapPdfText(string $value, int $maxCharacters): array
    {
        if ($value === '') {
            return [''];
        }

        $words = preg_split('/\s+/', $value) ?: [$value];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if ($line !== '' && mb_strlen($line . ' ' . $word) > $maxCharacters) {
                $lines[] = $line;
                $line = '';
            }
            if (mb_strlen($word) > $maxCharacters) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                while (mb_strlen($word) > $maxCharacters) {
                    $lines[] = mb_substr($word, 0, $maxCharacters);
                    $word = mb_substr($word, $maxCharacters);
                }
            }
            $line = $line === '' ? $word : $line . ' ' . $word;
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines ?: [''];
    }

    private static function pdf(string $value): string
    {
        $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\(', '\)', '', ' '], $value);
    }
}

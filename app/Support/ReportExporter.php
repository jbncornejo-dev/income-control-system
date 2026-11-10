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
            . '<Style ss:ID="Title"><Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1"/></Style>'
            . '<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/></Style>'
            . '</Styles>' . "\n";

        foreach ($sheets as $sheet) {
            $name = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', (string) ($sheet['name'] ?? 'Reporte')), 0, 31) ?: 'Reporte';
            $xml .= '<Worksheet ss:Name="' . self::xml($name) . '"><Table>';
            $xml .= '<Row><Cell ss:StyleID="Title"><Data ss:Type="String">' . self::xml($title) . '</Data></Cell></Row>';
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
                    $xml .= '<Cell><Data ss:Type="' . $type . '">' . self::xml((string) ($value ?? '')) . '</Data></Cell>';
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
        $lines = [$title, ...$subtitleLines, ''];
        foreach ($sections as $section) {
            $lines[] = (string) $section['heading'];
            if (! empty($section['headers'])) {
                $lines[] = self::row($section['headers']);
            }
            $hayFilas = false;
            foreach ($section['rows'] ?? [] as $row) {
                $hayFilas = true;
                $lines[] = self::row($row);
            }
            if (! $hayFilas) {
                $lines[] = 'Sin registros.';
            }
            $lines[] = '';
        }

        $pages = array_chunk($lines, 46) ?: [[]];
        $objects = [];
        $pageIds = [];
        $contentIds = [];
        $nextId = 5;
        foreach ($pages as $index => $page) {
            $pageIds[$index] = $nextId++;
            $contentIds[$index] = $nextId++;
            $stream = "BT\n";
            $y = 750;
            foreach ($page as $line) {
                $stream .= '/F1 8 Tf 1 0 0 1 40 ' . $y . " Tm\n(" . self::pdf($line) . ") Tj\n";
                $y -= 15;
            }
            $stream .= 'ET';
            $objects[$contentIds[$index]] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
            $objects[$pageIds[$index]] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentIds[$index]} 0 R >>";
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

    private static function row(array $values): string
    {
        return implode(' | ', array_map(
            fn ($value) => self::pdf(mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($value ?? ''))), 0, 28)),
            $values
        ));
    }

    private static function pdf(string $value): string
    {
        $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\(', '\)', '', ' '], $value);
    }
}

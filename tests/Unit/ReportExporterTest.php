<?php

namespace Tests\Unit;

use App\Support\ReportExporter;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\TestCase;

class ReportExporterTest extends TestCase
{
    public function test_excel_keeps_identical_bytes_when_rows_are_lazy(): void
    {
        $rows = [['Código <&>', 'Ana Pérez', 12, null], ['00123', '(Dato)', 2.5]];
        $array = ReportExporter::downloadExcel('reporte.xls', 'Reporte', [['name' => 'Datos', 'headers' => ['Código'], 'rows' => $rows]]);
        $lazy = ReportExporter::downloadExcel('reporte.xls', 'Reporte', [['name' => 'Datos', 'headers' => ['Código'], 'rows' => new LazyCollection($rows)]]);

        $this->assertSame($array->getContent(), $lazy->getContent());
        $this->assertSame($array->headers->all(), $lazy->headers->all());
        $this->assertStringContainsString('Código &lt;&amp;&gt;', $lazy->getContent());
    }

    public function test_pdf_keeps_identical_bytes_and_empty_sections_when_rows_are_lazy(): void
    {
        foreach ([[], [['Ana Pérez', '(Dato)', 12, null]]] as $rows) {
            $array = ReportExporter::downloadPdf('reporte.pdf', 'Reporte', [], [['heading' => 'Datos', 'headers' => ['Código'], 'rows' => $rows]]);
            $lazy = ReportExporter::downloadPdf('reporte.pdf', 'Reporte', [], [['heading' => 'Datos', 'headers' => ['Código'], 'rows' => new LazyCollection($rows)]]);

            $this->assertSame($array->getContent(), $lazy->getContent());
            $this->assertSame($array->headers->all(), $lazy->headers->all());
        }
    }
}

<?php

namespace App\Reports\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class BaseExcelExport implements WithEvents, ShouldAutoSize
{
    protected string $dateFrom;
    protected string $dateTo;
    protected string $generatedAt;

    // Colores corporativos — cambiar aquí afecta todos los exports
    protected string $colorHeaderDark   = '1F3864';
    protected string $colorHeaderMedium = '2E75B6';
    protected string $colorHeaderLight  = 'D6E4F0';
    protected string $colorRowEven      = 'EBF3FB';
    protected string $colorRowOdd       = 'FFFFFF';
    protected string $colorBorder       = 'BDD7EE';

    public function __construct(string $dateFrom, string $dateTo)
    {
        $this->dateFrom    = $dateFrom;
        $this->dateTo      = $dateTo;
        $this->generatedAt = now()->setTimezone('America/Lima')->format('d/m/Y H:i:s');
    }

    // Cada subclase define su título de hoja
    abstract public function title(): string;

    // Cada subclase define su subtítulo
    abstract protected function subtitle(): string;

    // Cada subclase define sus columnas ['header' => 'key_en_data']
    abstract protected function columns(): array;

    // Cada subclase provee la data
    abstract protected function getData(): array;

    // Cada subclase puede agregar filas de totales — opcional
    protected function totalsRow(): array
    {
        return [];
    }

    // Cada subclase puede aplicar estilos especiales por celda — opcional
    protected function applyCellStyle(Worksheet $sheet, string $cell, string $key, mixed $value): void
    {
        // override en subclases si se necesita
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $data    = $this->getData();
                $columns = $this->columns();
                $keys    = array_values($columns);
                $headers = array_keys($columns);
                $colCount = count($headers);
                $lastCol  = $this->colLetter($colCount - 1);

                $this->buildHeader($sheet, $lastCol);
                $this->buildColumnHeaders($sheet, $headers, $lastCol);
                $lastDataRow = $this->buildDataRows($sheet, $data, $keys, $lastCol);
                $this->buildTotalsRow($sheet, $lastDataRow, $lastCol);
                $this->setColumnWidths($sheet, $keys);

                $sheet->freezePane('A6');
            },
        ];
    }

    private function buildHeader(Worksheet $sheet, string $lastCol): void
    {
        // Fila 1 — título
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'REPORTE DE MONITOREO — INSTANA');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['name' => 'Arial', 'bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->colorHeaderDark]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(40);

        // Fila 2 — subtítulo
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', $this->subtitle());
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['name' => 'Arial', 'bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->colorHeaderMedium]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(24);

        // Fila 3 — período y generación
        $midCol = $this->colLetter(intdiv(count(array_keys($this->columns())), 2) - 1);
        $sheet->mergeCells("A3:{$midCol}3");
        $sheet->setCellValue('A3', "Período: {$this->dateFrom}  →  {$this->dateTo}");
        $sheet->mergeCells("{$this->colLetter(intdiv(count(array_keys($this->columns())), 2))}3:{$lastCol}3");
        $sheet->setCellValue("{$this->colLetter(intdiv(count(array_keys($this->columns())), 2))}3", "Generado: {$this->generatedAt} (GMT-5)");
        $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
            'font'      => ['name' => 'Arial', 'size' => 10, 'italic' => true, 'color' => ['rgb' => $this->colorHeaderDark]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->colorHeaderLight]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(20);

        // Fila 4 — espacio
        $sheet->getRowDimension(4)->setRowHeight(6);
    }

    private function buildColumnHeaders(Worksheet $sheet, array $headers, string $lastCol): void
    {
        foreach ($headers as $col => $header) {
            $sheet->setCellValue($this->colLetter($col) . '5', $header);
        }

        $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
            'font'      => ['name' => 'Arial', 'bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->colorHeaderDark]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(22);
    }

    private function buildDataRows(Worksheet $sheet, array $data, array $keys, string $lastCol): int
    {
        foreach ($data as $index => $row) {
            $excelRow = $index + 6;
            $bgColor  = $index % 2 === 0 ? $this->colorRowEven : $this->colorRowOdd;

            foreach ($keys as $col => $key) {
                $cell = $this->colLetter($col) . $excelRow;
                $sheet->setCellValue($cell, $row[$key] ?? '');
                $this->applyCellStyle($sheet, $cell, $key, $row[$key] ?? '');
            }

            $sheet->getStyle("A{$excelRow}:{$lastCol}{$excelRow}")->applyFromArray([
                'font'      => ['name' => 'Arial', 'size' => 10],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $this->colorBorder]]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Columnas numéricas centradas (todas menos la primera)
            $sheet->getStyle("B{$excelRow}:{$lastCol}{$excelRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getRowDimension($excelRow)->setRowHeight(18);
        }

        return count($data) + 5;
    }

    private function buildTotalsRow(Worksheet $sheet, int $lastDataRow, string $lastCol): void
    {
        $totals = $this->totalsRow();
        if (empty($totals)) return;

        $totalRow = $lastDataRow + 1;
        foreach ($totals as $col => $formula) {
            $cell = $this->colLetter($col) . $totalRow;
            $sheet->setCellValue($cell, $formula);
        }

        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
            'font'      => ['name' => 'Arial', 'bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->colorHeaderMedium]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($totalRow)->setRowHeight(20);
    }

    private function setColumnWidths(Worksheet $sheet, array $keys): void
    {
        $widths = $this->columnWidths();
        foreach ($keys as $col => $key) {
            if (isset($widths[$key])) {
                $sheet->getColumnDimension($this->colLetter($col))->setWidth($widths[$key]);
            }
        }
    }

    // Cada subclase puede definir anchos — si no, ShouldAutoSize aplica
    protected function columnWidths(): array
    {
        return [];
    }

    protected function colLetter(int $index): string
    {
        $letters = range('A', 'Z');
        return $letters[$index] ?? 'A';
    }

    protected function estadoStyle(Worksheet $sheet, string $cell, string $estado): void
    {
        $styles = [
            'normal'      => ['bg' => 'E2EFDA', 'color' => '375623', 'label' => '● Normal'],
            'advertencia' => ['bg' => 'FFF2CC', 'color' => '7D6608', 'label' => '▲ Advertencia'],
            'critico'     => ['bg' => 'FCE4D6', 'color' => '9C2700', 'label' => '✖ Crítico'],
        ];

        $style = $styles[$estado] ?? $styles['normal'];
        $sheet->setCellValue($cell, $style['label']);
        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['name' => 'Arial', 'bold' => true, 'size' => 10, 'color' => ['rgb' => $style['color']]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $style['bg']]],
        ]);
    }
}
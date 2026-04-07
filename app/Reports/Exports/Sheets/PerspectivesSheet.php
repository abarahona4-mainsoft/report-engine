<?php

namespace App\Reports\Exports\Sheets;

use App\Reports\Exports\BaseExcelExport;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PerspectivesSheet extends BaseExcelExport implements WithTitle
{
    private array $data;

    public function __construct(array $data, string $dateFrom, string $dateTo)
    {
        parent::__construct($dateFrom, $dateTo);
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Aplicaciones';
    }

    protected function subtitle(): string
    {
        return 'Perspectivas de Aplicaciones';
    }

    protected function columns(): array
    {
        return [
            '#'             => 'item',
            'Aplicación'    => 'application',
            'Servicios'     => 'services',
            'Llamadas'      => 'calls',
            'Latencia'      => 'latency',
            'Tasa de Error' => 'error_rate',
            'Estado'        => 'estado',
        ];
    }

    protected function getData(): array
    {
        return $this->data;
    }

    protected function totalsRow(): array
    {
        $lastDataRow = count($this->data) + 4;
        return [
            0 => 'TOTAL',
            2 => "=SUM(C5:C{$lastDataRow})",
            3 => "=SUM(D5:D{$lastDataRow})",
        ];
    }

    protected function columnWidths(): array
    {
        return [
            'item'        => 6,
            'application' => 70,
            'services'    => 12,
            'calls'       => 16,
            'latency'     => 14,
            'error_rate'  => 16,
            'estado'      => 16,
        ];
    }

    protected function applyCellStyle(Worksheet $sheet, string $cell, string $key, mixed $value): void
    {
        if ($key === 'estado') {
            $this->estadoStyle($sheet, $cell, $value);
        }
    }
}
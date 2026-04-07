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
        $count = count($this->data) + 5;
        return [
            0 => 'TOTAL',
            1 => "=SUM(B6:B{$count})",
            2 => "=SUM(C6:C{$count})",
        ];
    }

    protected function columnWidths(): array
    {
        return [
            'item'        => 6,
            'application' => 45,
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
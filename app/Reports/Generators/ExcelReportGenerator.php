<?php

namespace App\Reports\Generators;

use App\Reports\Exports\Sheets\PerspectivesSheet;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ExcelReportGenerator
{
    public function generatePerspectives(array $data, string $dateFrom, string $dateTo): string
    {
        $filename = sprintf(
            'perspectivas_%s_%s.xlsx',
            str_replace([' ', ':'], '-', $dateFrom),
            str_replace([' ', ':'], '-', $dateTo)
        );

        $path = 'reports/' . $filename;

        Log::channel('reports')->info('Generando Excel perspectivas', [
            'filename' => $filename,
            'rows'     => count($data),
        ]);

        Excel::store(
            new PerspectivesSheet($data, $dateFrom, $dateTo),
            $path,
            'local'
        );

        Log::channel('reports')->info('Excel generado exitosamente', [
            'path' => storage_path('app/' . $path),
        ]);

        return storage_path('app/' . $path);
    }

    // Cuando quieras agregar eventos:
    // public function generateEvents(array $data, string $dateFrom, string $dateTo): string
    // {
    //     Excel::store(new EventsSheet($data, $dateFrom, $dateTo), 'reports/eventos_*.xlsx', 'local');
    // }
}
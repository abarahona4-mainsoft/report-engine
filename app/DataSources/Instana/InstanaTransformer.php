<?php

namespace App\DataSources\Instana;

use Illuminate\Support\Facades\Log;

class InstanaTransformer
{
    public function transform(array $rawItems): array
    {
        Log::channel('api')->info('Transformando datos Instana', [
            'total_items' => count($rawItems),
        ]);

        $transformed = collect($rawItems)->map(function ($item) {
            return [
                'endpoint'       => $item['name'] ?? 'N/A',
                'calls'          => $item['metrics']['calls'][0][1] ?? 0,
                'errors'         => $item['metrics']['errors'][0][1] ?? 0,
                'latency_ms'     => round($item['metrics']['latency'][0][1] ?? 0, 2),
                'error_rate'     => $this->calcErrorRate(
                                        $item['metrics']['calls'][0][1] ?? 0,
                                        $item['metrics']['errors'][0][1] ?? 0
                                    ),
                'status'         => $this->resolveStatus(
                                        $item['metrics']['errors'][0][1] ?? 0
                                    ),
            ];
        })->toArray();

        Log::channel('api')->info('Transformación completada', [
            'total_transformados' => count($transformed),
        ]);

        return $transformed;
    }

    private function calcErrorRate(int $calls, int $errors): string
    {
        if ($calls === 0) return '0%';
        return round(($errors / $calls) * 100, 2) . '%';
    }

    private function resolveStatus(int $errors): string
    {
        return $errors > 0 ? 'ERROR' : 'OK';
    }
}
<?php

namespace App\DataSources\Instana;

use Illuminate\Support\Facades\Log;

class InstanaTransformer
{
    // ─── Perspectives ─────────────────────────────────────────────────
    public function transformPerspectives(array $raw, array $events = [], array $excludeApplications = ['All Services']): array
    {
        Log::channel('api')->info('Transformando perspectives', [
            'total_items' => count($raw),
            'excludiendo' => $excludeApplications,
            'eventos'     => count($events),
        ]);

        // Construimos mapas de applicationId por severidad
        $warningIds  = $this->mapEventsByApplicationId($events, 5);
        $criticalIds = $this->mapEventsByApplicationId($events, 10);

        $result = collect($raw)
            ->filter(function ($item) use ($excludeApplications) {
                return !in_array($item['application']['label'] ?? '', $excludeApplications);
            })
            ->map(function ($item) use ($warningIds, $criticalIds) {
                $calls    = $item['metrics']['calls.sum'][0][1] ?? 0;
                $services = $item['metrics']['services.distinct_count'][0][1] ?? 0;
                $errors   = $item['metrics']['errors.mean'][0][1] ?? 0;
                $latency  = $item['metrics']['latency.mean'][0][1] ?? 0;
                $appId    = $item['application']['id'] ?? '';

                // Primero normal, luego advertencia, luego critico
                // El critico sobreescribe al advertencia si hay ambos
                $estado = 'normal';
                if (isset($warningIds[$appId]))  $estado = 'advertencia';
                if (isset($criticalIds[$appId])) $estado = 'critico';

                return [
                    'application' => $item['application']['label'] ?? 'N/A',
                    'services'    => (int) $services,
                    'calls'       => (int) $calls,
                    'latency'     => $this->formatLatency($latency),
                    'error_rate'  => $this->formatErrorRate($errors),
                    'estado'      => $estado,
                ];
            })
            ->sortByDesc('calls')
            ->values()
            ->toArray();

        Log::channel('api')->info('Transformación perspectives completada', [
            'total_transformados' => count($result),
        ]);

        return $result;
    }

    // ─── Métricas ─────────────────────────────────────────────────────
    public function transformMetrics(array $raw): array
    {
        return [];
    }

    // ─── Eventos ──────────────────────────────────────────────────────
    public function transformEvents(array $raw): array
    {
        return [];
    }

    // ─── Helpers de formato ───────────────────────────────────────────

    private function formatLatency(float $ms): string
    {
        if ($ms < 1) {
            return round($ms * 1000, 2) . 'μs';
        }

        if ($ms < 1000) {
            return round($ms, 2) . 'ms';
        }

        return round($ms / 1000, 2) . 's';
    }

    private function formatErrorRate(float $rate): string
    {
        // Instana devuelve la tasa como decimal (ej: 0.00001288)
        return number_format($rate * 100, 2) . '%';
    }

    private function mapEventsByApplicationId(array $events, int $severity): array
    {
        return collect($events)
            ->filter(fn($e) => ($e['severity'] ?? 0) === $severity)
            ->keyBy('applicationId')
            ->toArray();
    }    
}
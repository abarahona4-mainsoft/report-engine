<?php

namespace App\DataSources\Instana;

use App\DataSources\DataSourceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstanaClient implements DataSourceInterface
{
    private string $baseUrl;
    private string $apiToken;
    private int $timeout;
    private int $retryTimes;
    private int $retrySleep;

    public function __construct()
    {
        $this->baseUrl    = config('datasources.sources.instana.base_url');
        $this->apiToken   = config('datasources.sources.instana.api_token');
        $this->timeout    = config('datasources.sources.instana.timeout', 30);
        $this->retryTimes = config('datasources.sources.instana.retry.times', 3);
        $this->retrySleep = config('datasources.sources.instana.retry.sleep', 1000);
    }

    public function fetch(string $dateFrom, string $dateTo): array
    {
        $windowTo   = strtotime($dateTo) * 1000;
        $windowFrom = strtotime($dateFrom) * 1000;
        $windowSize = $windowTo - $windowFrom;

        return $this->fetchPerspectives($windowTo, $windowSize);
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(10)
                ->get("{$this->baseUrl}/api/instana/health");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    // ─── Perspectives — con paginación automática ─────────────────────
    public function fetchPerspectives(int $windowTo, int $windowSize): array
    {
        $allItems  = [];
        $page      = 1;
        $pageSize  = 200;

        Log::channel('api')->info('Instana perspectives fetch iniciado', [
            'window_to'   => date('Y-m-d H:i:s', $windowTo / 1000),
            'window_size' => $windowSize . 'ms',
        ]);

        do {
            $body = [
                'metrics' => [
                    ['metric' => 'calls',    'aggregation' => 'SUM'],
                    ['metric' => 'services', 'aggregation' => 'DISTINCT_COUNT'],
                    ['metric' => 'errors',   'aggregation' => 'MEAN'],
                    ['metric' => 'latency',  'aggregation' => 'MEAN'],
                ],
                'order' => [
                    'by'        => 'calls.sum',
                    'direction' => 'DESC',
                ],
                'pagination' => [
                    'page'     => $page,
                    'pageSize' => $pageSize,
                ],
                'timeFrame' => [
                    'to'         => $windowTo,
                    'windowSize' => $windowSize,
                ],
            ];

            try {
                $response = Http::withHeaders($this->headers())
                    ->timeout($this->timeout)
                    ->retry($this->retryTimes, $this->retrySleep)
                    ->post("{$this->baseUrl}/api/application-monitoring/metrics/applications", $body);

                if ($response->failed()) {
                    Log::channel('api')->error('Instana perspectives fetch fallido', [
                        'page'   => $page,
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    break;
                }

                $data      = $response->json();
                $items     = $data['items'] ?? [];
                $totalHits = $data['totalHits'] ?? 0;
                $allItems  = array_merge($allItems, $items);

                Log::channel('api')->info('Instana perspectives página obtenida', [
                    'page'       => $page,
                    'items'      => count($items),
                    'total_hits' => $totalHits,
                ]);

                $page++;

            } catch (\Exception $e) {
                Log::channel('api')->error('Instana perspectives excepción', [
                    'page'    => $page,
                    'message' => $e->getMessage(),
                ]);
                break;
            }

        } while (count($allItems) < $totalHits);

        Log::channel('api')->info('Instana perspectives fetch completado', [
            'total_items' => count($allItems),
        ]);

        return $allItems;
    }

    // ─── Métricas ─────────────────────────────────────────────────────
    public function fetchMetrics(string $dateFrom, string $dateTo): array
    {
        // se implementará en siguiente iteración
        return [];
    }

    // ─── Eventos ──────────────────────────────────────────────────────
    public function fetchEvents(string $dateFrom, string $dateTo): array
    {
        // se implementará en siguiente iteración
        return [];
    }

    private function headers(): array
    {
        return [
            'Authorization' => "apiToken {$this->apiToken}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }
}
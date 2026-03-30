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
        $from = new \DateTime($dateFrom, new \DateTimeZone('America/Lima'));
        $to   = new \DateTime($dateTo,   new \DateTimeZone('America/Lima'));

        $windowTo   = $to->getTimestamp() * 1000;
        $windowFrom = $from->getTimestamp() * 1000;
        $windowSize = $windowTo - $windowFrom;

        return $this->fetchPerspectives($dateTo, $windowSize);
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
    public function fetchPerspectives(string $dateTo, int $windowSize): array
    {
        $windowTo = $this->toUtcMs($dateTo);

        Log::channel('api')->info('Instana perspectives fetch iniciado', [
            'date_to_lima' => $dateTo,
            'window_to_utc' => date('Y-m-d H:i:s', $windowTo / 1000) . ' UTC',
            'window_size'   => $windowSize . 'ms',
        ]);

        $allItems  = [];
        $page      = 1;
        $pageSize  = 200;
        $totalHits = 0;

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
    public function fetchEventsPerspective(string $dateTo, int $windowSize): array
    {
        $windowTo = $this->toUtcMs($dateTo);

        Log::channel('api')->info('Instana events fetch iniciado', [
            'date_to_lima'  => $dateTo,
            'window_to_utc' => date('Y-m-d H:i:s', $windowTo / 1000) . ' UTC',
            'window_size'   => $windowSize . 'ms',
        ]);

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleep)
                ->get("{$this->baseUrl}/api/events", [
                    'to'                           => $windowTo,
                    'windowSize'                   => $windowSize,
                    'filterEventUpdates'           => 'false',
                    'excludeTriggeredBefore'       => 'false',
                    'includeAgentMonitoringIssues' => 'false',
                    'includeKubernetesInfoEvents'  => 'false',
                    'eventTypeFilters'             => 'ISSUE',
                ]);

            if ($response->failed()) {
                Log::channel('api')->error('Instana events fetch fallido', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return [];
            }

            $events = $response->json() ?? [];

            $filtered = array_values(array_filter($events, function ($event) {
                return ($event['type']       ?? '') === 'issue'
                    && ($event['state']      ?? '') === 'open'
                    && ($event['entityType'] ?? '') === 'APPLICATION';
            }));

            Log::channel('api')->info('Instana events fetch completado', [
                'total_raw'      => count($events),
                'total_filtrado' => count($filtered),
            ]);

            return $filtered;

        } catch (\Exception $e) {
            Log::channel('api')->error('Instana events excepción', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    // ─── Helper de conversión de fechas ───────────────────────────────────
    private function toUtcMs(string $date): int
    {
        $dt = new \DateTime($date, new \DateTimeZone('America/Lima'));
        $dt->setTimezone(new \DateTimeZone('UTC'));
        return $dt->getTimestamp() * 1000;
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
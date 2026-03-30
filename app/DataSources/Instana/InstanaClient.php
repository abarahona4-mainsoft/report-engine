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
        $this->baseUrl   = config('datasources.sources.instana.base_url');
        $this->apiToken  = config('datasources.sources.instana.api_token');
        $this->timeout   = config('datasources.sources.instana.timeout', 30);
        $this->retryTimes = config('datasources.sources.instana.retry.times', 3);
        $this->retrySleep = config('datasources.sources.instana.retry.sleep', 1000);
    }

    public function fetch(string $dateFrom, string $dateTo): array
    {
        $windowFrom = strtotime($dateFrom) * 1000;
        $windowTo   = strtotime($dateTo) * 1000;

        Log::channel('api')->info('Instana fetch iniciado', [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ]);

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleep)
                ->get("{$this->baseUrl}/api/application-monitoring/analyze/call-groups", [
                    'windowFrom' => $windowFrom,
                    'windowTo'   => $windowTo,
                    'fillTimeSeries' => false,
                ]);

            if ($response->failed()) {
                Log::channel('api')->error('Instana fetch fallido', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return [];
            }

            Log::channel('api')->info('Instana fetch exitoso', [
                'status' => $response->status(),
                'items'  => count($response->json('items', [])),
            ]);

            return $response->json('items', []);

        } catch (\Exception $e) {
            Log::channel('api')->error('Instana fetch excepción', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
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

    private function headers(): array
    {
        return [
            'Authorization' => "apiToken {$this->apiToken}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }
}
<?php

namespace App\DataSources;

interface DataSourceInterface
{
    public function fetch(string $dateFrom, string $dateTo): array;
    public function isAvailable(): bool;
}
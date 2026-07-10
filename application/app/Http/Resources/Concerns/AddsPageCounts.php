<?php

namespace App\Http\Resources\Concerns;

trait AddsPageCounts
{
    protected function withPages(array $stats): array
    {
        return [...$stats, 'pages' => round($stats['chars'] / 1800, 2)];
    }
}

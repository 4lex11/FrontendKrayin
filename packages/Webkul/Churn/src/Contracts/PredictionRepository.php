<?php

namespace Webkul\Churn\Contracts;

use Closure;

interface PredictionRepository
{
    public function record(int $customerId, array $payload, Closure $predict, ?int $workItemId = null): array;

    public function history(int $customerId, int $page = 1): array;
}

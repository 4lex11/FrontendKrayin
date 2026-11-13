<?php

namespace Webkul\Churn\Contracts;

interface WorkRepository
{
    public function enqueue(?array $ownerIds, string $cutoff, ?int $customerId, ?int $requester): array;

    public function progress(?array $ownerIds, string $cutoff): array;

    public function process(int $limit): array;
}

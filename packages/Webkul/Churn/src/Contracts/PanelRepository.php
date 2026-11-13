<?php

namespace Webkul\Churn\Contracts;

interface PanelRepository
{
    public function detail(int $customerId, ?array $ownerIds, string $cutoff, string $version): array;

    public function listing(?array $ownerIds, array $filters): array;

    public function visiblePredictionCount(?array $ownerIds): int;
}

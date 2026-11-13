<?php

namespace Webkul\Churn\Contracts;

interface DatasetRepository
{
    public function import(array $rows, string $hash, string $cutoff, int $ownerId, bool $dryRun): array;
}

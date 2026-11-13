<?php

namespace Webkul\Churn\Contracts;

interface ReviewRepository
{
    public function overview(int $customerId, ?array $ownerIds, string $cutoff, string $version): array;

    public function record(int $customerId, ?array $ownerIds, array $data, int $authorId): void;
}

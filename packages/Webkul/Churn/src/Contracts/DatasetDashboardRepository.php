<?php

namespace Webkul\Churn\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

interface DatasetDashboardRepository
{
    public const DIMENSIONS = ['category', 'subcategory', 'segment', 'region'];

    public const COLUMNS = [
        'line_id' => 'line.line_id',
        'order_id' => 'line.order_id',
        'order_date' => 'line.order_date',
        'ship_date' => 'line.ship_date',
        'ship_mode' => 'line.ship_mode',
        'customer_id' => 'customer.external_customer_id',
        'customer_name' => 'customer.dataset_name',
        'segment' => 'line.segment',
        'country' => 'line.country',
        'city' => 'line.city',
        'state' => 'line.state',
        'postal_code' => 'line.postal_code',
        'region' => 'line.region',
        'product_id' => 'line.product_id',
        'category' => 'line.category',
        'subcategory' => 'line.subcategory',
        'product_name' => 'line.product_name',
        'sales' => 'line.sales',
    ];

    public function summary(?array $ownerIds, ?string $from, ?string $to, array $dimensions = []): array;

    public function charts(?array $ownerIds, ?string $from, ?string $to, array $dimensions): array;

    public function options(?array $ownerIds): array;

    public function page(?array $ownerIds, array $filters, array $columns): LengthAwarePaginator;

    public function export(?array $ownerIds, array $filters, array $columns): LazyCollection;

    public function customer(?array $ownerIds, string $customerId, int $page): array;

    public function order(?array $ownerIds, string $orderId, int $page): array;

    public function predictionSummary(?array $ownerIds, string $cutoff, string $version): array;
}

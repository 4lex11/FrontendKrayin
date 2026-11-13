<?php

namespace Webkul\Churn\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Webkul\Churn\Exceptions\ChurnApiException;

class CustomerInput
{
    public function build(int $customerId, string $cutoff): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $cutoff);
        $start = DB::table('churn_sales_lines')->where('source', 'superstore-sales')->min('order_date');
        $end = DB::table('churn_sales_lines')->where('source', 'superstore-sales')->max('order_date');
        $customer = DB::table('churn_dataset_customers')->where('source', 'superstore-sales')->where('id', $customerId)->first();
        if (! $date || $date->format('Y-m-d') !== $cutoff || ! $start || $cutoff < $start || $cutoff > $end || ! $customer) {
            throw new ChurnApiException('customer_or_cutoff_not_available');
        }
        $rows = DB::table('churn_sales_lines')->where('dataset_customer_id', $customerId)
            ->where('order_date', '>', $date->modify('-365 days')->format('Y-m-d'))->where('order_date', '<=', $cutoff)
            ->orderBy('order_date')->orderBy('line_id')->limit(5001)
            ->get(['line_id', 'order_id', 'order_date', 'sales', 'category', 'subcategory'])
            ->map(function ($row): array {
                $result = (array) $row;
                $result['sales'] = (float) $result['sales'];

                return $result;
            })->all();

        return ['customer_id' => $customer->external_customer_id, 'as_of' => $cutoff, 'history_start' => $start, 'transactions' => $rows];
    }
}

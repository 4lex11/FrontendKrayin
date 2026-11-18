<?php

namespace Webkul\Churn\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Webkul\Churn\Contracts\DatasetDashboardRepository as DatasetDashboardRepositoryContract;

class DatasetDashboardRepository implements DatasetDashboardRepositoryContract
{
    private function salesLines(?array $ownerIds, ?string $from = null, ?string $to = null, array $dimensions = []): Builder
    {
        $lines = DB::table('churn_sales_lines as line')
            ->join('churn_dataset_customers as customer', 'customer.id', '=', 'line.dataset_customer_id')
            ->join('persons as person', 'person.id', '=', 'customer.person_id')
            ->where('line.source', 'superstore-sales')
            ->whereColumn('line.source', 'customer.source');

        if ($ownerIds !== null) {
            $lines->whereIn('person.user_id', $ownerIds);
        }
        if ($from !== null) {
            $lines->where('line.order_date', '>=', $from);
        }
        if ($to !== null) {
            $lines->where('line.order_date', '<=', $to);
        }
        foreach (self::DIMENSIONS as $dimension) {
            if (isset($dimensions[$dimension])) {
                $lines->where('line.'.$dimension, $dimensions[$dimension]);
            }
        }

        return $lines;
    }

    public function summary(?array $ownerIds, ?string $from, ?string $to, array $dimensions = []): array
    {
        $lines = $this->salesLines($ownerIds, $from, $to, $dimensions);
        $totals = $lines->selectRaw('COUNT(DISTINCT customer.id) AS customers, COUNT(DISTINCT line.order_id) AS orders, COUNT(*) AS line_count, COALESCE(SUM(line.sales), 0) AS sales, MIN(line.order_date) AS first_date, MAX(line.order_date) AS last_date')->first();
        $orders = (int) $totals->orders;

        return [
            'customers' => (int) $totals->customers,
            'orders' => $orders,
            'lines' => (int) $totals->line_count,
            'sales' => (string) $totals->sales,
            'average_order' => $orders > 0 ? bcdiv((string) $totals->sales, (string) $orders, 6) : null,
            'first_date' => $totals->first_date,
            'last_date' => $totals->last_date,
        ];
    }

    public function charts(?array $ownerIds, ?string $from, ?string $to, array $dimensions): array
    {
        $lines = $this->salesLines($ownerIds, $from, $to, $dimensions);
        $charts = [
            'month' => (clone $lines)->selectRaw('SUBSTR(line.order_date, 1, 7) AS label, SUM(line.sales) AS sales')
                ->groupByRaw('SUBSTR(line.order_date, 1, 7)')->orderBy('label')->get(),
        ];
        foreach (self::DIMENSIONS as $dimension) {
            $column = 'line.'.$dimension;
            $charts[$dimension] = (clone $lines)->select($column.' as label')->selectRaw('SUM(line.sales) AS sales')
                ->groupBy($column)->orderByDesc('sales')->orderBy('label')->get();
        }

        return $charts;
    }

    public function options(?array $ownerIds): array
    {
        $lines = $this->salesLines($ownerIds);
        $options = [];
        foreach (self::DIMENSIONS as $dimension) {
            $column = 'line.'.$dimension;
            $options[$dimension] = (clone $lines)->whereNotNull($column)->distinct()->orderBy($column)->pluck($column)->all();
        }

        return $options;
    }

    public function predictionSummary(?array $ownerIds, string $cutoff, string $version): array
    {
        $start = (new \DateTimeImmutable($cutoff))->modify('-180 days')->format('Y-m-d');
        $eligible = $this->salesLines($ownerIds, $start, $cutoff)
            ->select('line.dataset_customer_id')->distinct();
        $customers = DB::table('churn_dataset_customers as customer')
            ->join('persons as person', 'person.id', '=', 'customer.person_id')
            ->leftJoinSub($eligible, 'eligible', 'eligible.dataset_customer_id', '=', 'customer.id')
            ->leftJoin('churn_predictions as prediction', function ($join) use ($cutoff, $version): void {
                $join->on('prediction.dataset_customer_id', '=', 'customer.id')
                    ->where('prediction.as_of', $cutoff)->where('prediction.model_version', $version);
            })->where('customer.source', 'superstore-sales');
        if ($ownerIds !== null) {
            $customers->whereIn('person.user_id', $ownerIds);
        }
        $counts = $customers->selectRaw(
            'COUNT(*) AS total, COUNT(eligible.dataset_customer_id) AS eligible, COUNT(prediction.id) AS saved, '
            .'COUNT(CASE WHEN prediction.risk = ? THEN 1 END) AS high_risk, '
            .'COUNT(CASE WHEN prediction.risk = ? THEN 1 END) AS medium_risk, '
            .'COUNT(CASE WHEN prediction.risk = ? THEN 1 END) AS low_risk',
            ['Alto', 'Medio', 'Bajo'],
        )->first();
        $summary = array_map('intval', (array) $counts);

        return $summary + [
            'ineligible' => $summary['total'] - $summary['eligible'],
            'missing' => $summary['total'] - $summary['saved'],
        ];
    }

    private function explorerQuery(?array $ownerIds, array $filters, array $columns): Builder
    {
        $query = $this->salesLines($ownerIds, $filters['from'] ?? null, $filters['to'] ?? null, $filters);
        $keyword = $filters['q'] ?? '';
        if ($keyword !== '') {
            $term = '%'.strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(function (Builder $search) use ($term): void {
                foreach (['order_id', 'customer_id', 'customer_name', 'product_id', 'product_name', 'city'] as $key) {
                    $search->orWhereRaw(self::COLUMNS[$key]." LIKE ? ESCAPE '!'", [$term]);
                }
            });
        }
        $selected = [];
        foreach ($columns as $key) {
            $selected[] = self::COLUMNS[$key].' as '.$key;
        }

        return $query->select($selected)
            ->orderBy(self::COLUMNS[$filters['sort'] ?? 'order_date'], $filters['direction'] ?? 'desc')
            ->orderBy('line.id');
    }

    public function page(?array $ownerIds, array $filters, array $columns): LengthAwarePaginator
    {
        return $this->explorerQuery($ownerIds, $filters, $columns)
            ->paginate(25, page: $filters['page'] ?? 1);
    }

    public function export(?array $ownerIds, array $filters, array $columns): LazyCollection
    {
        return $this->explorerQuery($ownerIds, $filters, $columns)->cursor();
    }

    public function customer(?array $ownerIds, string $customerId, int $page): array
    {
        $query = $this->salesLines($ownerIds)->where('customer.external_customer_id', $customerId);
        $customer = (clone $query)->select('customer.id', 'customer.dataset_name', 'customer.external_customer_id')->first();
        abort_if($customer === null, 404);

        $totals = (clone $query)->selectRaw('COUNT(DISTINCT line.order_id) AS orders, SUM(line.sales) AS sales')->first();
        $prediction = DB::table('churn_predictions')->where('dataset_customer_id', $customer->id)
            ->orderByDesc('as_of')->orderByDesc('predicted_at')->orderByDesc('id')
            ->first(['as_of', 'model_version']);
        $columns = ['order_id', 'order_date', 'ship_date', 'product_id', 'product_name', 'category', 'subcategory', 'sales'];

        return [
            'customer' => $customer,
            'totals' => $totals,
            'prediction' => $prediction,
            'columns' => $columns,
            'rows' => $this->detailLines($query, $columns, $page),
        ];
    }

    public function order(?array $ownerIds, string $orderId, int $page): array
    {
        $query = $this->salesLines($ownerIds)->where('line.order_id', $orderId);
        $order = (clone $query)->select('line.order_id', 'line.order_date', 'customer.dataset_name', 'customer.external_customer_id')->first();
        abort_if($order === null, 404);

        $columns = ['line_id', 'ship_date', 'ship_mode', 'product_id', 'product_name', 'category', 'subcategory', 'sales'];

        return [
            'order' => $order,
            'sales' => (clone $query)->sum('line.sales'),
            'columns' => $columns,
            'rows' => $this->detailLines($query, $columns, $page),
        ];
    }

    private function detailLines(Builder $query, array $columns, int $page): LengthAwarePaginator
    {
        return $query->select(array_map(fn ($key) => self::COLUMNS[$key].' as '.$key, $columns))
            ->orderByDesc('line.order_date')->orderBy('line.id')->paginate(25, page: $page);
    }
}

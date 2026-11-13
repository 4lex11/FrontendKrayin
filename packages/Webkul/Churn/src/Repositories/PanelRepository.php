<?php

namespace Webkul\Churn\Repositories;

use Illuminate\Support\Facades\DB;
use Webkul\Churn\Contracts\PanelRepository as PanelRepositoryContract;
use Webkul\Churn\Services\HistoricalFeatures;
use Webkul\Churn\Services\RetentionAdvice;

class PanelRepository implements PanelRepositoryContract
{
    public function detail(int $customerId, ?array $ownerIds, string $cutoff, string $version): array
    {
        $query = DB::table('churn_dataset_customers as c')->join('persons', 'persons.id', '=', 'c.person_id')
            ->where('c.id', $customerId)->where('c.source', 'superstore-sales');
        if ($ownerIds !== null) {
            $query->whereIn('persons.user_id', $ownerIds);
        }
        $customer = $query->select(['c.id', 'c.external_customer_id', 'persons.name'])->first();
        abort_if($customer === null, 404);
        $windowStart = (new \DateTimeImmutable($cutoff))->modify('-365 days')->format('Y-m-d');
        $rows = DB::table('churn_sales_lines')->where('dataset_customer_id', $customerId)
            ->where('order_date', '>', $windowStart)->where('order_date', '<=', $cutoff)->orderBy('order_date')->orderBy('line_id')
            ->get(['line_id', 'order_id', 'order_date', 'sales', 'category', 'subcategory'])
            ->map(function ($row): array {
                $result = (array) $row;
                $result['sales'] = (float) $result['sales'];

                return $result;
            })->all();
        $payload = [
            'customer_id' => $customer->external_customer_id, 'as_of' => $cutoff,
            'history_start' => DB::table('churn_sales_lines')->where('source', 'superstore-sales')->min('order_date'),
            'transactions' => $rows,
        ];
        $prediction = DB::table('churn_predictions')->where('dataset_customer_id', $customerId)->where('as_of', $cutoff)->where('model_version', $version)->first();
        $inputMatches = $prediction ? hash_equals($prediction->input_sha256, hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION))) : null;
        $history = DB::table('churn_predictions')->where('dataset_customer_id', $customerId)
            ->orderByDesc('as_of')->orderByDesc('predicted_at')->orderByDesc('id')->paginate(10, ['*'], 'history_page')->withQueryString();
        $runs = DB::table('churn_prediction_runs')->where('dataset_customer_id', $customerId)->where('as_of', $cutoff)
            ->orderByDesc('id')->limit(10)->get(['status', 'started_at', 'finished_at', 'error_code', 'http_status']);

        $statistics = (new HistoricalFeatures)->summarize($rows, $cutoff);

        return [
            'advice' => (new RetentionAdvice)->recommend($prediction, $statistics, $inputMatches),
            'customer' => $customer, 'prediction' => $prediction, 'history' => $history, 'runs' => $runs,
            'inputMatches' => $inputMatches,
            'statistics' => $statistics,
        ];
    }

    public function listing(?array $ownerIds, array $filters): array
    {
        $latestRuns = DB::table('churn_prediction_runs')->where('as_of', $filters['as_of'])
            ->selectRaw('dataset_customer_id, MAX(id) as run_id')->groupBy('dataset_customer_id');
        $query = DB::table('churn_dataset_customers as c')
            ->join('persons as person', 'person.id', '=', 'c.person_id')
            ->leftJoin('churn_predictions as prediction', function ($join) use ($filters) {
                $join->on('prediction.dataset_customer_id', '=', 'c.id')
                    ->where('prediction.as_of', $filters['as_of'])->where('prediction.model_version', config('churn.api.model_version'));
            })
            ->leftJoinSub($latestRuns, 'latest_run', fn ($join) => $join->on('latest_run.dataset_customer_id', '=', 'c.id'))
            ->leftJoin('churn_prediction_runs as run', 'run.id', '=', 'latest_run.run_id')
            ->where('c.source', 'superstore-sales');
        if ($ownerIds !== null) {
            $query->whereIn('person.user_id', $ownerIds);
        }
        if ($filters['q'] !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($q) => $q->whereRaw("person.name LIKE ? ESCAPE '!'", [$term])
                ->orWhereRaw("c.external_customer_id LIKE ? ESCAPE '!'", [$term]));
        }
        if ($filters['risk'] !== '') {
            $query->where('prediction.risk', $filters['risk']);
        }
        match ($filters['status']) {
            'saved' => $query->whereNotNull('prediction.id'),
            'missing' => $query->whereNull('prediction.id'),
            'failed', 'running' => $query->where('run.status', $filters['status']),
            default => null,
        };
        $counts = (clone $query)->selectRaw("COUNT(*) as total, COUNT(prediction.id) as saved, SUM(CASE WHEN prediction.risk = 'Alto' THEN 1 ELSE 0 END) as high_count, SUM(CASE WHEN prediction.risk = 'Medio' THEN 1 ELSE 0 END) as medium_count, SUM(CASE WHEN prediction.risk = 'Bajo' THEN 1 ELSE 0 END) as low_count, SUM(CASE WHEN prediction.id IS NULL THEN 1 ELSE 0 END) as missing_count")->first();
        $summary = array_map(fn ($v) => (int) $v, (array) $counts);
        if ($filters['sort'] === 'customer') {
            $query->orderBy('person.name');
        } else {
            $query->orderByRaw('CASE WHEN prediction.probability IS NULL THEN 1 ELSE 0 END')
                ->orderBy('prediction.probability', $filters['sort'] === 'risk_asc' ? 'asc' : 'desc');
        }
        $rows = $query->orderBy('c.external_customer_id')->select([
            'c.id', 'c.external_customer_id', 'person.name', 'prediction.id as prediction_id',
            'prediction.probability', 'prediction.risk', 'prediction.predicted_at', 'prediction.limited_history',
            'prediction.input_sha256', 'prediction.automatic_action_allowed', 'prediction.model_status',
            'run.status as run_status', 'run.finished_at as run_finished_at',
        ])->paginate(20)->withQueryString();

        // Fetch the page history in one query; no per-customer queries or API calls.
        $cutoff = $filters['as_of'];
        $start = (new \DateTimeImmutable($cutoff))->modify('-365 days')->format('Y-m-d');
        $sales = DB::table('churn_sales_lines')->whereIn('dataset_customer_id', $rows->pluck('id'))
            ->where('order_date', '>', $start)->where('order_date', '<=', $cutoff)
            ->orderBy('order_date')->orderBy('line_id')
            ->get(['dataset_customer_id', 'line_id', 'order_id', 'order_date', 'sales', 'category', 'subcategory'])->groupBy('dataset_customer_id');
        $historyStart = DB::table('churn_sales_lines')->where('source', 'superstore-sales')->min('order_date');
        foreach ($rows as $row) {
            $transactions = ($sales->get($row->id) ?? collect())->map(function ($line): array {
                $line = (array) $line;
                unset($line['dataset_customer_id']);
                $line['sales'] = (float) $line['sales'];

                return $line;
            })->all();
            $payload = ['customer_id' => $row->external_customer_id, 'as_of' => $cutoff, 'history_start' => $historyStart, 'transactions' => $transactions];
            $matches = $row->prediction_id ? hash_equals($row->input_sha256, hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION))) : null;
            $row->advice = (new RetentionAdvice)->recommend($row->prediction_id ? $row : null, (new HistoricalFeatures)->summarize($transactions, $cutoff), $matches);
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    public function visiblePredictionCount(?array $ownerIds): int
    {
        $query = DB::table('churn_predictions as predictions')
            ->join('churn_dataset_customers as customers', 'customers.id', '=', 'predictions.dataset_customer_id')
            ->join('persons', 'persons.id', '=', 'customers.person_id');
        // null means global. An empty group is not global and must return no rows.
        if ($ownerIds !== null) {
            $query->whereIn('persons.user_id', $ownerIds);
        }

        return $query->count();
    }
}

<?php

namespace Webkul\Churn\Repositories;

use Illuminate\Support\Facades\DB;
use Webkul\Churn\Contracts\PredictionRepository;
use Webkul\Churn\Contracts\WorkRepository as WorkRepositoryContract;
use Webkul\Churn\Exceptions\ChurnApiException;
use Webkul\Churn\Services\ChurnApiClient;
use Webkul\Churn\Services\CustomerInput;
use Webkul\Churn\Services\HistoricalFeatures;

class WorkRepository implements WorkRepositoryContract
{
    public function __construct(private CustomerInput $inputs, private ChurnApiClient $client, private PredictionRepository $predictions) {}

    public function enqueue(?array $ownerIds, string $cutoff, ?int $customerId, ?int $requester): array
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $cutoff);
        $start = DB::table('churn_sales_lines')->where('source', 'superstore-sales')->min('order_date');
        $end = DB::table('churn_sales_lines')->where('source', 'superstore-sales')->max('order_date');
        if (! $date || $date->format('Y-m-d') !== $cutoff || ! $start || $cutoff < $start || $cutoff > $end) {
            throw new ChurnApiException('invalid_cutoff');
        }

        return DB::transaction(function () use ($ownerIds, $cutoff, $customerId, $requester) {
            // Lock customer rows in stable order. This also serializes concurrent submissions.
            $query = DB::table('churn_dataset_customers as c')->join('persons', 'persons.id', '=', 'c.person_id')->where('c.source', 'superstore-sales');
            if ($ownerIds !== null) {
                $query->whereIn('persons.user_id', $ownerIds);
            }
            if ($customerId !== null) {
                $query->where('c.id', $customerId);
            }
            $ids = $query->orderBy('c.id')->lockForUpdate()->pluck('c.id');
            abort_if($customerId !== null && $ids->isEmpty(), 404);
            $added = $existing = 0;
            foreach ($ids as $id) {
                $identity = ['dataset_customer_id' => $id, 'as_of' => $cutoff, 'model_version' => config('churn.api.model_version')];
                if (DB::table('churn_work_items')->where($identity)->whereIn('status', ['pending', 'running'])->exists()) {
                    $existing++;

                    continue;
                }
                DB::table('churn_work_items')->insert($identity + ['requested_by' => $requester, 'status' => 'pending', 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
                $added++;
            }

            return ['added' => $added, 'existing' => $existing];
        });
    }

    public function progress(?array $ownerIds, string $cutoff): array
    {
        $latest = DB::table('churn_work_items')->where('as_of', $cutoff)->where('model_version', config('churn.api.model_version'))
            ->selectRaw('dataset_customer_id, MAX(id) as work_id')->groupBy('dataset_customer_id');
        $query = DB::table('churn_work_items as w')->joinSub($latest, 'latest', fn ($join) => $join->on('w.id', '=', 'latest.work_id'))
            ->join('churn_dataset_customers as c', 'c.id', '=', 'w.dataset_customer_id')->join('persons', 'persons.id', '=', 'c.person_id');
        if ($ownerIds !== null) {
            $query->whereIn('persons.user_id', $ownerIds);
        }
        $counts = (clone $query)->selectRaw('w.status, COUNT(*) as count')->groupBy('w.status')->pluck('count', 'status')->all();

        return ['counts' => array_map('intval', array_replace(['pending' => 0, 'running' => 0, 'completed' => 0, 'error' => 0, 'skipped' => 0], $counts)),
            'items' => $query->orderByDesc('w.id')->limit(20)->get(['w.*', 'c.external_customer_id'])];
    }

    public function process(int $limit): array
    {
        if ($limit < 1 || $limit > 1000) {
            throw new ChurnApiException('invalid_limit');
        }
        // MariaDB connection-owned advisory lock: one worker; released even if its process dies.
        // HTTP is outside transactions; the lock is not a table/row lock.
        $lock = 'churn-worker-'.substr(hash('sha256', DB::connection()->getDatabaseName()), 0, 32);
        if ((int) DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired !== 1) {
            throw new ChurnApiException('worker_busy');
        }
        $summary = ['completed' => 0, 'error' => 0, 'skipped' => 0];
        try {
            // No other queue worker can be active. Interrupted items are closed, never silently rerun.
            $interrupted = DB::table('churn_work_items')->where('status', 'running')->pluck('id');
            DB::table('churn_prediction_runs')->whereIn('work_item_id', $interrupted)->where('status', 'running')->update(['status' => 'failed', 'error_code' => 'worker_interrupted', 'finished_at' => gmdate('Y-m-d H:i:s')]);
            $summary['error'] += $interrupted->count();
            DB::table('churn_work_items')->where('status', 'running')->update(['status' => 'error', 'outcome' => 'worker_interrupted', 'finished_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
            for ($i = 0; $i < $limit; $i++) {
                $item = DB::table('churn_work_items')->where('status', 'pending')->orderBy('id')->first();
                if (! $item) {
                    break;
                }
                DB::table('churn_work_items')->where('id', $item->id)->update(['status' => 'running', 'started_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
                $status = 'error';
                $outcome = 'execution_error';
                $predictionId = null;
                try {
                    if ($item->model_version !== config('churn.api.model_version')) {
                        throw new ChurnApiException('model_configuration_changed');
                    }
                    $payload = $this->inputs->build($item->dataset_customer_id, $item->as_of);
                    $stats = (new HistoricalFeatures)->summarize($payload['transactions'], $item->as_of);
                    if (! $stats['eligible']) {
                        $status = 'skipped';
                        $outcome = 'not_eligible';
                    } else {
                        $result = $this->predictions->record($item->dataset_customer_id, $payload, fn ($input) => $this->client->predictTransactions($input), $item->id);
                        $predictionId = $result['prediction_id'];
                        $status = 'completed';
                        $outcome = $result['created'] ? 'saved' : 'reused';
                    }
                } catch (\Throwable $error) {
                    $outcome = $error instanceof ChurnApiException ? $error->reason : 'execution_error';
                }
                DB::table('churn_work_items')->where('id', $item->id)->update(['status' => $status, 'outcome' => $outcome, 'prediction_id' => $predictionId, 'finished_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
                $summary[$status]++;
            }

            return $summary;
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    }
}

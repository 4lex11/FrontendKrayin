<?php

namespace Webkul\Churn\Repositories;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Webkul\Churn\Contracts\PredictionRepository as PredictionRepositoryContract;
use Webkul\Churn\Exceptions\ChurnApiException;
use Webkul\Churn\Models\DatasetCustomer;
use Webkul\Churn\Models\Prediction;
use Webkul\Churn\Models\PredictionRun;

class PredictionRepository implements PredictionRepositoryContract
{
    public function record(int $customerId, array $payload, Closure $predict, ?int $workItemId = null): array
    {
        $customer = DatasetCustomer::findOrFail($customerId);
        if ($customer->external_customer_id !== ($payload['customer_id'] ?? null)) {
            throw new ChurnApiException('customer_mismatch');
        }
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        $run = PredictionRun::create(['dataset_customer_id' => $customerId, 'as_of' => $payload['as_of'], 'input_sha256' => $hash, 'status' => 'running', 'work_item_id' => $workItemId, 'started_at' => gmdate('Y-m-d H:i:s')]);
        try {
            // HTTP must not hold database locks or keep a transaction open.
            $response = $predict($payload);
            if (($response['customer_id'] ?? null) !== $payload['customer_id'] || ($response['as_of'] ?? null) !== $payload['as_of']
                || ($response['automatic_action_allowed'] ?? null) !== false || ($response['model_status'] ?? null) !== 'experimental') {
                throw new ChurnApiException('invalid_response');
            }

            return DB::transaction(function () use ($customerId, $payload, $response, $hash, $run) {
                // Serializes competing writes for this customer, including an absent prediction key.
                DatasetCustomer::whereKey($customerId)->lockForUpdate()->firstOrFail();
                $prediction = Prediction::where('dataset_customer_id', $customerId)->where('as_of', $payload['as_of'])->where('model_version', $response['model_version'])->first();
                $created = $prediction === null;
                if ($prediction) {
                    $previous = $prediction->response;
                    $comparable = $response;
                    unset($previous['predicted_at'], $comparable['predicted_at']);
                    if ($prediction->input_sha256 !== $hash || $previous != $comparable) {
                        throw new ChurnApiException('prediction_conflict');
                    }
                } else {
                    $prediction = Prediction::create([
                        'dataset_customer_id' => $customerId, 'as_of' => $payload['as_of'], 'model_version' => $response['model_version'],
                        'schema_version' => $response['schema_version'], 'horizon_days' => $response['horizon_days'],
                        'probability' => $response['probabilidad_churn'], 'risk' => $response['categoria_riesgo'],
                        'limited_history' => $response['limited_history'], 'automatic_action_allowed' => false,
                        'model_status' => $response['model_status'], 'input_sha256' => $hash,
                        'predicted_at' => (new DateTimeImmutable($response['predicted_at']))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
                        'response' => $response,
                    ]);
                }
                $run->update(['status' => $created ? 'succeeded' : 'reused', 'prediction_id' => $prediction->id, 'finished_at' => gmdate('Y-m-d H:i:s')]);

                return ['run_id' => $run->id, 'prediction_id' => $prediction->id, 'created' => $created, 'prediction' => $prediction->response];
            });
        } catch (\Throwable $error) {
            $safe = $error instanceof ChurnApiException ? $error : new ChurnApiException('persistence_or_execution_error');
            $run->update(['status' => 'failed', 'error_code' => $safe->reason, 'http_status' => $safe->httpStatus, 'attempts' => $safe->attempts, 'finished_at' => gmdate('Y-m-d H:i:s')]);
            throw $safe;
        }
    }

    public function history(int $customerId, int $page = 1): array
    {
        return Prediction::where('dataset_customer_id', $customerId)->orderByDesc('as_of')->orderByDesc('predicted_at')->orderByDesc('id')
            ->forPage(max(1, $page), 50)->get()->toArray();
    }
}

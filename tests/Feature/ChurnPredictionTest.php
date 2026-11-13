<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Webkul\Churn\Contracts\PredictionRepository;
use Webkul\Churn\Exceptions\ChurnApiException;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->customer = DB::table('churn_dataset_customers')->first();
    $this->payload = ['customer_id' => $this->customer->external_customer_id, 'as_of' => '2018-06-30', 'history_start' => '2015-01-03', 'transactions' => [['line_id' => 'TEST', 'sales' => 10.0]]];
    $this->response = ['status' => 'success', 'customer_id' => $this->payload['customer_id'], 'as_of' => '2018-06-30', 'predicted_at' => '2026-10-28T12:00:00.123456-05:00', 'model_version' => 'crm03-test', 'schema_version' => 'churn180-v1', 'horizon_days' => 180, 'probabilidad_churn' => 0.34, 'categoria_riesgo' => 'Medio', 'accion_sugerida' => 'Revisión humana', 'model_status' => 'experimental', 'automatic_action_allowed' => false, 'limited_history' => true];
    $this->repository = app(PredictionRepository::class);
});

it('stores traced predictions and commits run status together', function () {
    $saved = $this->repository->record($this->customer->id, $this->payload, fn () => $this->response);
    $row = DB::table('churn_predictions')->where('id', $saved['prediction_id'])->first();
    expect($saved['created'])->toBeTrue()->and($row->model_version)->toBe('crm03-test')
        ->and($row->predicted_at)->toBe('2026-10-28 17:00:00.123456')
        ->and(DB::table('churn_prediction_runs')->where('id', $saved['run_id'])->value('status'))->toBe('succeeded');
});

it('reuses an identical prediction without replacing its original timestamp', function () {
    $first = $this->repository->record($this->customer->id, $this->payload, fn () => $this->response);
    $this->response['predicted_at'] = '2026-10-29T01:00:00Z';
    $second = $this->repository->record($this->customer->id, $this->payload, fn () => $this->response);
    expect($second['prediction_id'])->toBe($first['prediction_id'])->and($second['created'])->toBeFalse()
        ->and($second['prediction']['predicted_at'])->toBe($first['prediction']['predicted_at'])
        ->and($second['run_id'])->not->toBe($first['run_id'])
        ->and(DB::table('churn_prediction_runs')->where('id', $second['run_id'])->value('status'))->toBe('reused');
});

it('preserves the valid prediction when a repeat fails', function () {
    $saved = $this->repository->record($this->customer->id, $this->payload, fn () => $this->response);
    expect(fn () => $this->repository->record($this->customer->id, $this->payload, fn () => throw new ChurnApiException('http_error', 401, 1)))->toThrow(ChurnApiException::class);
    expect(DB::table('churn_predictions')->where('id', $saved['prediction_id'])->value('probability'))->toBe(0.34);
    $run = DB::table('churn_prediction_runs')->orderByDesc('id')->first();
    expect($run->status)->toBe('failed')->and($run->http_status)->toBe(401)->and($run->attempts)->toBe(1)->and($run->prediction_id)->toBeNull();
});

it('rejects conflicting input or results for the same identity', function ($changeInput) {
    $saved = $this->repository->record($this->customer->id, $this->payload, fn () => $this->response);
    if ($changeInput) {
        $this->payload['transactions'][0]['sales'] = 20.0;
    } else {
        $this->response['probabilidad_churn'] = 0.8;
    }
    expect(fn () => $this->repository->record($this->customer->id, $this->payload, fn () => $this->response))->toThrow(ChurnApiException::class, 'prediction_conflict');
    expect(DB::table('churn_predictions')->where('id', $saved['prediction_id'])->value('probability'))->toBe(0.34);
})->with([true, false]);

it('retains separate versions and cuts ordered by observation date', function () {
    $this->repository->record($this->customer->id, $this->payload, fn () => $this->response);
    $this->response['model_version'] = 'crm03-test-2';
    $newVersion = $this->repository->record($this->customer->id, $this->payload, fn () => $this->response);
    $this->payload['as_of'] = $this->response['as_of'] = '2017-12-31';
    $older = $this->repository->record($this->customer->id, $this->payload, fn () => $this->response);
    $history = $this->repository->history($this->customer->id);
    $ids = array_column($history, 'id');
    expect(array_search($older['prediction_id'], $ids))->toBeGreaterThan(array_search($newVersion['prediction_id'], $ids));
});

it('does not store secrets from unexpected errors', function () {
    expect(fn () => $this->repository->record($this->customer->id, $this->payload, fn () => throw new RuntimeException('secret-personal-body')))->toThrow(ChurnApiException::class, 'persistence_or_execution_error');
    $run = DB::table('churn_prediction_runs')->orderByDesc('id')->first();
    expect($run->error_code)->toBe('persistence_or_execution_error')->and(json_encode($run))->not->toContain('secret-personal-body');
});

it('rejects responses for another customer or automatic actions', function ($field, $value) {
    $this->response[$field] = $value;
    expect(fn () => $this->repository->record($this->customer->id, $this->payload, fn () => $this->response))->toThrow(ChurnApiException::class, 'invalid_response');
})->with([['customer_id', 'OTHER'], ['automatic_action_allowed', true]]);

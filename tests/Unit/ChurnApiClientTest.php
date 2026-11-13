<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Webkul\Churn\Exceptions\ChurnApiException;
use Webkul\Churn\Services\ChurnApiClient;

beforeEach(function () {
    $this->key = tempnam(sys_get_temp_dir(), 'key');
    $this->ca = tempnam(sys_get_temp_dir(), 'ca');
    file_put_contents($this->key, str_repeat('x', 40));
    $this->settings = ['url' => 'https://localhost:8443', 'key_file' => $this->key, 'ca_file' => $this->ca, 'model_version' => 'churn180-v1'];
    $this->payload = ['customer_id' => 'C1', 'as_of' => '2018-06-30', 'history_start' => '2015-01-03', 'transactions' => [['line_id' => '1']]];
    $this->answer = ['status' => 'success', 'customer_id' => 'C1', 'as_of' => '2018-06-30', 'predicted_at' => '2026-10-28T00:00:00+00:00', 'model_version' => 'churn180-v1', 'schema_version' => 'churn180-v1', 'horizon_days' => 180, 'probabilidad_churn' => 0.35, 'categoria_riesgo' => 'Medio', 'accion_sugerida' => 'Revisión humana', 'model_status' => 'experimental', 'automatic_action_allowed' => false, 'limited_history' => false];
    $this->history = [];
    $this->elapsed = 0.0;
    $this->delays = [];
    $this->makeClient = function (array $responses): ChurnApiClient {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new ChurnApiClient(new Client(['handler' => $stack]), $this->settings, fn () => $this->elapsed, function ($delay) {
            $this->delays[] = $delay;
            $this->elapsed += $delay;
        });
    };
});

afterEach(function () {
    unlink($this->key);
    unlink($this->ca);
});

it('validates the response and uses TLS credentials and bounded timeouts', function () {
    $result = ($this->makeClient)([new Response(200, [], json_encode($this->answer))])->predictTransactions($this->payload);
    expect($result)->toBe($this->answer);
    $request = $this->history[0];
    expect($request['options']['verify'])->toBe($this->ca)
        ->and($request['options']['timeout'])->toBe(15)
        ->and($request['options']['connect_timeout'])->toBe(3)
        ->and($request['request']->getHeaderLine('X-API-Key'))->toBe(str_repeat('x', 40))
        ->and((string) $request['request']->getUri())->toBe('https://localhost:8443/v1/predict/transactions');
});

it('does not retry permanent HTTP errors or redirects', function ($status) {
    try {
        ($this->makeClient)([new Response($status, ['Location' => 'https://other.invalid'], 'HTML error')])->predictTransactions($this->payload);
        $this->fail('Expected error');
    } catch (ChurnApiException $e) {
        expect($e->httpStatus)->toBe($status)->and($e->attempts)->toBe(1)->and($this->history)->toHaveCount(1);
    }
})->with([301, 302, 400, 401, 403, 404, 413, 422, 426]);

it('retries transient statuses and respects Retry After', function ($status) {
    $client = ($this->makeClient)([new Response($status, ['Retry-After' => '3'], 'unavailable'), new Response(200, [], json_encode($this->answer))]);
    expect($client->predictTransactions($this->payload))->toBe($this->answer)->and($this->history)->toHaveCount(2)->and($this->delays[0])->toBeGreaterThanOrEqual(3);
})->with([408, 500, 502, 503, 504]);

it('bounds attempts and refuses Retry After beyond the budget', function ($headers, $attempts, $reason) {
    try {
        ($this->makeClient)(array_fill(0, 3, new Response(503, $headers)))->predictTransactions($this->payload);
        $this->fail('Expected error');
    } catch (ChurnApiException $e) {
        expect($e->attempts)->toBe($attempts)->and($e->reason)->toBe($reason)->and(count($this->history))->toBe($attempts);
    }
})->with([[[], 3, 'retries_exhausted'], [['Retry-After' => '60'], 1, 'budget_exhausted'], [['Retry-After' => gmdate(DATE_RFC7231, time() + 120)], 1, 'budget_exhausted']]);

it('retries connection failures but does not retry TLS failures or expose their messages', function ($errno, $attempts, $reason) {
    $error = new ConnectException('secret payload must not escape', new Request('POST', 'https://localhost'), null, ['errno' => $errno]);
    try {
        ($this->makeClient)([$error, $error, $error])->predictTransactions($this->payload);
        $this->fail('Expected error');
    } catch (ChurnApiException $e) {
        expect($e->attempts)->toBe($attempts)->and($e->reason)->toBe($reason)->and($e->getPrevious())->toBeNull()->and($e->getMessage())->not->toContain('secret');
    }
})->with([[7, 3, 'retries_exhausted'], [28, 3, 'retries_exhausted'], [60, 1, 'transport_or_tls_error'], [77, 1, 'transport_or_tls_error']]);

it('rejects incompatible or unsafe prediction responses', function ($field, $value) {
    $answer = array_replace($this->answer, [$field => $value]);
    expect(fn () => ($this->makeClient)([new Response(200, [], json_encode($answer))])->predictTransactions($this->payload))->toThrow(ChurnApiException::class, 'invalid_response');
})->with([['customer_id', 'other'], ['as_of', '2018-07-01'], ['model_version', 'v2'], ['schema_version', 'v2'], ['horizon_days', 90], ['probabilidad_churn', -0.1], ['probabilidad_churn', 1.1], ['probabilidad_churn', '0.3'], ['automatic_action_allowed', true], ['limited_history', null], ['predicted_at', 'bad'], ['categoria_riesgo', 'unknown']]);

it('rejects invalid JSON and oversized responses', function ($body) {
    expect(fn () => ($this->makeClient)([new Response(200, [], $body)])->predictTransactions($this->payload))->toThrow(ChurnApiException::class, 'invalid_response');
})->with(['<html>error</html>', str_repeat('x', 65537)]);

it('rejects insecure configuration before sending credentials', function ($url) {
    $this->settings['url'] = $url;
    expect(fn () => ($this->makeClient)([])->predictTransactions($this->payload))->toThrow(ChurnApiException::class, 'invalid_configuration');
    expect($this->history)->toBeEmpty();
})->with(['http://localhost', 'https://user:password@localhost', 'https://localhost?token=x']);

it('rejects excessive transactions and non finite JSON', function () {
    $this->payload['transactions'] = array_fill(0, 5001, []);
    expect(fn () => ($this->makeClient)([])->predictTransactions($this->payload))->toThrow(ChurnApiException::class, 'invalid_request');
    $this->payload['transactions'] = [['sales' => INF]];
    expect(fn () => ($this->makeClient)([])->predictTransactions($this->payload))->toThrow(ChurnApiException::class, 'invalid_request');
});

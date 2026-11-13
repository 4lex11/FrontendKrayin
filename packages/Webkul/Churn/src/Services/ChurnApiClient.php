<?php

namespace Webkul\Churn\Services;

use Closure;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use Webkul\Churn\Exceptions\ChurnApiException;

class ChurnApiClient
{
    private Closure $clock;

    private Closure $sleep;

    public function __construct(private ClientInterface $http, private array $settings, ?Closure $clock = null, ?Closure $sleep = null)
    {
        $this->clock = $clock ?? fn (): float => hrtime(true) / 1e9;
        $this->sleep = $sleep ?? function (float $seconds): void {
            usleep((int) ($seconds * 1e6));
        };
    }

    public function predictTransactions(array $payload): array
    {
        if (! is_string($payload['customer_id'] ?? null) || ! is_string($payload['as_of'] ?? null)
            || ! is_array($payload['transactions'] ?? null) || count($payload['transactions']) < 1 || count($payload['transactions']) > 5000) {
            throw new ChurnApiException('invalid_request');
        }
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (\JsonException) {
            throw new ChurnApiException('invalid_request');
        }
        if (strlen($body) > 2000000) {
            throw new ChurnApiException('request_too_large');
        }
        $url = $this->settings['url'] ?? '';
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new ChurnApiException('invalid_configuration');
        }
        $keyFile = $this->settings['key_file'] ?? '';
        $ca = $this->settings['ca_file'] ?? '';
        if (! is_string($keyFile) || ! is_file($keyFile) || ! is_readable($keyFile) || filesize($keyFile) > 1024
            || ! is_string($ca) || ! is_file($ca) || ! is_readable($ca)) {
            throw new ChurnApiException('invalid_configuration');
        }
        $key = rtrim((string) file_get_contents($keyFile), "\r\n");
        $key = preg_replace('/^\xEF\xBB\xBF/', '', $key);
        if (! preg_match('/^[\x21-\x7E]{32,512}$/D', $key)) {
            throw new ChurnApiException('invalid_configuration');
        }
        $started = ($this->clock)();
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $remaining = 50 - (($this->clock)() - $started);
            if ($remaining <= 0) {
                throw new ChurnApiException('budget_exhausted', null, $attempt - 1);
            }
            $status = null;
            $delay = $attempt + random_int(0, 250) / 1000;
            try {
                $response = $this->http->request('POST', rtrim($url, '/').'/v1/predict/transactions', [
                    'headers' => ['X-API-Key' => $key, 'Accept' => 'application/json', 'Content-Type' => 'application/json'],
                    'body' => $body, 'verify' => $ca, 'allow_redirects' => false, 'http_errors' => false,
                    'connect_timeout' => min(3, $remaining), 'timeout' => min(15, $remaining),
                    'proxy' => '',
                ]);
                $status = $response->getStatusCode();
                if ($status === 200) {
                    $raw = $response->getBody()->read(65537);
                    try {
                        $data = strlen($raw) <= 65536 ? json_decode($raw, true, 32, JSON_THROW_ON_ERROR) : null;
                    } catch (\JsonException) {
                        $data = null;
                    }

                    return $this->validateResponse($data, $payload, $attempt);
                }
                if (! in_array($status, [408, 500, 502, 503, 504], true)) {
                    throw new ChurnApiException('http_error', $status, $attempt);
                }
                $retry = $response->getHeaderLine('Retry-After');
                if ($retry !== '') {
                    $seconds = ctype_digit($retry) ? (float) $retry : (($stamp = strtotime($retry)) !== false ? max(0, $stamp - time()) : 0);
                    $delay = max($delay, $seconds);
                }
            } catch (TransferException $error) {
                $context = method_exists($error, 'getHandlerContext') ? $error->getHandlerContext() : [];
                // Only retry known transient cURL failures. TLS/configuration failures fail closed.
                if (! in_array($context['errno'] ?? null, [6, 7, 18, 28, 52, 55, 56], true)) {
                    throw new ChurnApiException('transport_or_tls_error', null, $attempt);
                }
            }
            if ($attempt === 3) {
                throw new ChurnApiException('retries_exhausted', $status, $attempt);
            }
            if ($delay >= 50 - (($this->clock)() - $started)) {
                throw new ChurnApiException('budget_exhausted', $status, $attempt);
            }
            ($this->sleep)($delay);
        }
        throw new ChurnApiException('retries_exhausted');
    }

    private function validateResponse(mixed $data, array $payload, int $attempt): array
    {
        $probability = is_array($data) ? ($data['probabilidad_churn'] ?? null) : null;
        if (! is_array($data) || ($data['status'] ?? null) !== 'success'
            || ($data['customer_id'] ?? null) !== $payload['customer_id'] || ($data['as_of'] ?? null) !== $payload['as_of']
            || ($data['schema_version'] ?? null) !== 'churn180-v1'
            || ($data['model_version'] ?? null) !== ($this->settings['model_version'] ?? 'churn180-v1')
            || ($data['horizon_days'] ?? null) !== 180 || ($data['model_status'] ?? null) !== 'experimental'
            || ($data['automatic_action_allowed'] ?? null) !== false || ! is_bool($data['limited_history'] ?? null)
            || (! is_float($probability) && ! is_int($probability)) || ! is_finite((float) $probability) || $probability < 0 || $probability > 1
            || ! in_array($data['categoria_riesgo'] ?? null, ['Bajo', 'Medio', 'Alto'], true)
            || ! is_string($data['accion_sugerida'] ?? null)
            || ! is_string($data['predicted_at'] ?? null) || ! preg_match('/^\d{4}-\d{2}-\d{2}T.+(?:Z|[+-]\d{2}:\d{2})$/D', $data['predicted_at'])
            || strtotime($data['predicted_at']) === false) {
            throw new ChurnApiException('invalid_response', 200, $attempt);
        }

        return array_intersect_key($data, array_flip(['status', 'customer_id', 'as_of', 'predicted_at', 'model_version', 'schema_version', 'horizon_days', 'probabilidad_churn', 'categoria_riesgo', 'accion_sugerida', 'model_status', 'automatic_action_allowed', 'limited_history']));
    }
}

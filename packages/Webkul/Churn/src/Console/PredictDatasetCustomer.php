<?php

namespace Webkul\Churn\Console;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Webkul\Churn\Contracts\PredictionRepository;
use Webkul\Churn\Exceptions\ChurnApiException;
use Webkul\Churn\Services\ChurnApiClient;

class PredictDatasetCustomer extends Command
{
    protected $signature = 'churn:predict-customer {customer_id} {--as-of=2018-06-30} {--save : Guardar resultado e historial de ejecución}';

    protected $description = 'Consulta la API HTTPS con ventas histÃ³ricas; guarda solo con --save y no realiza acciones.';

    public function handle(ChurnApiClient $client, PredictionRepository $predictions): int
    {
        try {
            $cutoff = (string) $this->option('as-of');
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $cutoff);
            if (! $date || $date->format('Y-m-d') !== $cutoff) {
                throw new ChurnApiException('invalid_cutoff');
            }
            $id = (string) $this->argument('customer_id');
            $customer = DB::table('churn_dataset_customers')->where('source', 'superstore-sales')->where('external_customer_id', $id)->first();
            $start = DB::table('churn_sales_lines')->where('source', 'superstore-sales')->min('order_date');
            $end = DB::table('churn_sales_lines')->where('source', 'superstore-sales')->max('order_date');
            if (! $customer || ! $start || $cutoff < $start || $cutoff > $end) {
                throw new ChurnApiException('customer_or_cutoff_not_available');
            }
            $rows = DB::table('churn_sales_lines')->where('dataset_customer_id', $customer->id)
                ->where('order_date', '>', $date->modify('-365 days')->format('Y-m-d'))->where('order_date', '<=', $cutoff)
                ->orderBy('order_date')->orderBy('line_id')->limit(5001)
                ->get(['line_id', 'order_id', 'order_date', 'sales', 'category', 'subcategory'])
                ->map(function ($row): array {
                    $value = (array) $row;
                    $value['sales'] = (float) $value['sales'];

                    return $value;
                })->all();
            $payload = ['customer_id' => $id, 'as_of' => $cutoff, 'history_start' => $start, 'transactions' => $rows];
            $result = $this->option('save')
                ? $predictions->record($customer->id, $payload, fn (array $request): array => $client->predictTransactions($request))
                : $client->predictTransactions($payload);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (ChurnApiException $error) {
            $this->line(json_encode(['status' => 'error', 'reason' => $error->reason, 'http_status' => $error->httpStatus, 'attempts' => $error->attempts]));

            return self::FAILURE;
        }
    }
}

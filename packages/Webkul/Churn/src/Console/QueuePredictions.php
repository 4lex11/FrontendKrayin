<?php

namespace Webkul\Churn\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Webkul\Churn\Contracts\WorkRepository;
use Webkul\Churn\Exceptions\ChurnApiException;

class QueuePredictions extends Command
{
    protected $signature = 'churn:queue {--customer= : External customer ID} {--all : All dataset customers} {--as-of=2018-06-30}';

    protected $description = 'Queue a historical dataset customer or explicitly queue all customers (local administrator command).';

    public function handle(WorkRepository $work): int
    {
        if ((bool) $this->option('customer') === (bool) $this->option('all')) {
            $this->error('Choose exactly one of --customer or --all');

            return self::FAILURE;
        }
        $id = $this->option('customer') ? DB::table('churn_dataset_customers')->where('source', 'superstore-sales')->where('external_customer_id', $this->option('customer'))->value('id') : null;
        if ($this->option('customer') && ! $id) {
            $this->error('Unknown customer');

            return self::FAILURE;
        }
        try {
            $this->line(json_encode($work->enqueue(null, (string) $this->option('as-of'), $id, null), JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (ChurnApiException $e) {
            $this->error($e->reason);

            return self::FAILURE;
        }
    }
}

<?php

namespace Webkul\Churn\Console;

use Illuminate\Console\Command;
use Webkul\Churn\Contracts\WorkRepository;
use Webkul\Churn\Exceptions\ChurnApiException;

class ProcessPredictions extends Command
{
    protected $signature = 'churn:work {--limit=100 : Maximum number of queued customers to process}';

    protected $description = 'Process the persistent Churn queue; run again to continue pending work.';

    public function handle(WorkRepository $work): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 1000) {
            $this->error('limit must be between 1 and 1000');

            return self::FAILURE;
        }
        try {
            $result = $work->process($limit);
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));

            return $result['error'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (ChurnApiException $error) {
            $this->error($error->reason);

            return self::FAILURE;
        }
    }
}

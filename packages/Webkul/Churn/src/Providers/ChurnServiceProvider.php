<?php

namespace Webkul\Churn\Providers;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Webkul\Churn\Console\ImportSuperstore;
use Webkul\Churn\Console\PredictDatasetCustomer;
use Webkul\Churn\Console\ProcessPredictions;
use Webkul\Churn\Console\QueuePredictions;
use Webkul\Churn\Contracts\DatasetRepository;
use Webkul\Churn\Contracts\PanelRepository;
use Webkul\Churn\Contracts\PredictionRepository;
use Webkul\Churn\Contracts\ReviewRepository;
use Webkul\Churn\Contracts\WorkRepository;
use Webkul\Churn\Services\ChurnApiClient;

class ChurnServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->commands([ImportSuperstore::class, PredictDatasetCustomer::class, ProcessPredictions::class, QueuePredictions::class]);
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'churn');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'churn');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/acl.php', 'acl');
        $this->app->bind(ReviewRepository::class, \Webkul\Churn\Repositories\ReviewRepository::class);
        $this->app->bind(WorkRepository::class, \Webkul\Churn\Repositories\WorkRepository::class);
        $this->app->bind(PanelRepository::class, \Webkul\Churn\Repositories\PanelRepository::class);
        $this->app->bind(PredictionRepository::class, \Webkul\Churn\Repositories\PredictionRepository::class);
        $this->mergeConfigFrom(__DIR__.'/../Config/api.php', 'churn.api');
        $this->app->bind(ChurnApiClient::class, fn () => new ChurnApiClient(new Client, config('churn.api')));
        $this->app->bind(DatasetRepository::class, \Webkul\Churn\Repositories\DatasetRepository::class);
        $this->mergeConfigFrom(
            __DIR__.'/../Config/menu.php', 'menu.admin'
        );
    }
}

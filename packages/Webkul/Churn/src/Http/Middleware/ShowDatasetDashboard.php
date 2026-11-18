<?php

namespace Webkul\Churn\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Churn\Contracts\DatasetDashboardRepository;
use Webkul\Churn\Http\Controllers\DatasetDashboardController;

class ShowDatasetDashboard
{
    public function handle(Request $request, Closure $next, DatasetDashboardRepository $dashboard): mixed
    {
        if ($request->query('view') === 'crm' || ! bouncer()->hasPermission('churn')) {
            return $next($request);
        }

        return new Response(app(DatasetDashboardController::class)->index($request, $dashboard)->render());
    }
}

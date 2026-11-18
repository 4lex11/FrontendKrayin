<?php

namespace Webkul\Churn\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Churn\Contracts\DatasetDashboardRepository;

class DatasetDashboardController extends Controller
{
    public function index(Request $request, DatasetDashboardRepository $dashboard): View
    {
        abort_unless(bouncer()->hasPermission('churn'), 403);

        $ownerIds = bouncer()->getAuthorizedUserIds();
        $options = $dashboard->options($ownerIds);
        $filters = $this->filters($request, $options);
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $cutoff = $filters['prediction_cutoff'] ?? '2018-06-30';
        $version = config('churn.api.model_version');

        return view('churn::dataset.index', [
            'dashboardRoute' => $request->routeIs('admin.dashboard.index') ? 'admin.dashboard.index' : 'admin.churn.dataset.index',
            'filters' => $filters,
            'options' => $options,
            'summary' => $dashboard->summary($ownerIds, $from, $to, $filters),
            'charts' => $dashboard->charts($ownerIds, $from, $to, $filters),
            'cutoff' => $cutoff,
            'modelVersion' => $version,
            'predictions' => $dashboard->predictionSummary($ownerIds, $cutoff, $version),
        ]);
    }

    public function explore(Request $request, DatasetDashboardRepository $dashboard): View|StreamedResponse
    {
        abort_unless(bouncer()->hasPermission('churn'), 403);

        $ownerIds = bouncer()->getAuthorizedUserIds();
        $options = $dashboard->options($ownerIds);
        $filters = $this->filters($request, $options, explorer: true);
        $available = array_keys(DatasetDashboardRepository::COLUMNS);
        $columns = array_values(array_intersect($available, $filters['columns'] ?? $available));

        if (($filters['format'] ?? null) === 'csv') {
            $rows = $dashboard->export($ownerIds, $filters, $columns);

            return response()->streamDownload(function () use ($rows, $columns): void {
                $output = fopen('php://output', 'wb');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, array_map(fn ($column) => __('churn::dataset.columns.'.$column), $columns), escape: '');
                foreach ($rows as $row) {
                    $values = array_map(function (string $column) use ($row): string {
                        $value = (string) $row->$column;

                        return preg_match('/^(?:[\x00-\x20]*[=+@-]|[\t\r\n])/D', $value) ? "'".$value : $value;
                    }, $columns);
                    fputcsv($output, $values, escape: '');
                }
                fclose($output);
            }, 'superstore-ventas.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }

        return view('churn::dataset.explore', [
            'filters' => $filters,
            'options' => $options,
            'available' => $available,
            'columns' => $columns,
            'rows' => $dashboard->page($ownerIds, $filters, $columns)->appends(array_diff_key($filters, ['page' => true])),
        ]);
    }

    public function customer(string $customer, Request $request, DatasetDashboardRepository $dashboard): View
    {
        abort_unless(bouncer()->hasPermission('churn'), 403);
        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);

        return view('churn::dataset.customer', $dashboard->customer(
            bouncer()->getAuthorizedUserIds(), $customer, (int) ($data['page'] ?? 1),
        ));
    }

    public function order(string $order, Request $request, DatasetDashboardRepository $dashboard): View
    {
        abort_unless(bouncer()->hasPermission('churn'), 403);
        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);

        return view('churn::dataset.order', $dashboard->order(
            bouncer()->getAuthorizedUserIds(), $order, (int) ($data['page'] ?? 1),
        ));
    }

    private function filters(Request $request, array $options, bool $explorer = false): array
    {
        $rules = [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => array_filter([
                'nullable',
                'date_format:Y-m-d',
                $request->filled('from') ? 'after_or_equal:from' : null,
            ]),
        ];
        $attributes = [
            'from' => __('churn::dataset.from'),
            'to' => __('churn::dataset.to'),
        ];
        foreach ($options as $dimension => $values) {
            $rules[$dimension] = ['nullable', 'string', Rule::in($values)];
            $attributes[$dimension] = __('churn::dataset.dimensions.'.$dimension);
        }
        if ($explorer) {
            $columns = array_keys(DatasetDashboardRepository::COLUMNS);
            $rules += [
                'q' => ['nullable', 'string', 'max:100'],
                'sort' => ['nullable', 'string', Rule::in($columns)],
                'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
                'columns' => ['sometimes', 'array', 'min:1', 'max:18'],
                'columns.*' => ['required', 'string', 'distinct', Rule::in($columns)],
                'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
                'format' => ['nullable', 'in:csv'],
            ];
        } else {
            $rules['prediction_cutoff'] = ['nullable', 'date_format:Y-m-d', 'after_or_equal:2015-01-03', 'before_or_equal:2018-12-30'];
            $attributes['prediction_cutoff'] = __('churn::dataset.prediction_cutoff');
        }

        return $request->validate($rules, [], $attributes);
    }
}

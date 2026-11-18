@push('styles')
    @include('churn::dataset.dashboard-styles')
@endpush

<x-admin::layouts>
    <x-slot:title>{{ __('churn::dataset.title') }}</x-slot>

    @php($labels = __('churn::dataset.values'))
    <div class="sales-dashboard">
        <header class="sales-heading">
            <div>
                <h1>{{ __('churn::dataset.title') }}</h1>
                <p>{{ __('churn::dataset.description') }}</p>
            </div>
            <div class="sales-actions">
                @if (bouncer()->hasPermission('dashboard'))
                    <a class="sales-link" href="{{ route('admin.dashboard.index', ['view' => 'crm']) }}">{{ __('churn::dataset.crm_dashboard') }}</a>
                @endif
                <a class="sales-button" href="{{ route('admin.churn.dataset.explore', \Illuminate\Support\Arr::except($filters, ['prediction_cutoff'])) }}">{{ __('churn::dataset.explore') }}</a>
            </div>
        </header>

        <form method="GET" action="{{ route($dashboardRoute) }}" class="sales-filter sales-panel" aria-label="{{ __('churn::dataset.sales_filters') }}">
            <div class="sales-filter-fields">@include('churn::dataset.filter-fields')</div>
            <input type="hidden" name="prediction_cutoff" value="{{ $cutoff }}">
            <div class="sales-actions">
                <button class="sales-button" type="submit">{{ __('churn::dataset.apply') }}</button>
                <a class="sales-link" href="{{ route($dashboardRoute, ['prediction_cutoff' => $cutoff]) }}">{{ __('churn::dataset.reset') }}</a>
            </div>
        </form>

        <dl class="sales-kpis sales-panel">
            @foreach (['sales' => 2, 'customers' => 0, 'orders' => 0, 'lines' => 0, 'average_order' => 2] as $metric => $precision)
                <div class="sales-kpi {{ $metric === 'sales' ? 'sales-kpi-primary' : '' }}">
                    <dt title="{{ __('churn::dataset.definitions.'.$metric) }}">{{ __('churn::dataset.metrics.'.$metric) }}</dt>
                    <dd data-testid="dataset-{{ $metric }}">
                        {{ $summary[$metric] === null ? '—' : \Illuminate\Support\Number::format((float) $summary[$metric], $precision, locale: app()->getLocale()) }}
                    </dd>
                    @if ($metric === 'sales' && $summary['lines'] > 0)
                        <dd class="sales-period">{{ __('churn::dataset.period', [
                            'from' => \Illuminate\Support\Facades\Date::parse($summary['first_date'])->format('d/m/Y'),
                            'to' => \Illuminate\Support\Facades\Date::parse($summary['last_date'])->format('d/m/Y'),
                        ]) }}</dd>
                    @endif
                </div>
            @endforeach
        </dl>
        @if ($summary['lines'] > 0)
            <div class="sales-overview">
                @include('churn::dataset.monthly-chart', ['points' => $charts['month']])
                @include('churn::dataset.chart', ['dimension' => 'category', 'points' => $charts['category']])
            </div>
        @else
            <p class="sales-panel sales-empty" role="status">{{ __('churn::dataset.empty') }}</p>
        @endif
        @include('churn::dataset.predictions')
        @if ($summary['lines'] > 0)
            <div class="sales-breakdown">
                @foreach ($charts as $dimension => $points)
                    @if (! in_array($dimension, ['month', 'category'], true))
                        @include('churn::dataset.chart')
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</x-admin::layouts>

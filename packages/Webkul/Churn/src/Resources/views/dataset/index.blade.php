<x-admin::layouts>
    <x-slot:title>{{ __('churn::dataset.title') }}</x-slot>

    @php($labels = __('churn::dataset.values'))
    <div class="flex flex-col gap-4">
        <header class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h1 class="text-xl font-bold">{{ __('churn::dataset.title') }}</h1>
            <p class="mt-2 text-sm">{{ __('churn::dataset.description') }}</p>
            <a class="mt-3 inline-block text-brandColor underline" href="{{ route('admin.churn.index') }}">{{ __('churn::app.title') }}</a>
            <a class="secondary-button mt-3 inline-flex" href="{{ route('admin.churn.dataset.explore', \Illuminate\Support\Arr::except($filters, ['prediction_cutoff'])) }}">{{ __('churn::dataset.explore') }}</a>
            @if (bouncer()->hasPermission('dashboard'))
                <a class="mt-3 inline-block text-brandColor underline" href="{{ route('admin.dashboard.index', ['view' => 'crm']) }}">{{ __('churn::dataset.crm_dashboard') }}</a>
            @endif
        </header>

        <form method="GET" action="{{ route($dashboardRoute) }}" class="flex flex-wrap items-end gap-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            @include('churn::dataset.filter-fields')
            <input type="hidden" name="prediction_cutoff" value="{{ $cutoff }}">
            <button class="primary-button" type="submit">{{ __('churn::dataset.apply') }}</button>
            <a class="secondary-button" href="{{ route($dashboardRoute, ['prediction_cutoff' => $cutoff]) }}">{{ __('churn::dataset.reset') }}</a>
        </form>

        @if ($summary['lines'] > 0)
            <p class="text-sm">{{ __('churn::dataset.period', [
                'from' => \Illuminate\Support\Facades\Date::parse($summary['first_date'])->format('d/m/Y'),
                'to' => \Illuminate\Support\Facades\Date::parse($summary['last_date'])->format('d/m/Y'),
            ]) }}</p>
        @else
            <p role="status">{{ __('churn::dataset.empty') }}</p>
        @endif

        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach (['customers' => 0, 'orders' => 0, 'lines' => 0, 'sales' => 2, 'average_order' => 2] as $metric => $precision)
                <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <dt class="text-sm">{{ __('churn::dataset.metrics.'.$metric) }}</dt>
                    <dd class="my-2 text-2xl font-bold" data-testid="dataset-{{ $metric }}">
                        {{ $summary[$metric] === null ? '—' : \Illuminate\Support\Number::format((float) $summary[$metric], $precision, locale: app()->getLocale()) }}
                    </dd>
                    <dd class="text-sm">{{ __('churn::dataset.definitions.'.$metric) }}</dd>
                </div>
            @endforeach
        </dl>
        @include('churn::dataset.predictions')
        @if ($summary['lines'] > 0)
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($charts as $dimension => $points)
                    @include('churn::dataset.chart')
                @endforeach
            </div>
        @endif
    </div>
</x-admin::layouts>

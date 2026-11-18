<x-admin::layouts>
    <x-slot:title>{{ __('churn::dataset.customer_history') }}</x-slot>

    @php($labels = __('churn::dataset.values'))
    <div class="flex flex-col gap-4">
        <a class="text-brandColor underline" href="{{ route('admin.churn.dataset.explore') }}">{{ __('churn::dataset.explore') }}</a>
        <header class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h1 class="text-xl font-bold">{{ __('churn::dataset.customer_history') }} · {{ $customer->dataset_name }}</h1>
            <p class="mt-2 text-sm">{{ __('churn::dataset.columns.customer_id') }}: {{ $customer->external_customer_id }}</p>
            <p class="mt-2 text-sm">{{ __('churn::dataset.metrics.orders') }}: {{ $totals->orders }} · {{ __('churn::dataset.metrics.lines') }}: {{ $rows->total() }} · {{ __('churn::dataset.metrics.sales') }}: {{ \Illuminate\Support\Number::format((float) $totals->sales, 2, locale: app()->getLocale()) }}</p>
            <a class="secondary-button mt-3 inline-flex" href="{{ route('admin.churn.show', array_filter([
                'customer' => $customer->id, 'as_of' => $prediction?->as_of, 'version' => $prediction?->model_version,
            ])) }}">{{ __('churn::dataset.prediction_detail') }}</a>
            @if (!$prediction)
                <p class="mt-2 text-sm">{{ __('churn::dataset.no_prediction') }}</p>
            @endif
        </header>

        @include('churn::dataset.detail-lines')
    </div>
</x-admin::layouts>

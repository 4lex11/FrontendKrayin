<x-admin::layouts>
    <x-slot:title>{{ __('churn::dataset.order_detail') }}</x-slot>

    @php($labels = __('churn::dataset.values'))
    <div class="flex flex-col gap-4">
        <a class="text-brandColor underline" href="{{ route('admin.churn.dataset.explore') }}">{{ __('churn::dataset.explore') }}</a>
        <header class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h1 class="text-xl font-bold">{{ __('churn::dataset.order_detail') }} · {{ $order->order_id }}</h1>
            <p class="mt-2 text-sm">{{ __('churn::dataset.columns.order_date') }}: {{ \Illuminate\Support\Facades\Date::parse($order->order_date)->format('d/m/Y') }}</p>
            <p class="mt-2 text-sm">{{ __('churn::dataset.metrics.lines') }}: {{ $rows->total() }} · {{ __('churn::dataset.metrics.sales') }}: {{ \Illuminate\Support\Number::format((float) $sales, 2, locale: app()->getLocale()) }}</p>
            <a class="mt-3 inline-block text-brandColor underline" href="{{ route('admin.churn.dataset.customer', ['customer' => $order->external_customer_id]) }}">{{ $order->dataset_name }} ({{ $order->external_customer_id }})</a>
        </header>

        @include('churn::dataset.detail-lines')
    </div>
</x-admin::layouts>

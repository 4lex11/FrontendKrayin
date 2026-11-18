<x-admin::layouts>
    <x-slot:title>{{ __('churn::dataset.explore') }}</x-slot>

    @php($labels = __('churn::dataset.values'))
    <div class="flex flex-col gap-4">
        <header class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h1 class="text-xl font-bold">{{ __('churn::dataset.explore') }}</h1>
            <p class="mt-2 text-sm">{{ __('churn::dataset.description') }}</p>
            <a class="mt-3 inline-block text-brandColor underline" href="{{ route('admin.churn.dataset.index', \Illuminate\Support\Arr::only($filters, ['from', 'to', 'category', 'subcategory', 'segment', 'region'])) }}">{{ __('churn::dataset.title') }}</a>
        </header>

        <form method="GET" action="{{ route('admin.churn.dataset.explore') }}" class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-end gap-4">
                @include('churn::dataset.filter-fields')
                <div>
                    <label for="dataset-q" class="block text-sm">{{ __('churn::dataset.search') }}</label>
                    <input id="dataset-q" name="q" type="search" maxlength="100" value="{{ old('q', $filters['q'] ?? '') }}" class="rounded border p-2 dark:bg-gray-900">
                    <p class="text-sm">{{ __('churn::dataset.search_hint') }}</p>
                </div>
                <div>
                    <label for="dataset-sort" class="block text-sm">{{ __('churn::dataset.sort') }}</label>
                    <select id="dataset-sort" name="sort" class="rounded border p-2 dark:bg-gray-900">
                        @foreach ($available as $column)
                            <option value="{{ $column }}" @selected(($filters['sort'] ?? 'order_date') === $column)>{{ __('churn::dataset.columns.'.$column) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="dataset-direction" class="block text-sm">{{ __('churn::dataset.direction') }}</label>
                    <select id="dataset-direction" name="direction" class="rounded border p-2 dark:bg-gray-900">
                        @foreach (['asc', 'desc'] as $direction)
                            <option value="{{ $direction }}" @selected(($filters['direction'] ?? 'desc') === $direction)>{{ __('churn::dataset.'.$direction) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <details>
                <summary class="cursor-pointer font-medium">{{ __('churn::dataset.choose_columns', ['count' => count($columns)]) }}</summary>
                <fieldset class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <legend class="mb-2 text-sm">{{ __('churn::dataset.columns_hint') }}</legend>
                    @foreach ($available as $column)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="columns[]" value="{{ $column }}" @checked(in_array($column, $columns, true))>
                            {{ __('churn::dataset.columns.'.$column) }}
                        </label>
                    @endforeach
                </fieldset>
            </details>

            @if ($errors->any())
                <ul class="text-sm text-red-600" role="alert">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="flex flex-wrap gap-2">
                <button class="primary-button" type="submit">{{ __('churn::dataset.apply') }}</button>
                <button class="secondary-button" type="submit" name="format" value="csv">{{ __('churn::dataset.download') }}</button>
                <a class="secondary-button" href="{{ route('admin.churn.dataset.explore') }}">{{ __('churn::dataset.reset') }}</a>
            </div>
        </form>

        @if ($rows->isEmpty())
            <p role="status">{{ __('churn::dataset.empty') }}</p>
            @if ($rows->total() > 0)
                <a class="text-brandColor underline" href="{{ $rows->url(1) }}">{{ __('churn::dataset.first_page') }}</a>
            @endif
        @else
            <p class="text-sm">{{ __('churn::dataset.result_count', [
                'from' => \Illuminate\Support\Number::format($rows->firstItem(), locale: app()->getLocale()),
                'to' => \Illuminate\Support\Number::format($rows->lastItem(), locale: app()->getLocale()),
                'total' => \Illuminate\Support\Number::format($rows->total(), locale: app()->getLocale()),
            ]) }}</p>
            @include('churn::dataset.lines-table')
            {{ $rows->links('churn::dataset.pagination') }}
        @endif
    </div>
</x-admin::layouts>

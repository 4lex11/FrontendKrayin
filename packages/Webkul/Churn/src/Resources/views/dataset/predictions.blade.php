<section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    <h2 class="text-lg font-semibold">{{ __('churn::dataset.prediction_title') }}</h2>
    <p class="mt-2 text-sm">{{ __('churn::dataset.prediction_scope') }}</p>
    <form method="GET" action="{{ route('admin.churn.dataset.index') }}" class="my-4 flex flex-wrap items-end gap-3">
        @foreach (\Illuminate\Support\Arr::except($filters, ['prediction_cutoff']) as $filter => $value)
            <input type="hidden" name="{{ $filter }}" value="{{ $value }}">
        @endforeach
        <div>
            <label for="prediction-cutoff" class="block text-sm">{{ __('churn::dataset.prediction_cutoff') }}</label>
            <input id="prediction-cutoff" name="prediction_cutoff" type="date" value="{{ old('prediction_cutoff', $cutoff) }}" min="2015-01-03" max="2018-12-30" required class="rounded border p-2 dark:bg-gray-900">
            @error('prediction_cutoff')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <button class="primary-button" type="submit">{{ __('churn::dataset.apply') }}</button>
        <a class="secondary-button" href="{{ route('admin.churn.index', ['as_of' => $cutoff]) }}">{{ __('churn::dataset.prediction_detail') }}</a>
    </form>
    <p class="mb-3 text-sm">{{ __('churn::app.model') }} {{ $modelVersion }} · {{ __('churn::app.experimental') }}</p>
    <dl class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach (['total', 'eligible', 'ineligible', 'saved', 'missing', 'high_risk', 'medium_risk', 'low_risk'] as $metric)
            <div>
                <dt class="text-sm">{{ __('churn::dataset.prediction_metrics.'.$metric) }}</dt>
                <dd class="text-xl font-bold" data-testid="prediction-{{ $metric }}">{{ \Illuminate\Support\Number::format($predictions[$metric], locale: app()->getLocale()) }}</dd>
            </div>
        @endforeach
    </dl>
    <p class="mt-3 text-sm">{{ __('churn::dataset.prediction_eligibility') }}</p>
    <p class="mt-2 text-sm">{{ __('churn::dataset.prediction_missing') }}</p>
</section>

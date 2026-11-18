<section class="min-w-0 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" aria-labelledby="chart-{{ $dimension }}">
    <h2 id="chart-{{ $dimension }}" class="mb-4 text-lg font-semibold">{{ __('churn::dataset.chart', ['dimension' => __('churn::dataset.dimensions.'.$dimension)]) }}</h2>
    @php($max = max(1, (float) $points->max('sales')))
    <div class="max-h-96 overflow-y-auto" tabindex="0" role="region" aria-labelledby="chart-{{ $dimension }}">
        <dl class="grid gap-3">
            @foreach ($points as $point)
                <div>
                    <dt class="text-sm">
                        @if ($point->label === null)
                            {{ __('churn::dataset.unclassified') }}
                        @elseif ($dimension === 'month')
                            <time datetime="{{ $point->label }}">{{ \Illuminate\Support\Facades\Date::parse($point->label.'-01')->locale(app()->getLocale())->isoFormat('MMM YYYY') }}</time>
                        @else
                            {{ $labels[$point->label] ?? $point->label }}
                        @endif
                    </dt>
                    <dd class="flex items-center gap-3">
                        <meter class="min-w-0 flex-1" min="0" max="{{ $max }}" value="{{ (float) $point->sales }}" aria-hidden="true"></meter>
                        <span class="text-sm tabular-nums">{{ \Illuminate\Support\Number::format((float) $point->sales, 2, locale: app()->getLocale()) }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>

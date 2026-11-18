<section class="sales-panel sales-chart" aria-labelledby="chart-{{ $dimension }}">
    <h2 id="chart-{{ $dimension }}">{{ __('churn::dataset.chart', ['dimension' => __('churn::dataset.dimensions.'.$dimension)]) }}</h2>
    @php($max = max(1, (float) $points->max('sales')))
    <div class="sales-bars-scroll" tabindex="0" role="region" aria-labelledby="chart-{{ $dimension }}">
        <dl class="sales-bars">
            @foreach ($points as $point)
                <div>
                    <dt>{{ $point->label === null ? __('churn::dataset.unclassified') : ($labels[$point->label] ?? $point->label) }}</dt>
                    <dd>{{ \Illuminate\Support\Number::format((float) $point->sales, 2, locale: app()->getLocale()) }}</dd>
                    <dd class="sales-bar-track" aria-hidden="true"><span style="width: {{ (float) $point->sales * 100 / $max }}%"></span></dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>

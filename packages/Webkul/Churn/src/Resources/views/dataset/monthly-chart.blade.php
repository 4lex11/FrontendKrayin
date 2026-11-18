<section class="sales-panel sales-chart" aria-labelledby="sales-monthly-title">
    <h2 id="sales-monthly-title">{{ __('churn::dataset.chart', ['dimension' => __('churn::dataset.dimensions.month')]) }}</h2>
    @php
        $months = $points->keyBy('label');
        $firstMonth = \Carbon\CarbonImmutable::parse($points->first()->label.'-01');
        $lastMonth = $points->last()->label;
        $points = collect();
        for ($month = $firstMonth; $month->format('Y-m') <= $lastMonth; $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $points->push($months->get($key, (object) ['label' => $key, 'sales' => 0]));
        }
        $maximum = max(1, (float) $points->max('sales'));
        $step = 10 ** floor(log10($maximum / 4));
        $tick = ceil($maximum / (4 * $step)) * $step;
        $ceiling = 4 * $tick;
    @endphp
    <div class="sales-monthly-scroll" tabindex="0" role="region" aria-labelledby="sales-monthly-title">
        <div class="sales-monthly-plot" aria-hidden="true">
            <div class="sales-axis">
                @for ($i = 4; $i >= 0; $i--)
                    <span>{{ \Illuminate\Support\Number::format($i * $tick, locale: app()->getLocale()) }}</span>
                @endfor
            </div>
            <div class="sales-months" style="--months: {{ $points->count() }}">
                @foreach ($points as $point)
                    @php
                        $height = (float) $point->sales * 100 / $ceiling;
                        $year = substr($point->label, 0, 4);
                        $caption = \Illuminate\Support\Facades\Date::parse($point->label.'-01')->locale(app()->getLocale())->isoFormat('MMM YYYY');
                        $amount = \Illuminate\Support\Number::format((float) $point->sales, 2, locale: app()->getLocale());
                        $yearStart = $loop->first || $year !== substr($points[$loop->index - 1]->label, 0, 4);
                    @endphp
                    <div class="sales-month">
                        <div class="sales-month-slot" title="{{ $caption }} · {{ $amount }}">
                            <span class="sales-month-bar" style="height: {{ $height }}%"></span>
                        </div>
                        @if ($yearStart)
                            <span class="sales-year">{{ $year }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <details class="sales-chart-data">
        <summary>{{ __('churn::dataset.month_values') }}</summary>
        <dl>
            @foreach ($points as $point)
                <div>
                    <dt><time datetime="{{ $point->label }}">{{ \Illuminate\Support\Facades\Date::parse($point->label.'-01')->locale(app()->getLocale())->isoFormat('MMM YYYY') }}</time></dt>
                    <dd>{{ \Illuminate\Support\Number::format((float) $point->sales, 2, locale: app()->getLocale()) }}</dd>
                </div>
            @endforeach
        </dl>
    </details>
</section>

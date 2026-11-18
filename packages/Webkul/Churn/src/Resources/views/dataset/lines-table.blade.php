<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" role="region" aria-label="{{ __('churn::dataset.explore') }}" tabindex="0">
    <table class="w-full text-left text-sm">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th scope="col" class="whitespace-nowrap p-3">{{ __('churn::dataset.columns.'.$column) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="border-t border-gray-200 dark:border-gray-800">
                    @foreach ($columns as $column)
                        @php($value = $row->$column)
                        <td class="p-3">
                            @if ($value === null)
                                {{ __('churn::dataset.unclassified') }}
                            @elseif ($column === 'customer_id')
                                <a class="text-brandColor underline" href="{{ route('admin.churn.dataset.customer', ['customer' => $value]) }}">{{ $value }}</a>
                            @elseif ($column === 'order_id')
                                <a class="text-brandColor underline" href="{{ route('admin.churn.dataset.order', ['order' => $value]) }}">{{ $value }}</a>
                            @elseif (in_array($column, ['order_date', 'ship_date'], true))
                                {{ \Illuminate\Support\Facades\Date::parse($value)->format('d/m/Y') }}
                            @elseif ($column === 'sales')
                                {{ \Illuminate\Support\Number::format((float) $value, maxPrecision: 6, locale: app()->getLocale()) }}
                            @elseif (in_array($column, ['category', 'subcategory', 'segment', 'region', 'ship_mode', 'country'], true))
                                {{ $labels[$value] ?? $value }}
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

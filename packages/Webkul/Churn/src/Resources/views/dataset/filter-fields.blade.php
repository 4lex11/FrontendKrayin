<div>
    <label class="block text-sm" for="dataset-from">{{ __('churn::dataset.from') }}</label>
    <input id="dataset-from" name="from" type="date" value="{{ old('from', $filters['from'] ?? '') }}" class="rounded border p-2 dark:bg-gray-900">
    @error('from')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
</div>
<div>
    <label class="block text-sm" for="dataset-to">{{ __('churn::dataset.to') }}</label>
    <input id="dataset-to" name="to" type="date" value="{{ old('to', $filters['to'] ?? '') }}" class="rounded border p-2 dark:bg-gray-900">
    @error('to')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
</div>
@foreach ($options as $dimension => $values)
    <div>
        <label class="block text-sm" for="dataset-{{ $dimension }}">{{ ucfirst(__('churn::dataset.dimensions.'.$dimension)) }}</label>
        <select id="dataset-{{ $dimension }}" name="{{ $dimension }}" class="rounded border p-2 dark:bg-gray-900">
            <option value="">{{ __('churn::dataset.all') }}</option>
            @foreach ($values as $value)
                <option value="{{ $value }}" @selected(old($dimension, $filters[$dimension] ?? '') === $value)>{{ $labels[$value] ?? $value }}</option>
            @endforeach
        </select>
        @error($dimension)<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
@endforeach

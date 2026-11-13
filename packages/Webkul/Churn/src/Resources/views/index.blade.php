@push('styles')
<style>
    #churn-panel { min-width: 0; }
    #churn-panel input, #churn-panel select { max-width: 100%; min-width: 0; }
    #churn-panel form > div { min-width: 0; }
    #churn-panel table { min-width: 850px; }
    @media (max-width: 1023px) {
        #churn-panel form > div { flex: 1 1 150px; }
        #churn-panel form input, #churn-panel form select { width: 100%; }
        #churn-panel .churn-stat { min-width: 120px; }
    }
</style>
@endpush
<x-admin::layouts>
    <x-slot:title>{{ __('churn::app.title') }}</x-slot>
    <div id="churn-panel" class="flex flex-col gap-4">
        <header class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h1 class="text-xl font-bold dark:text-white">{{ __('churn::app.title') }}</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ __('churn::app.description') }}</p>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ __('churn::app.model') }}: {{ $modelVersion }} · {{ __('churn::app.cutoff') }}: {{ $filters['as_of'] }}</p>
        </header>
        <aside role="note" class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="font-semibold dark:text-white">{{ __('churn::app.experimental') }}</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ __('churn::app.warning') }} {{ __('churn::app.no_live') }}</p>
        </aside>
<section class="rounded-lg border bg-white p-4 dark:bg-gray-900" data-testid="queue-progress">
<h2 class="font-semibold">{{ __('churn::app.queue_title') }}</h2>
<p class="mt-2 text-sm">{{ __('churn::app.queue_scope') }}</p>
<div class="mt-4 flex flex-wrap gap-4">
@foreach($work['counts'] as $state => $count)<span>{{ __('churn::app.work_states.'.$state) }}: <strong data-work-state="{{ $state }}">{{ $count }}</strong></span>@endforeach
</div>
@php($workTotal = array_sum($work['counts']))
@php($workDone = $work['counts']['completed'] + $work['counts']['error'] + $work['counts']['skipped'])
<p class="mt-2">{{ $workDone }} / {{ $workTotal }} · {{ $workTotal ? number_format($workDone * 100 / $workTotal, 0) : 0 }} %</p>
@if(bouncer()->hasPermission('churn.update'))
<form method="POST" action="{{ route('admin.churn.update') }}" class="mt-4 flex flex-wrap items-center gap-4" data-testid="queue-batch">
@csrf<input type="hidden" name="scope" value="visible"><input type="hidden" name="as_of" value="{{ $filters['as_of'] }}">
<button type="submit" class="primary-button">{{ __('churn::app.queue_all') }}</button>
<p class="text-sm">{{ __('churn::app.queue_note') }}</p>
</form>
@endif
<a class="mt-4 inline-block text-brandColor" href="{{ request()->fullUrl() }}">{{ __('churn::app.refresh_progress') }}</a>
@if(count($work['items']))
<details class="mt-4"><summary>{{ __('churn::app.work_recent') }}</summary>
<div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('churn::app.work_recent') }}"><table><thead><tr><th class="p-2">{{ __('churn::app.customer') }}</th><th class="p-2">{{ __('churn::app.status') }}</th><th class="p-2">{{ __('churn::app.finished') }}</th></tr></thead><tbody>
@foreach($work['items'] as $item)<tr><td class="p-2"><a class="text-brandColor" href="{{ route('admin.churn.show',['customer'=>$item->dataset_customer_id,'as_of'=>$item->as_of,'version'=>$item->model_version]) }}">{{ $item->external_customer_id }}</a></td><td class="p-2">{{ __('churn::app.work_states.'.$item->status) }}@if($item->status === 'error') · {{ __('churn::app.work_error') }}@endif</td><td class="p-2">{{ $item->finished_at ?? '—' }}</td></tr>@endforeach
</tbody></table></div></details>
@endif
</section>

        <form method="GET" action="{{ route('admin.churn.index') }}" class="flex flex-wrap items-end gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-2">
                <label for="churn-search">{{ __('churn::app.search') }}</label>
                <input id="churn-search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="rounded border border-gray-300 p-2 dark:bg-gray-900" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="churn-cutoff">{{ __('churn::app.cutoff') }}</label>
                <input id="churn-cutoff" name="as_of" type="date" value="{{ $filters['as_of'] }}" min="2015-01-03" max="2018-12-30" required class="rounded border border-gray-300 p-2 dark:bg-gray-900" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="churn-risk">{{ __('churn::app.risk') }}</label>
                <select id="churn-risk" name="risk" class="rounded border border-gray-300 p-2 dark:bg-gray-900">
                    <option value="">{{ __('churn::app.all') }}</option>
                    @foreach (['Alto' => 'high', 'Medio' => 'medium', 'Bajo' => 'low'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['risk'] === $value)>{{ __('churn::app.'.$label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-2">
                <label for="churn-status">{{ __('churn::app.status') }}</label>
                <select id="churn-status" name="status" class="rounded border border-gray-300 p-2 dark:bg-gray-900">
                    <option value="">{{ __('churn::app.all') }}</option>
                    @foreach (['saved' => 'saved_filter', 'missing' => 'missing_filter', 'failed' => 'failed_filter', 'running' => 'running_filter'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ __('churn::app.'.$label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-2">
                <label for="churn-sort">{{ __('churn::app.sort') }}</label>
                <select id="churn-sort" name="sort" class="rounded border border-gray-300 p-2 dark:bg-gray-900">
                    @foreach (['risk_desc', 'risk_asc', 'customer'] as $value)
                        <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ __('churn::app.'.$value) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="primary-button" type="submit">{{ __('churn::app.apply') }}</button>
            <a class="secondary-button" href="{{ route('admin.churn.index') }}">{{ __('churn::app.reset') }}</a>
        </form>
        @if ($errors->any())
            <ul role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif
        <div class="flex flex-wrap gap-4" aria-label="{{ __('churn::app.total') }}">
            @foreach (['total' => 'total', 'high_count' => 'high', 'medium_count' => 'medium', 'low_count' => 'low', 'missing_count' => 'missing'] as $key => $label)
                <section class="churn-stat flex-1 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-sm text-gray-600 dark:text-gray-300">{{ __('churn::app.'.$label) }}</h2>
                    <p class="mt-2 text-2xl font-bold dark:text-white" data-testid="summary-{{ $key }}">{{ $summary[$key] }}</p>
                </section>
            @endforeach
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('churn::app.scope_note') }} {{ __('churn::app.advice_note') }}</p>
        <div tabindex="0" role="region" aria-label="{{ __('churn::app.title') }}" class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">{{ __('churn::app.title') }} — {{ $filters['as_of'] }}</caption>
                <thead><tr class="border-b border-gray-200 dark:border-gray-800">
                    @foreach (['customer', 'probability', 'risk', 'cutoff', 'updated', 'status', 'advice_title'] as $column)
                        <th scope="col" class="p-4">{{ __('churn::app.'.$column) }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <td class="p-4"><a class="font-semibold text-blue-600 underline" href="{{ route('admin.churn.show', ['customer' => $row->id, 'as_of' => $filters['as_of'], 'version' => $modelVersion]) }}">{{ $row->name }}</a><br><span class="text-gray-600 dark:text-gray-300">{{ $row->external_customer_id }}</span></td>
                            <td class="p-4">{{ $row->prediction_id ? number_format($row->probability * 100, 2).' %' : '—' }}</td>
                            <td class="p-4">{{ $row->risk ?? __('churn::app.missing') }}@if ($row->limited_history)<br><small>{{ __('churn::app.limited') }}</small>@endif</td>
                            <td class="p-4">{{ $filters['as_of'] }}</td>
                            <td class="p-4">{{ $row->predicted_at ?? '—' }}</td>
                            <td class="p-4">
                                @if ($row->run_status === 'failed')
                                    {{ __('churn::app.'.($row->prediction_id ? 'failed_saved' : 'failed_missing')) }}
                                @elseif ($row->run_status === 'running')
                                    {{ __('churn::app.running') }}
                                @else
                                    {{ __('churn::app.'.($row->prediction_id ? 'saved_status' : 'not_calculated')) }}
                                @endif
                            </td>
                            <td class="p-4" style="min-width:240px;max-width:320px" data-advice="{{ $row->advice }}">{{ __('churn::app.advice.'.$row->advice) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-6">{{ __('churn::app.no_results') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <nav class="flex flex-wrap items-center justify-between gap-4" aria-label="{{ __('churn::app.page') }}">
            <span>{{ __('churn::app.page') }} {{ $rows->currentPage() }} {{ __('churn::app.of') }} {{ $rows->lastPage() }} · {{ $rows->total() }} {{ __('churn::app.total') }}</span>
            <div class="flex gap-4">
                @if ($rows->previousPageUrl())<a class="secondary-button" href="{{ $rows->previousPageUrl() }}">{{ __('churn::app.previous') }}</a>@endif
                @if ($rows->nextPageUrl())<a class="secondary-button" href="{{ $rows->nextPageUrl() }}">{{ __('churn::app.next_page') }}</a>@endif
            </div>
        </nav>
    </div>
</x-admin::layouts>

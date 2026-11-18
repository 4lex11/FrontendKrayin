@push('styles')
<style>
#churn-detail { min-width:0; overflow-wrap:anywhere; }
#churn-detail .detail-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; }
#churn-detail th,#churn-detail td { padding:12px; text-align:left; }
#churn-detail .scroll-table { overflow-x:auto; }
#churn-detail table { min-width:650px; width:100%; }
</style>
@endpush
<x-admin::layouts>
<x-slot:title>{{ __('churn::app.detail') }} · {{ $customer->external_customer_id }}</x-slot>
<div id="churn-detail" class="flex flex-col gap-4">
<a class="text-brandColor" href="{{ route('admin.churn.index', ['as_of'=>$cutoff]) }}">← {{ __('churn::app.back') }}</a>
<header class="rounded-lg border bg-white p-6 dark:bg-gray-900">
<h1 class="text-xl font-bold">{{ $customer->name }} · {{ $customer->external_customer_id }}</h1>
<p class="mt-2">{{ __('churn::app.experimental') }}</p><p class="mt-2 text-sm">{{ __('churn::app.warning') }} {{ __('churn::app.no_live') }}</p>
@if(bouncer()->hasPermission('churn.update') && $modelVersion === config('churn.api.model_version'))
<form method="POST" action="{{ route('admin.churn.update') }}" class="mt-4 flex flex-wrap items-center gap-4" data-testid="queue-form">
@csrf
<input type="hidden" name="as_of" value="{{ $cutoff }}">
<input type="hidden" name="scope" value="customer">
<input type="hidden" name="customer" value="{{ $customer->id }}">
<button type="submit" class="primary-button">{{ __('churn::app.queue_customer') }}</button>
<p class="text-sm">{{ __('churn::app.queue_note') }}</p>
</form>
@endif
</header>
<section class="rounded-lg border bg-white p-6 dark:bg-gray-900">
<h2 class="text-lg font-semibold">{{ __('churn::app.detail') }}</h2>
<dl class="detail-grid mt-4">
<div><dt>{{ __('churn::app.probability') }}</dt><dd data-testid="detail-probability" class="text-xl font-bold">{{ $prediction ? \Illuminate\Support\Number::format($prediction->probability * 100, 2, locale: app()->getLocale()).' %' : '—' }}</dd></div>
<div><dt>{{ __('churn::app.risk') }}</dt><dd>{{ $prediction->risk ?? __('churn::app.missing') }}</dd></div>
<div><dt>{{ __('churn::app.cutoff') }}</dt><dd>{{ \Illuminate\Support\Facades\Date::parse($cutoff)->format('d/m/Y') }}</dd></div>
<div><dt>{{ __('churn::app.model') }}</dt><dd>{{ $modelVersion }}</dd></div>
<div><dt>{{ __('churn::app.horizon') }}</dt><dd>{{ $prediction->horizon_days ?? 180 }} {{ __('churn::app.days') }}</dd></div>
<div><dt>{{ __('churn::app.updated') }}</dt><dd>{{ $prediction?->predicted_at ? \Illuminate\Support\Facades\Date::parse($prediction->predicted_at)->format('d/m/Y H:i') : '—' }}</dd></div>
</dl>
@if($prediction && $prediction->limited_history)<p class="mt-4">{{ __('churn::app.limited') }}</p>@endif
<aside class="mt-4 rounded-lg border p-4" data-testid="detail-suggestion" data-advice="{{ $advice }}">
<h2 class="font-semibold">{{ __('churn::app.advice_title') }}</h2>
<p class="mt-2">{{ __('churn::app.advice.'.$advice) }}</p>
<p class="mt-2 text-sm">{{ __('churn::app.advice_reasons.'.$advice) }}</p>
<p class="mt-2 text-sm">{{ __('churn::app.advice_note') }}</p>
</aside>
</section>
<section class="rounded-lg border bg-white p-6 dark:bg-gray-900">
<h2 class="text-lg font-semibold">{{ __('churn::app.features') }}</h2>
<p class="mt-2">{{ $statistics['window_start'] }} &lt; {{ __('churn::app.order_date') }} ≤ {{ $cutoff }}</p>
<p class="mt-2" data-testid="input-match">{{ $inputMatches === true ? __('churn::app.input_verified') : __('churn::app.input_reconstructed') }}</p>
<p class="mt-2 text-sm">{{ __('churn::app.feature_note') }}</p>
<p class="mt-2">{{ $statistics['eligible'] ? __('churn::app.eligible') : __('churn::app.ineligible') }}</p>
@if($statistics['features'])
<dl class="detail-grid mt-4">
@foreach($statistics['features'] as $name => $value)<div><dt>{{ __('churn::app.features_labels.'.$name) }} <span class="text-sm">({{ $name }})</span></dt><dd data-feature="{{ $name }}" class="font-semibold">{{ str_starts_with($name,'Monetary_') ? \Illuminate\Support\Number::format($value, 4, locale: app()->getLocale()) : $value }}</dd></div>@endforeach
</dl>
@else<p class="mt-4">{{ __('churn::app.no_history') }}</p>@endif
</section>
<section class="rounded-lg border bg-white p-6 dark:bg-gray-900">
<h2 class="text-lg font-semibold">{{ __('churn::app.history') }}</h2>
<div class="scroll-table" tabindex="0" role="region" aria-label="{{ __('churn::app.history') }}"><table><thead><tr>
@foreach(['cutoff','model','probability','risk','updated'] as $label)<th>{{ __('churn::app.'.$label) }}</th>@endforeach
</tr></thead><tbody>
@forelse($history as $item)<tr><td><a class="text-brandColor" href="{{ route('admin.churn.show',['customer'=>$customer->id,'as_of'=>$item->as_of,'version'=>$item->model_version]) }}">{{ \Illuminate\Support\Facades\Date::parse($item->as_of)->format('d/m/Y') }}</a></td><td>{{ $item->model_version }}</td><td>{{ \Illuminate\Support\Number::format($item->probability*100,2, locale: app()->getLocale()) }} %</td><td>{{ $item->risk }}</td><td>{{ \Illuminate\Support\Facades\Date::parse($item->predicted_at)->format('d/m/Y H:i') }}</td></tr>
@empty<tr><td colspan="5">{{ __('churn::app.missing') }}</td></tr>@endforelse
</tbody></table></div>
<nav class="mt-4 flex gap-4" aria-label="{{ __('churn::app.history') }}">
@if($history->previousPageUrl())<a href="{{ $history->previousPageUrl() }}">{{ __('churn::app.previous') }}</a>@endif
<span>{{ $history->currentPage() }} / {{ $history->lastPage() }}</span>
@if($history->nextPageUrl())<a href="{{ $history->nextPageUrl() }}">{{ __('churn::app.next_page') }}</a>@endif
</nav></section>
<section class="rounded-lg border bg-white p-6 dark:bg-gray-900"><h2 class="text-lg font-semibold">{{ __('churn::app.runs') }}</h2>
<p class="mt-2 text-sm">{{ __('churn::app.runs_note') }}</p>
<div class="scroll-table" tabindex="0" role="region" aria-label="{{ __('churn::app.runs') }}"><table><thead><tr><th>{{ __('churn::app.status') }}</th><th>{{ __('churn::app.started') }}</th><th>{{ __('churn::app.finished') }}</th></tr></thead><tbody>
@forelse($runs as $run)<tr><td>{{ __('churn::app.run_states.'.$run->status) }}@if($run->status === 'failed' && $prediction)<br>{{ __('churn::app.failed_saved') }}@endif</td><td>{{ $run->started_at }}</td><td>{{ $run->finished_at ?? '—' }}</td></tr>@empty<tr><td colspan="3">{{ __('churn::app.no_runs') }}</td></tr>@endforelse
</tbody></table></div></section>

<section class="rounded-lg border bg-white p-6 dark:bg-gray-900" id="manual-review">
<h2 class="text-lg font-semibold">{{ __('churn::app.review.title') }}</h2>
<p class="mt-2 text-sm">{{ __('churn::app.review.notice') }}</p>
<p class="mt-2">{{ __('churn::app.cutoff') }}: {{ $cutoff }} · {{ __('churn::app.model') }}: {{ $modelVersion }}</p>
@if($reviews['latest'])
<dl class="detail-grid mt-4" data-testid="review-current">
<div><dt>{{ __('churn::app.status') }}</dt><dd>{{ __('churn::app.review.states.'.$reviews['latest']->status) }}</dd></div>
<div><dt>{{ __('churn::app.review.responsible') }}</dt><dd>{{ $reviews['latest']->responsible_name }}</dd></div>
<div><dt>{{ __('churn::app.review.author') }}</dt><dd>{{ $reviews['latest']->author_name }}</dd></div>
<div><dt>{{ __('churn::app.review.recorded') }}</dt><dd>{{ $reviews['latest']->created_at }}</dd></div>
</dl>
@else<p class="mt-4" data-testid="review-empty">{{ __('churn::app.review.empty') }}</p>@endif
@if(bouncer()->hasPermission('churn.review'))
@if($errors->any())<ul class="mt-4" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<form method="POST" action="{{ route('admin.churn.review',['customer'=>$customer->id]) }}" class="mt-4 flex flex-col gap-4" data-testid="review-form">
@csrf
<input type="hidden" name="as_of" value="{{ $cutoff }}"><input type="hidden" name="version" value="{{ $modelVersion }}">
<input type="hidden" name="expected_id" value="{{ old('expected_id',$reviews['latest']?->id ?? 0) }}">
<div class="detail-grid">
<div><label for="review-status">{{ __('churn::app.status') }}</label><select id="review-status" name="status" required class="mt-2 block w-full rounded border p-2 dark:bg-gray-900">@foreach(['pending','reviewed','discarded'] as $state)<option value="{{ $state }}" @selected(old('status',$reviews['latest']?->status ?? 'pending')===$state)>{{ __('churn::app.review.states.'.$state) }}</option>@endforeach</select></div>
<div><label for="review-responsible">{{ __('churn::app.review.responsible') }}</label><select id="review-responsible" name="responsible_id" required class="mt-2 block w-full rounded border p-2 dark:bg-gray-900"><option value="">{{ __('churn::app.review.choose') }}</option>@foreach($reviews['responsibles'] as $person)<option value="{{ $person->id }}" @selected((string)old('responsible_id',$reviews['latest']?->responsible_id ?? auth()->guard('user')->id())===(string)$person->id)>{{ $person->name }}</option>@endforeach</select></div>
</div>
<div><label for="review-note">{{ __('churn::app.review.note') }}</label><textarea v-pre id="review-note" name="note" maxlength="2000" rows="4" required class="mt-2 block w-full rounded border p-2 dark:bg-gray-900" aria-describedby="review-note-help">{{ old('note') }}</textarea><p id="review-note-help" class="mt-2 text-sm">{{ __('churn::app.review.note_help') }}</p></div>
<div><button type="submit" class="primary-button">{{ __('churn::app.review.save') }}</button></div>
</form>
@endif
<h3 class="mt-4 font-semibold">{{ __('churn::app.review.history') }}</h3>
<div class="mt-4 flex flex-col gap-4" data-testid="review-history">
@forelse($reviews['history'] as $entry)
<article v-pre class="rounded border p-4"><p class="font-semibold">{{ __('churn::app.review.states.'.$entry->status) }} · {{ $entry->created_at }} UTC</p><p class="mt-2 text-sm">{{ __('churn::app.review.responsible') }}: {{ $entry->responsible_name }} · {{ __('churn::app.review.author') }}: {{ $entry->author_name }}</p><p class="mt-2" style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $entry->note }}</p></article>
@empty<p>{{ __('churn::app.review.empty') }}</p>@endforelse
</div>
<nav class="mt-4 flex gap-4" aria-label="{{ __('churn::app.review.history') }}">
@if($reviews['history']->previousPageUrl())<a href="{{ $reviews['history']->previousPageUrl() }}#manual-review">{{ __('churn::app.previous') }}</a>@endif
<span>{{ $reviews['history']->currentPage() }} / {{ $reviews['history']->lastPage() }}</span>
@if($reviews['history']->nextPageUrl())<a href="{{ $reviews['history']->nextPageUrl() }}#manual-review">{{ __('churn::app.next_page') }}</a>@endif
</nav>
</section>
</div></x-admin::layouts>

@if ($rows->isEmpty())
    <a class="text-brandColor underline" href="{{ $rows->url(1) }}">{{ __('churn::dataset.first_page') }}</a>
@else
    @include('churn::dataset.lines-table')
    {{ $rows->links('churn::dataset.pagination') }}
@endif

@if ($paginator->hasPages())
    <nav class="flex flex-wrap gap-2" aria-label="{{ __('churn::dataset.explore') }}">
        @if (!$paginator->onFirstPage())
            <a class="secondary-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('churn::dataset.previous') }}</a>
        @endif
        @if ($paginator->hasMorePages())
            <a class="secondary-button" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('churn::dataset.next') }}</a>
        @endif
    </nav>
@endif

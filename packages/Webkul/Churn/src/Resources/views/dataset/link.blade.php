@once
    @if (bouncer()->hasPermission('churn'))
        <div class="mb-4">
            <a class="secondary-button inline-flex" href="{{ route('admin.churn.dataset.index') }}">
                {{ __('churn::dataset.title') }}
            </a>
        </div>
    @endif
@endonce

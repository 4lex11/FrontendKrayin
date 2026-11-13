<?php

namespace Webkul\Churn\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Churn\Contracts\PanelRepository;
use Webkul\Churn\Contracts\ReviewRepository;
use Webkul\Churn\Contracts\WorkRepository;

class ChurnController extends Controller
{
    public function review(int $customer, Request $request, ReviewRepository $reviews): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('churn') && bouncer()->hasPermission('churn.review'), 403);
        $reviews->record($customer, bouncer()->getAuthorizedUserIds(), $request->only(['as_of', 'version', 'status', 'responsible_id', 'note', 'expected_id']), auth()->guard('user')->id());
        session()->flash('success', __('churn::app.review.saved'));

        return redirect()->route('admin.churn.show', ['customer' => $customer, 'as_of' => $request->input('as_of'), 'version' => $request->input('version')]);
    }

    public function update(Request $request, WorkRepository $work): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('churn') && bouncer()->hasPermission('churn.update'), 403);
        $data = $request->validate([
            'as_of' => 'required|date_format:Y-m-d|after_or_equal:2015-01-03|before_or_equal:2018-12-30',
            'customer' => 'nullable|integer|min:1',
            'scope' => 'required|in:customer,visible',
        ]);
        abort_if($data['scope'] === 'customer' && empty($data['customer']), 422);
        abort_if($data['scope'] === 'visible' && ! empty($data['customer']), 422);
        $result = $work->enqueue(bouncer()->getAuthorizedUserIds(), $data['as_of'], $data['scope'] === 'customer' ? (int) $data['customer'] : null, auth()->guard('user')->id());
        session()->flash('success', __('churn::app.enqueued', $result));

        return redirect()->route('admin.churn.index', ['as_of' => $data['as_of']]);
    }

    public function show(int $customer, PanelRepository $panel, Request $request): View
    {
        abort_unless(bouncer()->hasPermission('churn'), 403);
        $request->merge([
            'as_of' => $request->input('as_of', '2018-06-30'),
            'version' => $request->input('version', config('churn.api.model_version')),
        ]);
        $data = $request->validate([
            'as_of' => 'required|date_format:Y-m-d|after_or_equal:2015-01-03|before_or_equal:2018-12-30',
            'version' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.:-]+$/D'],
            'history_page' => 'nullable|integer|min:1|max:100000',
            'review_page' => 'nullable|integer|min:1|max:100000',
        ]);

        return view('churn::show', $panel->detail($customer, bouncer()->getAuthorizedUserIds(), $data['as_of'], $data['version']) + [
            'cutoff' => $data['as_of'], 'modelVersion' => $data['version'],
            'reviews' => app(ReviewRepository::class)->overview($customer, bouncer()->getAuthorizedUserIds(), $data['as_of'], $data['version']),
        ]);
    }

    public function index(PanelRepository $panel, Request $request): View
    {
        abort_unless(bouncer()->hasPermission('churn'), 403);

        $request->merge(['as_of' => $request->input('as_of', '2018-06-30')]);
        $validated = $request->validate([
            'q' => 'nullable|string|max:100', 'risk' => 'nullable|in:Bajo,Medio,Alto',
            'status' => 'nullable|in:saved,missing,failed,running',
            'sort' => 'nullable|in:risk_desc,risk_asc,customer',
            'as_of' => 'required|date_format:Y-m-d|after_or_equal:2015-01-03|before_or_equal:2018-12-30',
            'page' => 'nullable|integer|min:1|max:100000',
        ]);
        $filters = ['q' => $validated['q'] ?? '', 'risk' => $validated['risk'] ?? '', 'status' => $validated['status'] ?? '',
            'sort' => $validated['sort'] ?? 'risk_desc', 'as_of' => $validated['as_of']];

        return view('churn::index', $panel->listing(bouncer()->getAuthorizedUserIds(), $filters) + [
            'filters' => $filters,
            'work' => app(WorkRepository::class)->progress(bouncer()->getAuthorizedUserIds(), $filters['as_of']),
            'modelVersion' => config('churn.api.model_version'),
            'visiblePredictions' => $panel->visiblePredictionCount(bouncer()->getAuthorizedUserIds()),
        ]);
    }
}

<?php

namespace Webkul\Churn\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webkul\Churn\Contracts\ReviewRepository as ReviewRepositoryContract;
use Webkul\Churn\Models\Review;

class ReviewRepository implements ReviewRepositoryContract
{
    private function customer(int $id, ?array $ownerIds): Builder
    {
        $query = DB::table('churn_dataset_customers as c')->join('persons', 'persons.id', '=', 'c.person_id')->where('c.source', 'superstore-sales')->where('c.id', $id);
        if ($ownerIds !== null) {
            $query->whereIn('persons.user_id', $ownerIds);
        }

        return $query;
    }

    private function responsibleUsers(?array $ownerIds): Builder
    {
        $query = DB::table('users')->where('status', 1);
        if ($ownerIds !== null) {
            $query->whereIn('id', $ownerIds);
        }

        return $query;
    }

    public function overview(int $customerId, ?array $ownerIds, string $cutoff, string $version): array
    {
        abort_unless($this->customer($customerId, $ownerIds)->exists(), 404);
        $query = Review::where('dataset_customer_id', $customerId)->where('as_of', $cutoff)->where('model_version', $version);

        return ['latest' => (clone $query)->orderByDesc('id')->first(),
            'history' => $query->orderByDesc('id')->paginate(10, ['*'], 'review_page')->withQueryString(),
            'responsibles' => $this->responsibleUsers($ownerIds)->orderBy('name')->get(['id', 'name'])];
    }

    public function record(int $customerId, ?array $ownerIds, array $data, int $authorId): void
    {
        $data['note'] = is_string($data['note'] ?? null) ? trim($data['note']) : ($data['note'] ?? null);
        $data = Validator::make($data, [
            'as_of' => 'required|date_format:Y-m-d|after_or_equal:2015-01-03|before_or_equal:2018-12-30',
            'version' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.:-]+$/D'],
            'status' => 'required|in:pending,reviewed,discarded', 'responsible_id' => 'required|integer|min:1',
            'note' => 'required|string|max:2000', 'expected_id' => 'required|integer|min:0',
        ])->validate();
        DB::transaction(function () use ($customerId, $ownerIds, $data, $authorId) {
            abort_unless($this->customer($customerId, $ownerIds)->lockForUpdate()->first(['c.id']), 404);
            $responsible = $this->responsibleUsers($ownerIds)->where('id', $data['responsible_id'])->first(['id', 'name']);
            if (! $responsible) {
                throw ValidationException::withMessages(['responsible_id' => __('churn::app.review.invalid_responsible')]);
            }
            $author = DB::table('users')->where('id', $authorId)->where('status', 1)->first(['id', 'name']);
            abort_unless($author, 403);
            $latest = Review::where('dataset_customer_id', $customerId)->where('as_of', $data['as_of'])->where('model_version', $data['version'])->orderByDesc('id')->first();
            if ((int) ($latest?->id ?? 0) !== (int) $data['expected_id']) {
                throw ValidationException::withMessages(['expected_id' => __('churn::app.review.conflict')]);
            }
            Review::create(['dataset_customer_id' => $customerId, 'as_of' => $data['as_of'], 'model_version' => $data['version'],
                'status' => $data['status'], 'responsible_id' => $responsible->id, 'responsible_name' => $responsible->name,
                'author_id' => $author->id, 'author_name' => $author->name, 'note' => $data['note'],
                'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u')]);
        });
    }
}

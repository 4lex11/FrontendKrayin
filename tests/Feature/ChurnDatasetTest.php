<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Churn\Contracts\DatasetRepository;
use Webkul\Contact\Repositories\PersonRepository;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Every change in these tests is rolled back; never RefreshDatabase on the local CRM.
    // Later Churn modules may reference these fixtures; all deletes remain inside DatabaseTransactions.
    foreach (['churn_reviews', 'churn_work_items', 'churn_prediction_runs', 'churn_predictions'] as $table) {
        if (Schema::hasTable($table)) {
            DB::table($table)->delete();
        }
    }
    DB::table('churn_sales_lines')->delete();
    DB::table('churn_dataset_customers')->delete();
    DB::table('churn_datasets')->where('source', 'superstore-sales')->update(['sha256' => null]);
    $this->row = ['line_id' => 'TEST-1', 'order_id' => 'TEST-O', 'customer_id' => 'CRM01-TEST', 'customer_name' => 'Demo test', 'order_date' => '2018-01-01', 'sales' => '10.125', 'category' => 'Office', 'subcategory' => 'Paper'];
    $this->owner = (int) DB::table('users')->where('status', 1)->value('id');
});

it('dry runs without creating contacts mappings or sales', function () {
    $before = DB::table('persons')->count();
    $result = app(DatasetRepository::class)->import([$this->row], str_repeat('a', 64), '2018-06-30', $this->owner, true);
    expect($result['created_contacts'])->toBe(1)
        ->and(DB::table('persons')->count())->toBe($before)
        ->and(DB::table('churn_dataset_customers')->count())->toBe(0)
        ->and(DB::table('churn_sales_lines')->count())->toBe(0);
});

it('creates demo contacts once and repeats without duplicates', function () {
    $repository = app(DatasetRepository::class);
    $before = DB::table('persons')->count();
    $repository->import([$this->row], str_repeat('a', 64), '2018-06-30', $this->owner, false);
    $result = $repository->import([$this->row], str_repeat('a', 64), '2018-06-30', $this->owner, false);
    expect($result['lines_unchanged'])->toBe(1)->and($result['created_contacts'])->toBe(0)
        ->and(DB::table('persons')->count())->toBe($before + 1)
        ->and(DB::table('churn_sales_lines')->count())->toBe(1);
});

it('reuses legacy contacts by attribute code and preserves their fields', function () {
    $person = app(PersonRepository::class)->create(['entity_type' => 'persons', 'name' => 'Existing demo', 'emails' => [['value' => 'crm01-legacy@example.invalid', 'label' => 'demo']], 'user_id' => $this->owner]);
    $attribute = DB::table('attributes')->where('entity_type', 'persons')->where('code', 'customer_id')->value('id');
    DB::table('attribute_values')->insert(['attribute_id' => $attribute, 'entity_type' => 'persons', 'entity_id' => $person->id, 'text_value' => 'CRM01-TEST']);
    $result = app(DatasetRepository::class)->import([$this->row], str_repeat('a', 64), '2018-06-30', $this->owner, false);
    expect($result['reused_contacts'])->toBe(1)
        ->and(DB::table('churn_dataset_customers')->value('person_id'))->toBe($person->id)
        ->and(DB::table('persons')->where('id', $person->id)->value('name'))->toBe('Existing demo');
});

it('rolls back a conflicting line including any earlier inserts', function () {
    $repository = app(DatasetRepository::class);
    $repository->import([$this->row], str_repeat('a', 64), '2018-06-30', $this->owner, false);
    $new = array_replace($this->row, ['line_id' => 'TEST-2']);
    $bad = array_replace($this->row, ['sales' => '99']);
    expect(fn () => $repository->import([$new, $bad], str_repeat('a', 64), '2018-06-30', $this->owner, false))->toThrow(RuntimeException::class)
        ->and(DB::table('churn_sales_lines')->count())->toBe(1)
        ->and(DB::table('churn_sales_lines')->value('sales'))->toBe('10.125000');
});

it('rejects a different dataset and inactive or missing owner', function () {
    $repository = app(DatasetRepository::class);
    $repository->import([$this->row], str_repeat('a', 64), '2018-06-30', $this->owner, false);
    expect(fn () => $repository->import([$this->row], str_repeat('b', 64), '2018-06-30', $this->owner, false))->toThrow(RuntimeException::class);
    expect(fn () => $repository->import([$this->row], str_repeat('a', 64), '2018-06-30', 0, false))->toThrow(RuntimeException::class);
    DB::table('users')->where('id', $this->owner)->update(['status' => 0]);
    expect(fn () => $repository->import([$this->row], str_repeat('a', 64), '2018-06-30', $this->owner, false))->toThrow(RuntimeException::class);
});

it('rejects ambiguous legacy IDs without linking or importing sales', function () {
    $attribute = DB::table('attributes')->where('entity_type', 'persons')->where('code', 'customer_id')->value('id');
    foreach ([1, 2] as $i) {
        $person = app(PersonRepository::class)->create(['entity_type' => 'persons', 'name' => 'Ambiguous demo', 'emails' => [['value' => 'crm01-ambiguous-'.$i.'@example.invalid', 'label' => 'demo']], 'user_id' => $this->owner]);
        DB::table('attribute_values')->insert(['attribute_id' => $attribute, 'entity_type' => 'persons', 'entity_id' => $person->id, 'text_value' => 'CRM01-TEST']);
    }
    expect(fn () => app(DatasetRepository::class)->import([$this->row], str_repeat('a', 64), '2018-06-30', $this->owner, false))->toThrow(RuntimeException::class)
        ->and(DB::table('churn_dataset_customers')->count())->toBe(0)
        ->and(DB::table('churn_sales_lines')->count())->toBe(0);
});

it('rejects an unrecognized CSV before writing any data', function () {
    $path = tempnam(sys_get_temp_dir(), 'crm01');
    file_put_contents($path, 'different dataset');
    try {
        $this->artisan('churn:import-superstore', ['csv' => $path, '--owner' => $this->owner])->assertFailed();
        expect(DB::table('churn_sales_lines')->count())->toBe(0);
    } finally {
        unlink($path);
    }
});

<?php

use Webkul\Churn\Services\SuperstoreCsv;

function superstoreFixture(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'crm01');
    $handle = fopen($path, 'wb');
    fputcsv($handle, ['Row ID', 'Order ID', 'Order Date', 'Customer ID', 'Customer Name', 'Sales', 'Category', 'Sub-Category'], ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($handle, $row, ',', '"', '');
    }
    fclose($handle);

    return $path;
}

it('preserves decimal sales and reads ambiguous dates as day month year', function () {
    $path = superstoreFixture([['1', 'O-1', '08/11/2017', 'C-1', 'Demo', '12.345600', 'Office', 'Paper']]);
    try {
        $row = (new SuperstoreCsv)->read($path)[0];
        expect($row['order_date'])->toBe('2017-11-08')->and($row['sales'])->toBe('12.345600');
    } finally {
        unlink($path);
    }
});

it('rejects invalid dates and monetary values', function ($date, $sales) {
    $path = superstoreFixture([['1', 'O-1', $date, 'C-1', 'Demo', $sales, 'Office', 'Paper']]);
    try {
        expect(fn () => (new SuperstoreCsv)->read($path))->toThrow(InvalidArgumentException::class);
    } finally {
        unlink($path);
    }
})->with([['31/02/2017', '1'], ['2017-11-08', '1'], ['08/11/2017', '-1'], ['08/11/2017', 'NaN'], ['08/11/2017', '1.1234567']]);

it('rejects duplicate line IDs and inconsistent orders or customers', function ($second) {
    $path = superstoreFixture([['1', 'O-1', '08/11/2017', 'C-1', 'Demo', '1', 'Office', 'Paper'], $second]);
    try {
        expect(fn () => (new SuperstoreCsv)->read($path))->toThrow(InvalidArgumentException::class);
    } finally {
        unlink($path);
    }
})->with([
    [['1', 'O-2', '08/11/2017', 'C-1', 'Demo', '1', 'Office', 'Paper']],
    [['2', 'O-1', '09/11/2017', 'C-1', 'Demo', '1', 'Office', 'Paper']],
    [['2', 'O-1', '08/11/2017', 'C-2', 'Demo', '1', 'Office', 'Paper']],
    [['2', 'O-2', '08/11/2017', 'C-1', 'Other', '1', 'Office', 'Paper']],
]);

it('rejects empty data', function () {
    $path = superstoreFixture([]);
    try {
        expect(fn () => (new SuperstoreCsv)->read($path))->toThrow(InvalidArgumentException::class);
    } finally {
        unlink($path);
    }
});

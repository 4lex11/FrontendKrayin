<?php

namespace Webkul\Churn\Services;

use DateTimeImmutable;

class HistoricalFeatures
{
    /** Descriptive display only; inference continues to calculate features in Python. */
    public function summarize(array $rows, string $cutoff): array
    {
        $date = new DateTimeImmutable($cutoff);
        $start = $date->modify('-365 days')->format('Y-m-d');
        $rows = array_values(array_filter($rows, fn ($row) => $row['order_date'] > $start && $row['order_date'] <= $cutoff));
        if (! $rows) {
            return ['features' => null, 'eligible' => false, 'line_count' => 0, 'window_start' => $start];
        }
        $mean = 0.0;
        $m2 = 0.0;
        $sum = 0.0;
        foreach ($rows as $index => $row) {
            $sales = (float) $row['sales'];
            $sum += $sales;
            $delta = $sales - $mean;
            $mean += $delta / ($index + 1);
            $m2 += $delta * ($sales - $mean);
        }
        $last = max(array_column($rows, 'order_date'));
        $recency = (int) (new DateTimeImmutable($last))->diff($date)->days;

        return [
            'features' => [
                'Recency' => $recency,
                'Frequency' => count(array_unique(array_column($rows, 'order_id'))),
                'Monetary_Sum' => $sum,
                'Monetary_Mean' => $mean,
                'Monetary_Std' => count($rows) > 1 ? sqrt(max(0, $m2 / (count($rows) - 1))) : 0.0,
                'Cat_Count' => count(array_unique(array_column($rows, 'category'))),
                'SubCat_Count' => count(array_unique(array_column($rows, 'subcategory'))),
            ],
            'eligible' => $recency <= 180, 'line_count' => count($rows), 'window_start' => $start,
        ];
    }
}

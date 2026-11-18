<?php

namespace Webkul\Churn\Services;

use DateTimeImmutable;
use InvalidArgumentException;

class SuperstoreCsv
{
    public const SHA256 = 'dbb0f167b27ccf02d7ff8cb0446a9abc6dbc3522e83cf9daa6939801acfdd196';

    private const ORIGINAL_FIELDS = [
        'Ship Date' => 'ship_date',
        'Ship Mode' => 'ship_mode',
        'Segment' => 'segment',
        'Country' => 'country',
        'City' => 'city',
        'State' => 'state',
        'Postal Code' => 'postal_code',
        'Region' => 'region',
        'Product ID' => 'product_id',
        'Product Name' => 'product_name',
    ];

    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('CSV no disponible.');
        }
        $handle = fopen($path, 'rb');
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            $required = ['Row ID', 'Order ID', 'Order Date', 'Customer ID', 'Customer Name', 'Sales', 'Category', 'Sub-Category'];
            if (! is_array($header) || array_diff($required, $header) || count($header) !== count(array_unique($header))) {
                throw new InvalidArgumentException('Cabecera CSV inválida.');
            }
            $extraFields = array_intersect(array_keys(self::ORIGINAL_FIELDS), $header);
            if ($extraFields && count($extraFields) !== count(self::ORIGINAL_FIELDS)) {
                throw new InvalidArgumentException('Faltan columnas originales del dataset.');
            }
            $rows = [];
            $names = [];
            $orders = [];
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count($values) !== count($header)) {
                    throw new InvalidArgumentException('Fila incompleta.');
                }
                $raw = array_combine($header, $values);
                $row = $this->normalize($raw);
                if ($extraFields) {
                    $row['original_fields'] = $this->normalizeOriginalFields($raw);
                }
                if (isset($rows[$row['line_id']])) {
                    throw new InvalidArgumentException('Row ID duplicado.');
                }
                $id = $row['customer_id'];
                if (isset($names[$id]) && $names[$id] !== $row['customer_name']) {
                    throw new InvalidArgumentException('Customer ID con nombres inconsistentes.');
                }
                $order = [$id, $row['order_date']];
                if (isset($orders[$row['order_id']]) && $orders[$row['order_id']] !== $order) {
                    throw new InvalidArgumentException('Pedido con cliente o fecha inconsistentes.');
                }
                $names[$id] = $row['customer_name'];
                $orders[$row['order_id']] = $order;
                $rows[$row['line_id']] = $row;
            }
            if (! $rows) {
                throw new InvalidArgumentException('CSV vacío.');
            }

            return array_values($rows);
        } finally {
            fclose($handle);
        }
    }

    private function normalizeOriginalFields(array $raw): array
    {
        $fields = [];
        foreach (self::ORIGINAL_FIELDS as $header => $column) {
            $value = $raw[$header];
            if ($column === 'ship_date') {
                $date = DateTimeImmutable::createFromFormat('!d/m/Y', $value);
                if (! $date || $date->format('d/m/Y') !== $value) {
                    throw new InvalidArgumentException('Fecha de envío inválida.');
                }
                $fields[$column] = $date->format('Y-m-d');

                continue;
            }
            if ($column === 'postal_code' && $value === '') {
                $fields[$column] = null;

                continue;
            }
            $maxLength = match ($column) {
                'postal_code' => 32,
                'product_name' => 255,
                default => 100,
            };
            if (trim($value) === '' || mb_strlen($value) > $maxLength) {
                throw new InvalidArgumentException('Valor inválido en la columna '.$header.'.');
            }
            $fields[$column] = $value;
        }

        return $fields;
    }

    private function normalize(array $raw): array
    {
        $row = [];
        foreach (['Row ID' => 'line_id', 'Order ID' => 'order_id', 'Customer ID' => 'customer_id'] as $key => $target) {
            if (! preg_match('/^[A-Za-z0-9_.:-]{1,100}$/D', $raw[$key])) {
                throw new InvalidArgumentException('Identificador inválido.');
            }
            $row[$target] = $raw[$key];
        }
        $date = DateTimeImmutable::createFromFormat('!d/m/Y', $raw['Order Date']);
        if (! $date || $date->format('d/m/Y') !== $raw['Order Date']) {
            throw new InvalidArgumentException('Fecha inválida; se requiere dd/mm/yyyy.');
        }
        $row['order_date'] = $date->format('Y-m-d');
        if (! preg_match('/^\d{1,12}(?:\.\d{1,6})?$/D', $raw['Sales'])) {
            throw new InvalidArgumentException('Sales debe ser decimal no negativo con hasta seis decimales.');
        }
        $row['sales'] = $raw['Sales'];
        foreach (['Customer Name' => 'customer_name', 'Category' => 'category', 'Sub-Category' => 'subcategory'] as $key => $target) {
            if (trim($raw[$key]) === '' || mb_strlen($raw[$key]) > 100) {
                throw new InvalidArgumentException('Nombre o categoría inválidos.');
            }
            $row[$target] = $raw[$key];
        }

        return $row;
    }
}

<?php

namespace Webkul\Churn\Services;

use DateTimeImmutable;
use InvalidArgumentException;

class SuperstoreCsv
{
    public const SHA256 = 'dbb0f167b27ccf02d7ff8cb0446a9abc6dbc3522e83cf9daa6939801acfdd196';

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
            $rows = [];
            $names = [];
            $orders = [];
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count($values) !== count($header)) {
                    throw new InvalidArgumentException('Fila incompleta.');
                }
                $raw = array_combine($header, $values);
                $row = $this->normalize($raw);
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

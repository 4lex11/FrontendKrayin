<?php

namespace Webkul\Churn\Repositories;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Webkul\Churn\Contracts\DatasetRepository as DatasetRepositoryContract;
use Webkul\Churn\Models\DatasetCustomer;
use Webkul\Churn\Models\SalesLine;
use Webkul\Contact\Repositories\PersonRepository;

class DatasetRepository implements DatasetRepositoryContract
{
    public function __construct(private PersonRepository $persons) {}

    public function import(array $rows, string $hash, string $cutoff, int $ownerId, bool $dryRun): array
    {
        return DB::transaction(function () use ($rows, $hash, $cutoff, $ownerId, $dryRun) {
            $source = 'superstore-sales';
            $dataset = DB::table('churn_datasets')->where('source', $source)->lockForUpdate()->first();
            if (! $dataset || ($dataset->sha256 !== null && $dataset->sha256 !== $hash)) {
                throw new RuntimeException('Fuente ausente o hash distinto al dataset registrado.');
            }
            if (! DB::table('users')->where('id', $ownerId)->where('status', 1)->exists()) {
                throw new RuntimeException('El responsable debe ser un usuario activo.');
            }
            $attribute = DB::table('attributes')->where('entity_type', 'persons')->where('code', 'customer_id')->first();
            $result = ['dry_run' => $dryRun, 'customers' => 0, 'reused_contacts' => 0, 'created_contacts' => 0, 'existing_mappings' => 0, 'lines_inserted' => 0, 'lines_enriched' => 0, 'lines_unchanged' => 0, 'sha256' => $hash, 'default_cutoff' => $cutoff];
            $mapping = [];
            $existingCustomers = DatasetCustomer::where('source', $source)->get()->keyBy('external_customer_id');
            foreach ($rows as $row) {
                $external = $row['customer_id'];
                if (array_key_exists($external, $mapping)) {
                    continue;
                }
                $result['customers']++;
                $customer = $existingCustomers->get($external);
                if ($customer) {
                    if ($customer->dataset_name !== $row['customer_name']) {
                        throw new RuntimeException('Nombre del dataset incompatible con el mapeo registrado.');
                    }
                    $result['existing_mappings']++;
                    $mapping[$external] = $customer->id;

                    continue;
                }
                $ids = $attribute ? DB::table('attribute_values')->where('attribute_id', $attribute->id)->where('entity_type', 'persons')->where('text_value', $external)->pluck('entity_id')->unique()->values() : collect();
                if ($ids->count() > 1) {
                    throw new RuntimeException('Customer ID ambiguo en los contactos existentes; no se fusionó ningún contacto.');
                }
                $personId = $ids->first();
                if ($personId !== null && ! DB::table('persons')->where('id', $personId)->exists()) {
                    throw new RuntimeException('Atributo externo asociado a un contacto inexistente.');
                }
                if ($personId !== null && DatasetCustomer::where('source', $source)->where('person_id', $personId)->exists()) {
                    throw new RuntimeException('Contacto vinculado a otro Customer ID.');
                }
                $method = $personId !== null ? 'legacy_customer_id' : 'demo_created';
                $result[$personId !== null ? 'reused_contacts' : 'created_contacts']++;
                if (! $dryRun) {
                    if ($personId === null) {
                        $person = $this->persons->create([
                            'entity_type' => 'persons',
                            'name' => $row['customer_name'].' [DEMO Superstore]',
                            'emails' => [['value' => strtolower($external).'@superstore.invalid', 'label' => 'demo']],
                            'user_id' => $ownerId,
                        ]);
                        $personId = $person->id;
                    }
                    $customer = DatasetCustomer::create(['source' => $source, 'external_customer_id' => $external, 'person_id' => $personId, 'dataset_name' => $row['customer_name'], 'link_method' => $method]);
                }
                $mapping[$external] = $customer?->id;
            }
            $existingLines = SalesLine::where('source', $source)->get()->keyBy('line_id');
            $enrichment = [];
            foreach ($rows as $row) {
                $values = ['dataset_customer_id' => $mapping[$row['customer_id']], 'source' => $source, 'line_id' => $row['line_id'], 'order_id' => $row['order_id'], 'order_date' => $row['order_date'], 'sales' => $row['sales'], 'category' => $row['category'], 'subcategory' => $row['subcategory']];
                $line = $existingLines->get($row['line_id']);
                $original = $row['original_fields'] ?? [];
                if ($line) {
                    foreach ($values as $key => $value) {
                        $equal = $key === 'sales' ? bccomp((string) $line->$key, (string) $value, 6) === 0 : (string) $line->$key === (string) $value;
                        if (! $equal) {
                            throw new RuntimeException('Una línea existente difiere del archivo; importación cancelada.');
                        }
                    }
                    $updates = [];
                    foreach ($original as $key => $value) {
                        if ($line->$key === null && $value !== null) {
                            $updates[$key] = $value;
                        } elseif ($line->$key !== $value) {
                            throw new RuntimeException('Un campo original difiere del archivo; importación cancelada.');
                        }
                    }
                    if ($updates === []) {
                        $result['lines_unchanged']++;
                    } else {
                        if (! $dryRun) {
                            ksort($updates);
                            $enrichment[implode(',', array_keys($updates))][] = $values + $updates;
                        }
                        $result['lines_enriched']++;
                    }
                } else {
                    if (! $dryRun) {
                        SalesLine::create($values + $original);
                    }
                    $result['lines_inserted']++;
                }
            }
            foreach ($enrichment as $columns => $lines) {
                foreach (array_chunk($lines, 150) as $batch) {
                    SalesLine::upsert($batch, ['source', 'line_id'], explode(',', $columns));
                }
            }
            if (! $dryRun && $dataset->sha256 === null) {
                DB::table('churn_datasets')->where('source', $source)->update(['sha256' => $hash, 'default_cutoff' => $cutoff, 'imported_at' => now()]);
            }

            return $result;
        });
    }
}

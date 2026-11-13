<?php

namespace Webkul\Churn\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Webkul\Churn\Contracts\DatasetRepository;
use Webkul\Churn\Services\SuperstoreCsv;

class ImportSuperstore extends Command
{
    protected $signature = 'churn:import-superstore {csv} {--owner= : Usuario activo responsable de contactos nuevos} {--dry-run : Validar y planificar sin escribir datos}';

    protected $description = 'Importa el dataset académico verificado y reutiliza contactos por Customer ID.';

    public function handle(SuperstoreCsv $reader, DatasetRepository $repository): int
    {
        try {
            $path = (string) $this->argument('csv');
            if (! is_file($path) || hash_file('sha256', $path) !== SuperstoreCsv::SHA256) {
                throw new RuntimeException('Se requiere el train.csv exacto del estudio (SHA-256 verificado).');
            }
            $owner = filter_var($this->option('owner'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($owner === false) {
                throw new RuntimeException('Indique --owner con el ID de un usuario activo.');
            }
            $rows = $reader->read($path);
            if (count($rows) !== 9800 || count(array_unique(array_column($rows, 'customer_id'))) !== 793 || count(array_unique(array_column($rows, 'order_id'))) !== 4922) {
                throw new RuntimeException('Conteos inesperados para este dataset.');
            }
            $result = $repository->import($rows, SuperstoreCsv::SHA256, '2018-06-30', $owner, (bool) $this->option('dry-run'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Importación cancelada. Revise el archivo, el responsable y el registro local; no se aplicaron cambios parciales.');

            return self::FAILURE;
        }
    }
}

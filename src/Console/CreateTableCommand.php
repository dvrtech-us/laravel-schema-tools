<?php

namespace DVRTech\SchemaTools\Console;

use DVRTech\SchemaTools\Services\DatabaseSchemaGenerator;
use DVRTech\SchemaTools\Services\SchemaAnalyzer;
use DVRTech\SchemaTools\Services\SchemaDiff;
use DVRTech\SchemaTools\Services\SqlServerTableInspector;
use DVRTech\SchemaTools\Support\AnalysisOptions;
use DVRTech\SchemaTools\Support\InputPaths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateTableCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'schema-tools:create-table
        {files : Glob, path, or comma-separated JSON/CSV paths}
        {table : Table name}
        {--schema= : SQL Server schema, for example C208384}
        {--primary-key= : Comma-separated primary key columns}
        {--timestamps : Append created_at and updated_at DATETIME NULL}
        {--connection= : Database connection name}
        {--execute : Run the DDL on the connection}
        {--collation= : Collation applied to text columns}
        {--string-columns= : Comma-separated column names forced to varchar}
        {--decimal-columns= : Comma-separated column names stored as DECIMAL}
        {--varchar-default= : Minimum varchar length used in SQL}';

    /**
     * @var string
     */
    protected $description = 'Build SQL Server CREATE TABLE DDL from JSON/CSV files';

    public function handle(): int
    {
        try {
            $paths = InputPaths::expand([$this->argument('files')]);
            $options = AnalysisOptions::fromCommand($this);
            $table = (string) $this->argument('table');
            $schema = $this->nullableOption('schema');
            $collation = $this->nullableOption('collation');
            $primaryKey = $this->primaryKey();
            $timestamps = (bool) $this->option('timestamps');
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $analyzer = new SchemaAnalyzer();

        try {
            $structure = $analyzer->analyzeFiles($paths, $options);
            $sql = (new DatabaseSchemaGenerator())->generateMsSqlCreateTable(
                $table,
                $structure,
                $schema,
                $primaryKey,
                $timestamps,
                $collation
            );
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $this->line($sql);
        $this->reportSkipped($analyzer->getSkippedRowCount());

        if (!$this->option('execute')) {
            return 0;
        }

        $connectionName = $this->nullableOption('connection') ?: (string) config('database.default');
        $connection = DB::connection($connectionName);
        if ($connection->getDriverName() !== 'sqlsrv') {
            $this->error("Connection [{$connectionName}] uses driver [{$connection->getDriverName()}]. schema-tools:create-table --execute only runs on sqlsrv.");
            return 1;
        }

        $inspector = new SqlServerTableInspector($connection);
        $qualified = $schema !== null ? "[{$schema}].[{$table}]" : "[{$table}]";

        if ($inspector->exists($table, $schema)) {
            $diff = (new SchemaDiff())->compare($structure, $inspector->columns($table, $schema));
            $this->error("Table {$qualified} already exists. Refusing to create it.");
            $this->printDiff($diff);
            return 1;
        }

        $connection->unprepared($sql);

        if (!$inspector->exists($table, $schema)) {
            $this->error("The statement ran but {$qualified} was not found.");
            return 1;
        }

        $this->info("Created {$qualified}.");

        return 0;
    }

    /**
     * @return array<int, string>
     */
    private function primaryKey(): array
    {
        $value = $this->option('primary-key');
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $columns = [];
        foreach (explode(',', $value) as $column) {
            $column = trim($column);
            if ($column !== '') {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    private function nullableOption(string $name): ?string
    {
        $value = $this->option($name);
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * @param array{missing: array<int, array{name: string, expected: string}>, mismatches: array<int, array{name: string, actual: string, expected: string}>, wider: array<int, array{name: string, actual: string, expected: string}>, extra: array<int, string>} $diff
     */
    private function printDiff(array $diff): void
    {
        if ($diff['missing'] === [] && $diff['mismatches'] === [] && $diff['wider'] === [] && $diff['extra'] === []) {
            $this->line('The inferred columns match the table.');
            return;
        }

        if ($diff['missing'] !== []) {
            $this->warn('Missing columns:');
            foreach ($diff['missing'] as $column) {
                $this->line('  - ' . $column['name'] . ' (' . $column['expected'] . ')');
            }
        }

        if ($diff['mismatches'] !== []) {
            $this->warn('Type mismatches:');
            foreach ($diff['mismatches'] as $column) {
                $this->line('  - ' . $column['name'] . ': table has ' . $column['actual'] . ', inferred ' . $column['expected']);
            }
        }

        if ($diff['wider'] !== []) {
            $this->line('Wider columns already on the table:');
            foreach ($diff['wider'] as $column) {
                $this->line('  - ' . $column['name'] . ': table has ' . $column['actual'] . ', inferred ' . $column['expected']);
            }
        }

        if ($diff['extra'] !== []) {
            $this->line('Columns only on the table:');
            foreach ($diff['extra'] as $name) {
                $this->line('  - ' . $name);
            }
        }
    }

    private function reportSkipped(int $skipped): void
    {
        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} row(s) whose column count did not match the header.");
        }
    }
}

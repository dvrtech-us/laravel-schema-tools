<?php

namespace DVRTech\SchemaTools\Console;

use DVRTech\SchemaTools\Services\SchemaAnalyzer;
use DVRTech\SchemaTools\Support\AnalysisOptions;
use DVRTech\SchemaTools\Support\InputPaths;
use Illuminate\Console\Command;

class AnalyzeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'schema-tools:analyze
        {files* : JSON/CSV file paths or globs}
        {--driver= : sqlsrv or mysql. Defaults to the connection driver}
        {--string-columns= : Comma-separated column names forced to varchar}
        {--decimal-columns= : Comma-separated column names stored as DECIMAL}
        {--varchar-default= : Minimum varchar length used in SQL}';

    /**
     * @var string
     */
    protected $description = 'Analyze JSON/CSV files and show the recommended database structure';

    public function handle(): int
    {
        try {
            $paths = InputPaths::expand($this->argument('files'));
            $options = AnalysisOptions::fromCommand($this);
            $driver = $this->resolveDriver();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        if ($driver === null) {
            return 1;
        }

        $analyzer = new SchemaAnalyzer();

        try {
            $structure = $analyzer->analyzeFiles($paths, $options);
        } catch (\Exception $e) {
            $this->error('Error analyzing file: ' . $e->getMessage());
            return 1;
        }

        $label = count($paths) === 1 ? $paths[0] : count($paths) . ' files';
        $this->info("Analysis of {$label}:");
        $this->line('');

        $rows = [];
        foreach ($structure as $columnName => $columnDto) {
            $definition = $driver === 'sqlsrv'
                ? $columnDto->getMsSqlTypeDefinition()
                : $columnDto->getSqlDefinition();
            $length = $columnDto->observedLength ?? $columnDto->length;

            $rows[] = [
                $columnName,
                $columnDto->type,
                $length ?? 'N/A',
                $columnDto->precision ?? 'N/A',
                $definition,
            ];
        }

        $this->table(['Column', 'Type', 'Length', 'Precision', 'SQL Definition'], $rows);
        $this->reportSkipped($analyzer->getSkippedRowCount());

        return 0;
    }

    private function resolveDriver(): ?string
    {
        $driver = $this->option('driver');
        if (!is_string($driver) || $driver === '') {
            $connection = (string) config('database.default');
            $driver = (string) config("database.connections.{$connection}.driver", 'mysql');
        }

        if (!in_array($driver, ['mysql', 'sqlsrv'], true)) {
            $this->error("Unsupported driver [{$driver}]. Pass --driver=mysql or --driver=sqlsrv.");
            return null;
        }

        return $driver;
    }

    private function reportSkipped(int $skipped): void
    {
        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} row(s) whose column count did not match the header.");
        }
    }
}

<?php

namespace DVRTech\SchemaTools\Console;

use DVRTech\SchemaTools\Services\SchemaAnalyzer;
use DVRTech\SchemaTools\Generators\MigrationGenerator;
use DVRTech\SchemaTools\Support\AnalysisOptions;
use Illuminate\Console\Command;

class MigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schema-tools:migration
        {file : The JSON file to analyze}
        {table : The table name}
        {--class= : The migration class name}
        {--string-columns= : Comma-separated column names forced to varchar}
        {--decimal-columns= : Comma-separated column names stored as DECIMAL}
        {--varchar-default= : Minimum varchar length used in SQL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Laravel migration from a JSON file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $tableName = $this->argument('table');
        $className = $this->option('class');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $analyzer = new SchemaAnalyzer();
        $migrationGenerator = new MigrationGenerator();

        try {
            $structure = $analyzer->analyzeJsonFile($filePath, AnalysisOptions::fromCommand($this));
            $migration = $migrationGenerator->generateMigration($tableName, $structure, $className);

            $timestamp = date('Y_m_d_His');
            $migrationFileName = "{$timestamp}_create_{$tableName}_table.php";
            $migrationPath = database_path("migrations/{$migrationFileName}");

            file_put_contents($migrationPath, $migration);

            $this->info("Migration created: {$migrationPath}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Error generating migration: " . $e->getMessage());
            return 1;
        }
    }
}

<?php

namespace DVRTech\SchemaTools\Console;

use DVRTech\SchemaTools\Services\SchemaAnalyzer;
use DVRTech\SchemaTools\Generators\MigrationGenerator;
use DVRTech\SchemaTools\Generators\ModelGenerator;
use Illuminate\Console\Command;

class GenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schema-tools:generate {file : The JSON/CSV file to analyze} {table? : The table name (optional, defaults to filename)} {model? : The model name (optional, defaults to singular table name)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate both migration and model from a JSON/CSV file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $tableName = $this->argument('table');
        $modelName = $this->argument('model');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        // Generate default table name from filename if not provided
        if (!$tableName) {
            $fileName = pathinfo($filePath, PATHINFO_FILENAME);
            $tableName = str_replace(['-', '_'], '_', strtolower($fileName));

            $this->info("Using table name: {$tableName}");
        }

        // Generate default model name from table name if not provided
        if (!$modelName) {
            // Convert snake_case to PascalCase
            $modelName = str_replace('_', '', ucwords($tableName, '_'));
            $this->info("Using model name: {$modelName}");
        }

        $analyzer = new SchemaAnalyzer();
        $migrationGenerator = new MigrationGenerator();
        $modelGenerator = new ModelGenerator();

        try {
            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'json') {
                // Analyze JSON file
                $structure = $analyzer->analyzeJsonFile($filePath);
            } elseif (pathinfo($filePath, PATHINFO_EXTENSION) === 'csv') {
                // Analyze CSV file
                $structure = $analyzer->analyzeCsvFile($filePath);
            } else {
                $this->error("Unsupported file type: {$filePath}");
                return 1;
            }

            // Generate migration
            $migration = $migrationGenerator->generateMigration($tableName, $structure);
            $timestamp = date('Y_m_d_His');
            $migrationFileName = "{$timestamp}_create_{$tableName}_table.php";
            $migrationPath = database_path("migrations/{$migrationFileName}");
            file_put_contents($migrationPath, $migration);

            // Generate SQL file

            // Use raw SQL directory for SQL files
            if (!is_dir(database_path('raw'))) {
                mkdir(database_path('raw'), 0755, true);
            }

            $sqlFileName = "{$timestamp}_create_{$tableName}_table_";
            $sqlPath = database_path("raw/{$sqlFileName}");
            $sql = $migrationGenerator->generateMySql($tableName, $structure);
            file_put_contents($sqlPath . '_mysql.sql', $sql);
            // Generate SQL for other databases if needed
            $sql = $migrationGenerator->generateSqlServer($tableName, $structure);
            file_put_contents($sqlPath . '_sqlserver.sql', $sql);
            $sql = $migrationGenerator->generatePostgreSQL($tableName, $structure);
            file_put_contents($sqlPath . '_postgresql.sql', $sql);

            // Generate model
            $model = $modelGenerator->generateModel($modelName, $structure, $tableName);
            $modelPath = app_path("Models/{$modelName}.php");
            file_put_contents($modelPath, $model);

            $this->info("Migration created: {$migrationPath}");
            $this->info("SQL file created: {$sqlPath}");
            $this->info("Model created: {$modelPath}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Error generating files: " . $e->getMessage());
            return 1;
        }
    }
}

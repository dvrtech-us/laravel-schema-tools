<?php

namespace DVRTech\SchemaTools\Console;

use DVRTech\SchemaTools\Services\SchemaAnalyzer;
use DVRTech\SchemaTools\Generators\ModelGenerator;
use Illuminate\Console\Command;

class ModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schema-tools:model {file : The JSON file to analyze} {model : The model name} {--table= : The table name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Laravel model from a JSON file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $modelName = $this->argument('model');
        $tableName = $this->option('table');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $analyzer = new SchemaAnalyzer();
        $modelGenerator = new ModelGenerator();

        try {
            $structure = $analyzer->analyzeJsonFile($filePath);
            $model = $modelGenerator->generateModel($modelName, $structure, $tableName);

            $modelPath = app_path("Models/{$modelName}.php");

            file_put_contents($modelPath, $model);

            $this->info("Model created: {$modelPath}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Error generating model: " . $e->getMessage());
            return 1;
        }
    }
}

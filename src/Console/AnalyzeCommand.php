<?php

namespace DVRTech\SchemaTools\Console;

use DVRTech\SchemaTools\Services\SchemaAnalyzer;
use Illuminate\Console\Command;

class AnalyzeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schema-tools:analyze {file : The JSON/CSV file to analyze}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze a JSON/CSV file and show the recommended database structure';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $analyzer = new SchemaAnalyzer();

        try {
            // Determine file type and analyze accordingly
            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'json') {
                $structure = $analyzer->analyzeJsonFile($filePath);
            } elseif (pathinfo($filePath, PATHINFO_EXTENSION) === 'csv') {
                $structure = $analyzer->analyzeCsvFile($filePath);
            } else {
                $this->error("Unsupported file type: {$filePath}");
                return 1;
            }

            $this->info("Analysis of {$filePath}:");
            $this->line('');

            $headers = ['Column', 'Type', 'Length', 'Precision', 'SQL Definition'];
            $rows = [];

            foreach ($structure as $columnName => $columnDto) {
                $rows[] = [
                    $columnName,
                    $columnDto->type,
                    $columnDto->length ?? 'N/A',
                    $columnDto->precision ?? 'N/A',
                    $columnDto->getSqlDefinition()
                ];
            }

            $this->table($headers, $rows);

            return 0;
        } catch (\Exception $e) {
            $this->error("Error analyzing file: " . $e->getMessage());
            return 1;
        }
    }
}

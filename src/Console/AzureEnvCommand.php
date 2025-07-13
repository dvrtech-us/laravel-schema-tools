<?php

namespace DVRTech\SchemaTools\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;

class AzureEnvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'azure-env:convert 
                            {direction : Conversion direction (azure-to-env or env-to-azure)}
                            {--azure-file=azure-settings.json : Azure settings JSON file path}
                            {--env-file=.env : Environment file path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert between Azure App Settings JSON and .env files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $direction = $this->argument('direction');
        $azureFile = $this->option('azure-file');
        $envFile = $this->option('env-file');

        try {
            switch ($direction) {
                case 'azure-to-env':
                    $this->convertAzureToEnv($azureFile, $envFile);
                    break;
                case 'env-to-azure':
                    $this->convertEnvToAzure($envFile, $azureFile);
                    break;
                default:
                    $this->error("Invalid direction: {$direction}");
                    $this->info("Valid directions: azure-to-env, env-to-azure");
                    return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Convert Azure App Settings JSON to .env format
     */
    private function convertAzureToEnv(string $azureFile, string $envFile): void
    {
        if (!file_exists($azureFile)) {
            throw new InvalidArgumentException("Azure JSON file not found: {$azureFile}");
        }

        $this->info("Converting Azure settings to .env format...");

        $data = file_get_contents($azureFile);
        $data = json_decode($data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("Invalid JSON in {$azureFile}: " . json_last_error_msg());
        }

        $envFileData = '';
        $envFileData .= "# Generated from Azure App Settings\n";
        $envFileData .= "# Generated on: " . date('Y-m-d H:i:s') . "\n";
        $envFileData .= "# Source file: {$azureFile}\n\n";

        $processedCount = 0;
        foreach ($data as $setting) {
            if (!isset($setting['name']) || !array_key_exists('value', $setting)) {
                continue;
            }

            $name = $setting['name'];
            $value = $setting['value'];

            // Handle null values
            if ($value === null) {
                $value = '';
            }

            // Escape values that contain special characters
            if (is_string($value) && (
                strpos($value, ' ') !== false ||
                strpos($value, '"') !== false ||
                strpos($value, "'") !== false ||
                strpos($value, '#') !== false ||
                strpos($value, '\\') !== false
            )) {
                $value = '"' . str_replace('"', '\\"', $value) . '"';
            }

            $envFileData .= $name . '=' . $value . PHP_EOL;
            $processedCount++;
        }

        file_put_contents($envFile, $envFileData);

        $this->info("✓ Successfully converted Azure settings to .env file");
        $this->line("  Source: {$azureFile}");
        $this->line("  Target: {$envFile}");
        $this->line("  Processed: {$processedCount} settings");
    }

    /**
     * Convert .env file to Azure App Settings JSON format
     */
    private function convertEnvToAzure(string $envFile, string $azureFile): void
    {
        if (!file_exists($envFile)) {
            throw new InvalidArgumentException(".env file not found: {$envFile}");
        }

        $this->info("Converting .env file to Azure settings format...");

        $envContent = file_get_contents($envFile);
        $lines = explode("\n", $envContent);
        $azureSettings = [];

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    $this->warn("Skipping malformed line " . ($lineNumber + 1) . ": {$line}");
                    continue;
                }

                $key = trim($parts[0]);
                $value = trim($parts[1]);

                // Remove quotes from value if present
                if (strlen($value) >= 2) {
                    if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                        (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)
                    ) {
                        $value = substr($value, 1, -1);
                        $value = str_replace('\\"', '"', $value); // Unescape quotes
                    }
                }

                // Convert empty strings to null for Azure
                if ($value === '') {
                    $value = null;
                }

                $azureSettings[] = [
                    'name' => $key,
                    'value' => $value,
                    'slotSetting' => false
                ];
            }
        }

        $jsonData = json_encode($azureSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($azureFile, $jsonData);

        $this->info("✓ Successfully converted .env file to Azure settings");
        $this->line("  Source: {$envFile}");
        $this->line("  Target: {$azureFile}");
        $this->line("  Processed: " . count($azureSettings) . " settings");
    }
}

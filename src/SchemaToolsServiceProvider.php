<?php

namespace DVRTech\SchemaTools;

use DVRTech\SchemaTools\Console\AnalyzeCommand;
use DVRTech\SchemaTools\Console\AzureEnvCommand;
use DVRTech\SchemaTools\Console\MigrationCommand;
use DVRTech\SchemaTools\Console\ModelCommand;
use DVRTech\SchemaTools\Console\GenerateCommand;
use DVRTech\SchemaTools\Services\SchemaAnalyzer;
use DVRTech\SchemaTools\Services\DatabaseSchemaGenerator;
use DVRTech\SchemaTools\Generators\MigrationGenerator;
use DVRTech\SchemaTools\Generators\ModelGenerator;
use Illuminate\Support\ServiceProvider;

class SchemaToolsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(SchemaAnalyzer::class);
        $this->app->singleton(DatabaseSchemaGenerator::class);
        $this->app->singleton(MigrationGenerator::class);
        $this->app->singleton(ModelGenerator::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AnalyzeCommand::class,
                AzureEnvCommand::class,
                MigrationCommand::class,
                ModelCommand::class,
                GenerateCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__ . '/../config/schema-tools.php' => config_path('schema-tools-tools.php'),
        ], 'config');
    }
}

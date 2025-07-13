# JSON Schema Tools for Laravel

A Laravel package for analyzing JSON data structures and generating database schemas, migrations, and Eloquent models.

## Features

- **JSON Schema Analysis**: Automatically analyze JSON data to determine optimal database column types
- **Database Structure Generation**: Generate CREATE TABLE statements for MySQL and SQL Server
- **Laravel Integration**: Generate Laravel migrations and Eloquent models from JSON data
- **Type Compatibility**: Intelligent type promotion and compatibility checking
- **Flexible Output**: Support for various database types and formats

## Installation

Install the package via Composer:

```bash
composer require dvrtech/SchemaTools
```

The package will automatically register its service provider.

## Usage


### Artisan Commands

The package provides several Artisan commands:


# Analyze JSON file and show structure
php artisan schema-tools:analyze data.json

# Generate migration from JSON
php artisan schema-tools:migration data.json users

# Generate model from JSON
php artisan schema-tools:model data.json User

# Generate complete Laravel resources (migration + model)
php artisan schema-tools:generate data.json users User

## Converting Azure Settings JSON <-> .env

# Convert Azure settings JSON to .env file
php artisan azure-env:convert azure-to-env --azure-file=example-azure-settings.json --env-file=example.env

# Convert .env file to Azure settings JSON
php artisan azure-env:convert env-to-azure --env-file=example.env --azure-file=output-azure-settings.json




## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="DVRTech\SchemaTools\SchemaToolsServiceProvider"
```

## Testing

Run the tests:

```bash
composer test
```

## Advanced Use Cases

### Basic JSON Analysis

```php
use DVRTech\SchemaTools\Services\SchemaAnalyzer;

$analyzer = new SchemaAnalyzer();

// Analyze JSON data
$jsonData = json_decode(file_get_contents('data.json'), true);
$structure = $analyzer->analyzeDataStructure($jsonData);

// Get column information
foreach ($structure as $columnName => $columnDto) {
    echo "Column: {$columnName}\n";
    echo "Type: {$columnDto->type}\n";
    echo "SQL Definition: {$columnDto->getSqlDefinition()}\n";
}
```

### Generate Database Schema

```php
use DVRTech\SchemaTools\Services\DatabaseSchemaGenerator;

$generator = new DatabaseSchemaGenerator();

// Generate CREATE TABLE SQL
$createSQL = $generator->generateCreateTableSQL('users', $structure);
echo $createSQL;
```


### Generate Laravel Migration

```php
use DVRTech\SchemaTools\Services\MigrationGenerator;

$migrationGenerator = new MigrationGenerator();
$migration = $migrationGenerator->generateMigration('users', $structure);

// Save to migration file
file_put_contents(
    database_path('migrations/' . date('Y_m_d_His') . '_create_users_table.php'),
    $migration
);
```

### Generate Eloquent Model

```php
use DVRTech\SchemaTools\Services\ModelGenerator;

$modelGenerator = new ModelGenerator();
$model = $modelGenerator->generateModel('User', $structure);

// Save to model file
file_put_contents(app_path('Models/User.php'), $model);
```


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

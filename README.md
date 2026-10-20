# Schema Tools for Laravel

[![Latest Stable Version](https://poser.pugx.org/dvrtech/schema-tools/v/stable)](https://packagist.org/packages/dvrtech/schema-tools)
[![Total Downloads](https://poser.pugx.org/dvrtech/schema-tools/downloads)](https://packagist.org/packages/dvrtech/schema-tools)
[![License](https://poser.pugx.org/dvrtech/schema-tools/license)](https://packagist.org/packages/dvrtech/schema-tools)

A comprehensive Laravel package for analyzing JSON/CSV data structures and automatically generating database schemas, migrations, and Eloquent models. Streamline your development workflow by converting raw data into production-ready Laravel components.

## 🚀 Features

- **Intelligent Data Analysis**: Automatically analyze JSON and CSV data to determine optimal database column types
- **Smart Type Detection**: Infers `date`, `datetime`, `time`, `int`, `bigint`, `float`, `decimal`, `varchar`, `text`, and `json`, including CDK fractional timestamps and leading-zero codes
- **Database Structure Generation**: Generate CREATE TABLE statements for MySQL and SQL Server
- **Laravel Integration**: Generate Laravel migrations and Eloquent models from raw data
- **Type Compatibility**: Intelligent type promotion and compatibility checking
- **Azure Environment Support**: Bidirectional conversion between Azure settings JSON and .env files
- **Flexible Output**: Support for various database types and output formats
- **Production Ready**: Comprehensive test coverage and static analysis

## 📋 Requirements

- PHP 8.1 or higher
- Laravel 9.0, 10.0, 11.0, or 12.0

## 📦 Installation

Install the package via Composer:

```bash
composer require dvrtech/schema-tools
```

The package will automatically register its service provider through Laravel's package auto-discovery.

## 🎯 Quick Start

### Basic Usage

```bash
# Analyze data structure
php artisan schema-tools:analyze data.json

# Generate complete Laravel resources (migration + model)
php artisan schema-tools:generate data.json users User
```

### Artisan Commands

The package provides several Artisan commands for different use cases:

#### Schema Analysis
```bash
# Analyze JSON file and display structure
php artisan schema-tools:analyze data.json

# Analyze CSV file with headers
php artisan schema-tools:analyze customers.csv

# Merge every CSV for one CDK table and print SQL Server types
php artisan schema-tools:analyze "storage/cdk_files/TechPayroll/*.csv" \
  --driver=sqlsrv \
  --string-columns=ronumber,techniciannumber \
  --varchar-default=255
```

`schema-tools:analyze` accepts one or more paths. A path may be a file, a glob, or a comma-separated list. `--driver` is `mysql` or `sqlsrv`. When omitted, the command uses the default connection's driver. The Length column is the longest observed value. The SQL definition uses at least `--varchar-default` (255 unless you change it).

#### SQL Server CREATE TABLE
```bash
# Print guarded DDL. This does not connect.
php artisan schema-tools:create-table "storage/cdk_files/TechPayroll/*.csv" TechPayroll \
  --schema=C208384 \
  --primary-key=hostitemid,cora_acct_id \
  --timestamps \
  --collation=SQL_Latin1_General_CP1_CI_AS

# Create the table when it is absent
php artisan schema-tools:create-table "storage/cdk_files/TechPayroll/*.csv" TechPayroll \
  --schema=C208384 \
  --primary-key=hostitemid,cora_acct_id \
  --timestamps \
  --connection=sqlsrv \
  --execute
```

`--execute` runs only on a `sqlsrv` connection. If the table already exists, the command prints missing columns and type mismatches and does not change the database.

#### Migration Generation
```bash
# Generate a migration from JSON
php artisan schema-tools:migration data.json users

# Generate a migration and model from CSV
php artisan schema-tools:generate customers.csv customers
```

#### Model Generation
```bash
# Generate Eloquent model from JSON
php artisan schema-tools:model data.json User

# Generate model with custom table name
php artisan schema-tools:model data.json User --table=custom_users
```

#### Complete Resource Generation
```bash
# Generate both migration and model
php artisan schema-tools:generate data.json users User

# Generate with custom namespace
php artisan schema-tools:generate data.json users User --namespace=App\\Models\\Custom
```

### Azure Environment Conversion

Convert between Azure settings JSON and Laravel .env files:

```bash
# Convert Azure settings JSON to .env file
php artisan azure-env:convert azure-to-env --azure-file=azure-settings.json --env-file=.env

# Convert .env file to Azure settings JSON
php artisan azure-env:convert env-to-azure --env-file=.env --azure-file=azure-settings.json
```




## ⚙️ Configuration

Publish the configuration file to customize default settings:

```bash
php artisan vendor:publish --provider="DVRTech\SchemaTools\SchemaToolsServiceProvider"
```

Publishing writes `config/schema-tools-tools.php`. That path is the one the package reads first. The same keys are also merged as `config/schema-tools.*`, and a published value wins.

```php
return [
    'export_paths' => [
        'migrations' => 'database/migrations',
        'models' => 'app/Models',
    ],

    // Always varchar, even when the values look numeric.
    'string_columns' => [],

    // Always DECIMAL, sized from the data.
    'decimal_columns' => [],

    // Minimum varchar length emitted in SQL.
    'varchar_default' => 255,
];
```

Command options override the config for that run: `--string-columns`, `--decimal-columns`, and `--varchar-default`. The analyzer does not read config itself. Pass the same keys in the `$options` array when you call it from PHP.

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run tests with coverage
./vendor/bin/phpunit --coverage-html coverage-report

# Run static analysis
./vendor/bin/phpstan
```

## 🔧 Advanced Usage

### Programmatic API

#### Basic Schema Analysis

```php
use DVRTech\SchemaTools\Services\SchemaAnalyzer;

$analyzer = new SchemaAnalyzer();

// Analyze JSON data
$jsonData = json_decode(file_get_contents('data.json'), true);
$structure = $analyzer->analyzeDataStructure($jsonData);

// Inspect column information
foreach ($structure as $columnName => $columnDto) {
    echo "Column: {$columnName}\n";
    echo "Type: {$columnDto->type}\n";
    echo "SQL Definition: {$columnDto->getSqlDefinition()}\n";
    echo "Laravel Definition: {$columnDto->getLaravelMigrationDefinition()}\n\n";
}
```

#### Generate Raw SQL Schema

```php
use DVRTech\SchemaTools\Services\DatabaseSchemaGenerator;

$generator = new DatabaseSchemaGenerator();

// MySQL definitions use backticks. Approximate numbers are DOUBLE.
$createSQL = $generator->generateCreateTableSQL('users', $structure, 'mysql');

// SQL Server definitions. Pass sqlsrv to the simple generator, or use
// generateMsSqlCreateTable() for schema, primary key, timestamps, and collation.
$createSQL = $generator->generateMsSqlCreateTable(
    'TechPayroll',
    $structure,
    'C208384',
    ['hostitemid', 'cora_acct_id'],
    true,
    'SQL_Latin1_General_CP1_CI_AS'
);
```

#### Generate Laravel Migration

```php
use DVRTech\SchemaTools\Generators\MigrationGenerator;

$migrationGenerator = new MigrationGenerator();
$migration = $migrationGenerator->generateMigration('users', $structure);

// Save to migration file
$filename = date('Y_m_d_His') . '_create_users_table.php';
file_put_contents(
    database_path('migrations/' . $filename),
    $migration
);
```

#### Generate Eloquent Model

```php
use DVRTech\SchemaTools\Generators\ModelGenerator;

$modelGenerator = new ModelGenerator();
$model = $modelGenerator->generateModel('User', 'users', $structure);

// Save to model file
file_put_contents(app_path('Models/User.php'), $model);
```

### Type Detection Logic

Each non-empty value is classified, then the column is promoted. Behavior baselines and the promotion table are in [documentation/features/schema-tools/schema-analysis/baseline.md](documentation/features/schema-tools/schema-analysis/baseline.md).

| Observed values | Inferred type | MySQL | SQL Server |
| --- | --- | --- | --- |
| `2026-10-19` | `date` | `DATE` | `DATE` |
| `2026-10-20 09:34:14.51185` | `datetime` | `DATETIME(5)` | `DATETIME2` |
| `17:26:31`, `09:01:02` | `time` | `TIME` | `TIME` |
| Date mixed with datetime | `datetime` | `DATETIME(n)` | `DATETIME2` |
| Any other date/time mix | `varchar` | `VARCHAR(n)` | `NVARCHAR(n)` |
| Whole numbers inside ±2,147,483,647 | `int` | `INT` | `INT` |
| Whole numbers inside signed bigint | `bigint` | `BIGINT` | `BIGINT` |
| Longer digit strings, or a leading zero (`007`, `0123.45`) | `varchar` | `VARCHAR(n)` | `NVARCHAR(n)` |
| Fractions such as `3.14159` | `float` | `DOUBLE` | `FLOAT` |
| `amount`, `price`, `total`, `cost`, `gst`, `pst`, `discount`, `decimal`, or `tax` without `rate` | `decimal` | `DECIMAL(p,s)` | `DECIMAL(p,s)` |
| Bigint mixed with a fraction, or more than 15 integer digits | `decimal` | `DECIMAL(p,s)` | `DECIMAL(p,s)` |

`1e5` is numeric only when every value in the column uses scientific notation. `--string-columns` forces varchar. `--decimal-columns` forces `DECIMAL` sized from the largest integer-digit count and the largest scale. `p` above 38 falls back to varchar.

Varchar SQL length is `max(observed length, varchar default)`. Strings longer than `min(max(255, varchar default), 4000)` become `text` / `NVARCHAR(MAX)`. Laravel migrations use `double()`, `bigInteger()`, `dateTime()`, and `time()`. The Eloquent cast for `float` is `float`. `time` has no cast.

## 📊 Data Format Support

### JSON Files

Supports both single objects and arrays:

```json
// Single object
{
    "name": "John Doe",
    "email": "john@example.com",
    "age": 30
}

// Array of objects
[
    {"name": "John", "email": "john@example.com", "age": 30},
    {"name": "Jane", "email": "jane@example.com", "age": 25}
]
```

### CSV Files

Automatically detects headers and analyzes data types:

```csv
name,email,age,registration_date
John Doe,john@example.com,30,2024-01-15
Jane Smith,jane@example.com,25,2024-01-16
```

## 🔄 Azure Integration

### Environment File Conversion

Convert between Azure App Service configuration and Laravel .env files:

```bash
# Azure settings JSON format
{
    "DATABASE_URL": "mysql://user:pass@host:3306/db",
    "APP_ENV": "production",
    "APP_DEBUG": "false"
}

# Converts to .env format
DATABASE_URL=mysql://user:pass@host:3306/db
APP_ENV=production
APP_DEBUG=false
```

## 🏗️ Architecture Overview

The package follows a clean architecture pattern:

- **Services Layer**: `SchemaAnalyzer`, `ColumnStats`, `DatabaseSchemaGenerator`, `SchemaDiff`, `SqlServerTableInspector`
- **Support**: `AnalysisOptions`, `InputPaths`
- **Generators Layer**: `MigrationGenerator`, `ModelGenerator`
- **DTO**: `ColumnStructureDTO`
- **Console Commands**: `schema-tools:analyze`, `schema-tools:create-table`, `schema-tools:migration`, `schema-tools:model`, `schema-tools:generate`
- **Template System**: File-based code generation templates

Feature behavior is documented under [documentation/](documentation/README.md).

## 🤝 Contributing

We welcome contributions! Please follow these guidelines:

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/amazing-feature`
3. **Follow coding standards**: Use PSR-12 coding standards
4. **Add tests**: Ensure your changes are covered by tests
5. **Run quality checks**:
   ```bash
   ./vendor/bin/phpunit
   ./vendor/bin/phpstan
   ```
6. **Commit your changes**: Use conventional commit messages
7. **Push to the branch**: `git push origin feature/amazing-feature`
8. **Open a Pull Request**

### Development Setup

```bash
# Clone the repository
git clone https://github.com/dvrtech-us/laravel-schema-tools.git
cd laravel-schema-tools

# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit

# Run static analysis
./vendor/bin/phpstan
```

## 📝 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🛡️ Security

If you discover any security-related issues, please email dev-info@dvrtech.us instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## 💼 About DVRTech

This package is developed and maintained by [DVRTech LLC](https://dvrtech.us).

## 🙏 Credits

- **DVRTech LLC** - Initial development and maintenance
- **Laravel Community** - Inspiration and framework
- **All Contributors** - Thank you for your contributions!

---

<p align="center">
  <strong>Made with ❤️ by DVRTech</strong>
</p>

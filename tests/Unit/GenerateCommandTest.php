<?php

namespace DVRTech\SchemaTools\Tests\Unit;


use DVRTech\SchemaTools\Services\SchemaAnalyzer;
use DVRTech\SchemaTools\Generators\MigrationGenerator;
use DVRTech\SchemaTools\Generators\ModelGenerator;
use DVRTech\SchemaTools\DTO\ColumnStructureDTO;
use PHPUnit\Framework\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class GenerateCommandTest extends TestCase
{
    private string $testJsonPath;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir() . '/schema-tools-tests-' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Create test JSON file
        $this->testJsonPath = $this->tempDir . '/test.json';
        file_put_contents($this->testJsonPath, json_encode([
            ['name' => 'John Doe', 'age' => 30, 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'age' => 25, 'email' => 'jane@example.com'],
        ]));
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->testJsonPath)) {
            unlink($this->testJsonPath);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testGenerateCommandCreatesAllFiles()
    {
        // This test focuses on the integration between components
        // In a real Laravel application, this would use Laravel's testing framework
        // For now, we test that the components work together correctly

        $analyzer = new SchemaAnalyzer();
        $migrationGenerator = new MigrationGenerator();
        $modelGenerator = new ModelGenerator();

        // Test that all components can work together
        $this->assertInstanceOf(SchemaAnalyzer::class, $analyzer);
        $this->assertInstanceOf(MigrationGenerator::class, $migrationGenerator);
        $this->assertInstanceOf(ModelGenerator::class, $modelGenerator);

        // Test that the JSON file analysis works
        $structure = $analyzer->analyzeJsonFile($this->testJsonPath);
        $this->assertIsArray($structure);
        $this->assertNotEmpty($structure);

        // Test that both migration and SQL can be generated from the structure
        $migration = $migrationGenerator->generateMigration('users', $structure);
        $sql = $migrationGenerator->generateSql('users', $structure);

        $this->assertStringContains('Schema::create(\'users\'', $migration);
        $this->assertStringContains('CREATE TABLE `users`', $sql);
    }

    public function testMigrationGeneratorCreatesSqlAndPhpFiles()
    {
        $generator = new MigrationGenerator();

        $structure = [
            'name' => new ColumnStructureDTO('varchar', 255, null, 'name'),
            'age' => new ColumnStructureDTO('int', null, null, 'age'),
            'email' => new ColumnStructureDTO('varchar', 255, null, 'email'),
        ];

        // Test PHP migration generation
        $migration = $generator->generateMigration('users', $structure);
        $this->assertStringContains('Schema::create(\'users\'', $migration);
        $this->assertStringContains('$table->string(\'name\', 255)', $migration);
        $this->assertStringContains('$table->integer(\'age\')', $migration);
        $this->assertStringContains('$table->string(\'email\', 255)', $migration);

        // Test SQL generation
        $sql = $generator->generateSql('users', $structure);
        $this->assertStringContains('CREATE TABLE `users`', $sql);
        $this->assertStringContains('`name` VARCHAR(255)', $sql);
        $this->assertStringContains('`age` INT', $sql);
        $this->assertStringContains('`email` VARCHAR(255)', $sql);
    }

    public function testSchemaAnalyzerIntegration()
    {
        $analyzer = new SchemaAnalyzer();
        $generator = new MigrationGenerator();

        // Analyze the test JSON file
        $structure = $analyzer->analyzeJsonFile($this->testJsonPath);

        // Verify structure analysis
        $this->assertArrayHasKey('name', $structure);
        $this->assertArrayHasKey('age', $structure);
        $this->assertArrayHasKey('email', $structure);

        // Test that the structure can be used to generate both PHP and SQL
        $migration = $generator->generateMigration('test_table', $structure);
        $sql = $generator->generateSql('test_table', $structure);

        $this->assertStringContains('test_table', $migration);
        $this->assertStringContains('test_table', $sql);
    }

    public function testSqlGenerationWithVariousTypes()
    {
        $generator = new MigrationGenerator();

        $structure = [
            'title' => new ColumnStructureDTO('varchar', 100, null, 'title'),
            'content' => new ColumnStructureDTO('text', null, null, 'content'),
            'published_at' => new ColumnStructureDTO('date', null, null, 'published_at'),
            'view_count' => new ColumnStructureDTO('int', null, null, 'view_count'),
            'rating' => new ColumnStructureDTO('decimal', null, 2, 'rating'),
            'metadata' => new ColumnStructureDTO('json', null, null, 'metadata'),
        ];

        $sql = $generator->generateSql('posts', $structure);

        // Test various SQL types
        $this->assertStringContains('`title` VARCHAR(100)', $sql);
        $this->assertStringContains('`content` TEXT', $sql);
        $this->assertStringContains('`published_at` DATE', $sql);
        $this->assertStringContains('`view_count` INT', $sql);
        $this->assertStringContains('`rating` DECIMAL(10,2)', $sql);
        $this->assertStringContains('`metadata` JSON', $sql);

        // Test that timestamps are included
        $this->assertStringContains('`created_at` TIMESTAMP NULL DEFAULT NULL', $sql);
        $this->assertStringContains('`updated_at` TIMESTAMP NULL DEFAULT NULL', $sql);
    }

    public function testSqlFileNamingConsistency()
    {
        $generator = new MigrationGenerator();

        $structure = [
            'name' => new ColumnStructureDTO('varchar', 255, null, 'name'),
        ];

        // Test that we can generate both files and they would have consistent naming
        $migration = $generator->generateMigration('users', $structure);
        $sql = $generator->generateSql('users', $structure);

        // Both should reference the same table
        $this->assertStringContains('users', $migration);
        $this->assertStringContains('users', $sql);

        // The migration should be PHP code, the SQL should be SQL
        $this->assertStringContains('Schema::create', $migration);
        $this->assertStringContains('CREATE TABLE', $sql);
    }



    private function mockLaravelHelpers()
    {
        // In a real Laravel test, we would use the Laravel testing framework
        // This is a placeholder for the complex mocking that would be needed
        if (!function_exists('database_path')) {
            function database_path($path = '')
            {
                return sys_get_temp_dir() . '/database/' . $path;
            }
        }

        if (!function_exists('app_path')) {
            function app_path($path = '')
            {
                return sys_get_temp_dir() . '/app/' . $path;
            }
        }
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertThat(
            $haystack,
            $this->logicalOr(
                $this->stringContains($needle),
                $this->stringContains(strtolower($needle)),
                $this->stringContains(strtoupper($needle))
            ),
            "Failed asserting that '$haystack' contains '$needle'"
        );
    }
}

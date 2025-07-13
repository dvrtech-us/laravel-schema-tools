<?php

namespace DVRTech\SchemaTools\Tests\Unit;

use DVRTech\SchemaTools\Services\SchemaAnalyzer;
use DVRTech\SchemaTools\Generators\MigrationGenerator;
use PHPUnit\Framework\TestCase;

class SqlGenerationIntegrationTest extends TestCase
{
    private SchemaAnalyzer $analyzer;
    private MigrationGenerator $generator;

    protected function setUp(): void
    {
        $this->analyzer = new SchemaAnalyzer();
        $this->generator = new MigrationGenerator();
    }

    public function testSqlGenerationWithRealTestData()
    {
        $testDataPath = __DIR__ . '/../data/test-json-1.json';

        if (!file_exists($testDataPath)) {
            $this->markTestSkipped('Test data file not found: ' . $testDataPath);
        }

        // Analyze the real test data
        $structure = $this->analyzer->analyzeJsonFile($testDataPath);

        // Generate both PHP migration and SQL
        $migration = $this->generator->generateMigration('test_table', $structure);
        $sql = $this->generator->generateSql('test_table', $structure);

        // Test that both contain the expected table structure
        $this->assertStringContains('test_table', $migration);
        $this->assertStringContains('test_table', $sql);

        // Test that the SQL is properly formatted
        $this->assertStringContains('CREATE TABLE `test_table`', $sql);

        // Test that timestamps are included
        $this->assertStringContains('`created_at` TIMESTAMP NULL DEFAULT NULL', $sql);
        $this->assertStringContains('`updated_at` TIMESTAMP NULL DEFAULT NULL', $sql);

        // Test that the SQL ends properly
        $this->assertStringEndsWith(');', $sql);

        // Test that the SQL is valid syntax (basic check)
        $this->assertStringNotContainsString(';;', $sql); // No double semicolons
        $this->assertStringNotContainsString(',,', $sql); // No double commas
    }

    public function testSqlGenerationWithNestedJsonData()
    {
        // Create test data with nested structure
        $testData = [
            [
                'user_id' => 1,
                'profile' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com'
                ],
                'settings' => [
                    'theme' => 'dark',
                    'notifications' => true
                ]
            ],
            [
                'user_id' => 2,
                'profile' => [
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com'
                ],
                'settings' => [
                    'theme' => 'light',
                    'notifications' => false
                ]
            ]
        ];

        // Analyze the nested data
        $structure = $this->analyzer->analyzeDataStructure($testData);

        // Generate SQL
        $sql = $this->generator->generateSql('user_profiles', $structure);

        // Test that nested JSON data is handled correctly
        $this->assertStringContains('CREATE TABLE `user_profiles`', $sql);
        $this->assertStringContains('`user_id` INT', $sql);
        $this->assertStringContains('`profile` JSON', $sql);
        $this->assertStringContains('`settings` JSON', $sql);
    }

    public function testSqlGenerationWithMixedDataTypes()
    {
        $testData = [
            [
                'string_field' => 'Hello World',
                'integer_field' => 42,
                'float_field' => 3.14159,
                'boolean_field' => true,
                'date_field' => '2023-01-01',
                'large_text' => str_repeat('Lorem ipsum ', 100),
                'json_field' => ['key' => 'value'],
            ],
            [
                'string_field' => 'Another String',
                'integer_field' => 100,
                'float_field' => 2.71828,
                'boolean_field' => false,
                'date_field' => '2023-12-31',
                'large_text' => str_repeat('Dolor sit amet ', 200),
                'json_field' => ['another' => 'object'],
            ]
        ];

        $structure = $this->analyzer->analyzeDataStructure($testData);
        $sql = $this->generator->generateSql('mixed_data', $structure);

        // Test that different data types are handled correctly
        $this->assertStringContains('`string_field` VARCHAR', $sql);
        $this->assertStringContains('`integer_field` INT', $sql);
        $this->assertStringContains('`float_field` FLOAT', $sql);
        $this->assertStringContains('`large_text` TEXT', $sql);
        $this->assertStringContains('`json_field` JSON', $sql);

        // Test proper SQL formatting
        $lines = explode("\n", $sql);
        $this->assertGreaterThan(5, count($lines));

        // Test that each column definition is properly formatted
        foreach ($lines as $line) {
            if (strpos($line, '`') !== false && strpos($line, 'CREATE TABLE') === false) {
                $this->assertStringStartsWith('    ', $line, "Column definition should be properly indented: $line");
                if (strpos($line, ',') !== false) {
                    $this->assertStringEndsWith(',', trim($line), "Column definition should end with comma: $line");
                }
            }
        }
    }

    public function testSqlGenerationConsistencyAcrossMultipleRuns()
    {
        $testData = [
            ['name' => 'Test', 'value' => 123],
            ['name' => 'Another', 'value' => 456],
        ];

        $structure = $this->analyzer->analyzeDataStructure($testData);

        // Generate SQL multiple times
        $sql1 = $this->generator->generateSql('consistency_test', $structure);
        $sql2 = $this->generator->generateSql('consistency_test', $structure);
        $sql3 = $this->generator->generateSql('consistency_test', $structure);

        // All should be identical
        $this->assertEquals($sql1, $sql2);
        $this->assertEquals($sql2, $sql3);
    }

    public function testSqlGenerationWithEmptyData()
    {
        $structure = [];
        $sql = $this->generator->generateSql('empty_table', $structure);

        // Should still create a valid table with timestamps
        $this->assertStringContains('CREATE TABLE `empty_table`', $sql);
        $this->assertStringContains('`created_at` TIMESTAMP NULL DEFAULT NULL', $sql);
        $this->assertStringContains('`updated_at` TIMESTAMP NULL DEFAULT NULL', $sql);
        $this->assertStringEndsWith(');', $sql);
    }

    public function testSqlGenerationWithSpecialCharactersInTableName()
    {
        $structure = [
            'name' => new \DVRTech\SchemaTools\DTO\ColumnStructureDTO('varchar', 255, null, 'name'),
        ];

        $sql = $this->generator->generateSql('table_with_underscores', $structure);

        $this->assertStringContains('CREATE TABLE `table_with_underscores`', $sql);
        $this->assertStringContains('`name` VARCHAR(255)', $sql);
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

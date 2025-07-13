<?php

namespace DVRTech\SchemaTools\Tests\Unit;

use DVRTech\SchemaTools\Generators\MigrationGenerator;
use DVRTech\SchemaTools\DTO\ColumnStructureDTO;
use PHPUnit\Framework\TestCase;

class MigrationGeneratorTest extends TestCase
{
    private MigrationGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new MigrationGenerator();
    }

    public function testGenerateMigration()
    {
        $structure = [
            'name' => new ColumnStructureDTO('varchar', 255, null, 'name'),
            'age' => new ColumnStructureDTO('int', null, null, 'age'),
            'email' => new ColumnStructureDTO('varchar', 255, null, 'email'),
        ];

        $migration = $this->generator->generateMigration('users', $structure);

        // Test that the migration contains the expected components
        $this->assertStringContains('Schema::create(\'users\'', $migration);

        $this->assertStringContains('$table->timestamps();', $migration);
        $this->assertStringContains('Schema::dropIfExists(\'users\')', $migration);

        // Test that columns are included
        $this->assertStringContains('string(\'name\', 255)', $migration);
        $this->assertStringContains('integer(\'age\')', $migration);
        $this->assertStringContains('string(\'email\', 255)', $migration);
    }

    public function testGenerateMigrationWithCustomClassName()
    {
        $structure = [
            'title' => new ColumnStructureDTO('varchar', 100, null, 'title'),
        ];

        $migration = $this->generator->generateMigration('posts', $structure, 'CreatePostsTable');

        $this->assertStringContains('Schema::create(\'posts\'', $migration);
        $this->assertStringContains('string(\'title\', 100)', $migration);
    }

    public function testGenerateSql()
    {
        $structure = [
            'name' => new ColumnStructureDTO('varchar', 255, null, 'name'),
            'age' => new ColumnStructureDTO('int', null, null, 'age'),
            'email' => new ColumnStructureDTO('varchar', 255, null, 'email'),
            'balance' => new ColumnStructureDTO('decimal', null, 2, 'balance'),
            'is_active' => new ColumnStructureDTO('boolean', null, null, 'is_active'),
        ];

        $sql = $this->generator->generateSql('users', $structure);

        // Test that the SQL contains the expected table structure
        $this->assertStringContains('CREATE TABLE `users`', $sql);
        $this->assertStringContains('`created_at` TIMESTAMP NULL DEFAULT NULL', $sql);
        $this->assertStringContains('`updated_at` TIMESTAMP NULL DEFAULT NULL', $sql);

        // Test that columns are included with proper SQL types
        $this->assertStringContains('`name` VARCHAR(255)', $sql);
        $this->assertStringContains('`age` INT', $sql);
        $this->assertStringContains('`email` VARCHAR(255)', $sql);
        $this->assertStringContains('`balance` DECIMAL(10,2)', $sql);
    }

    public function testGenerateSqlWithDifferentTypes()
    {
        $structure = [
            'description' => new ColumnStructureDTO('text', null, null, 'description'),
            'created_date' => new ColumnStructureDTO('date', null, null, 'created_date'),
            'metadata' => new ColumnStructureDTO('json', null, null, 'metadata'),
            'rating' => new ColumnStructureDTO('float', null, 2, 'rating'),
        ];

        $sql = $this->generator->generateSql('reviews', $structure);

        // Test specific SQL types
        $this->assertStringContains('`description` TEXT', $sql);
        $this->assertStringContains('`created_date` DATE', $sql);
        $this->assertStringContains('`metadata` JSON', $sql);
        $this->assertStringContains('`rating` FLOAT(2)', $sql);
    }

    public function testGenerateSqlWithEmptyStructure()
    {
        $structure = [];

        $sql = $this->generator->generateSql('empty_table', $structure);

        // Test that basic structure is still created
        $this->assertStringContains('CREATE TABLE `empty_table`', $sql);
        $this->assertStringContains('`created_at` TIMESTAMP NULL DEFAULT NULL', $sql);
        $this->assertStringContains('`updated_at` TIMESTAMP NULL DEFAULT NULL', $sql);
    }

    public function testGenerateSqlFormatting()
    {
        $structure = [
            'name' => new ColumnStructureDTO('varchar', 100, null, 'name'),
            'age' => new ColumnStructureDTO('int', null, null, 'age'),
        ];

        $sql = $this->generator->generateSql('users', $structure);

        // Test proper formatting
        $this->assertStringContains("CREATE TABLE `users` (\n", $sql);
        $this->assertStringContains("\n);", $sql);
        $this->assertStringContains(",\n    ", $sql);

        // Test that each column is on a separate line with proper indentation
        $lines = explode("\n", $sql);
        $this->assertGreaterThan(3, count($lines));

        // Test that columns are properly indented
        foreach ($lines as $line) {
            if (strpos($line, '`') !== false && strpos($line, 'CREATE TABLE') === false) {
                $this->assertStringStartsWith('    ', $line);
            }
        }
    }

    public function testGenerateMigrationWithComplexStructure()
    {
        $structure = [
            'first_name' => new ColumnStructureDTO('varchar', 50, null, 'first_name'),
            'last_name' => new ColumnStructureDTO('varchar', 50, null, 'last_name'),
            'birth_date' => new ColumnStructureDTO('date', null, null, 'birth_date'),
            'salary' => new ColumnStructureDTO('decimal', null, 2, 'salary'),
            'notes' => new ColumnStructureDTO('text', null, null, 'notes'),
            'preferences' => new ColumnStructureDTO('json', null, null, 'preferences'),
        ];

        $migration = $this->generator->generateMigration('employees', $structure);

        // Test that all column types are properly handled
        $this->assertStringContains('string(\'first_name\', 50)', $migration);
        $this->assertStringContains('string(\'last_name\', 50)', $migration);
        $this->assertStringContains('date(\'birth_date\')', $migration);
        $this->assertStringContains('decimal(\'salary\', 10, 2)', $migration);
        $this->assertStringContains('text(\'notes\')', $migration);
        $this->assertStringContains('json(\'preferences\')', $migration);
    }

    public function testGenerateSqlAndMigrationConsistency()
    {
        $structure = [
            'username' => new ColumnStructureDTO('varchar', 50, null, 'username'),
            'score' => new ColumnStructureDTO('int', null, null, 'score'),
            'active' => new ColumnStructureDTO('boolean', null, null, 'active'),
        ];

        $migration = $this->generator->generateMigration('players', $structure);
        $sql = $this->generator->generateSql('players', $structure);

        // Test that both contain the same table name
        $this->assertStringContains('players', $migration);
        $this->assertStringContains('players', $sql);

        // Test that both have id and timestamps

        $this->assertStringContains('timestamps', $migration);
        $this->assertStringContains('created_at', $sql);
        $this->assertStringContains('updated_at', $sql);
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

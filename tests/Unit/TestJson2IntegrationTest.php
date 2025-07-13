<?php

namespace DVRTech\SchemaTools\Tests\Unit;

use DVRTech\SchemaTools\Services\SchemaAnalyzer;
use DVRTech\SchemaTools\Generators\MigrationGenerator;
use DVRTech\SchemaTools\Generators\ModelGenerator;
use DVRTech\SchemaTools\Tests\TestDataHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Depends;

class TestJson2IntegrationTest extends TestCase
{
    private SchemaAnalyzer $analyzer;
    private MigrationGenerator $migrationGenerator;
    private ModelGenerator $modelGenerator;

    protected function setUp(): void
    {
        $this->analyzer = new SchemaAnalyzer();
        $this->migrationGenerator = new MigrationGenerator();
        $this->modelGenerator = new ModelGenerator();
    }

    public function testAnalyzeEnterpriseQuoteJsonFile()
    {
        $testDataPath = TestDataHelper::getTestFilePath('test-json-2.json');

        if (!file_exists($testDataPath)) {
            $this->markTestSkipped('Test data file not found: ' . $testDataPath);
        }

        // Analyze the enterprise quote JSON data
        $structure = $this->analyzer->analyzeJsonFile($testDataPath);

        // Verify structure contains expected fields
        $this->assertIsArray($structure);
        $this->assertArrayHasKey('name', $structure);
        $this->assertArrayHasKey('quoteNumber', $structure);
        $this->assertArrayHasKey('id', $structure);
        $this->assertArrayHasKey('accountName', $structure);
        $this->assertArrayHasKey('createDate', $structure);
        $this->assertArrayHasKey('quoteTotal', $structure);
        $this->assertArrayHasKey('isApproved', $structure);

        // Test specific field types
        $this->assertEquals('varchar', $structure['name']->type);
        $this->assertEquals('int', $structure['quoteNumber']->type);
        $this->assertEquals('varchar', $structure['id']->type);
        $this->assertEquals('float', $structure['grossMargin']->type);
        $this->assertEquals('date', $structure['createDate']->type);
        $this->assertEquals('int', $structure['isApproved']->type);  // Updated: booleans are now treated as integers

        // Test custom fields are detected
        $this->assertArrayHasKey('zCustomQuoteBool1', $structure);
        $this->assertArrayHasKey('zCustomQuoteDecimal1', $structure);
        $this->assertEquals('int', $structure['zCustomQuoteBool1']->type);  // Updated: booleans are now treated as integers
        $this->assertEquals('decimal', $structure['zCustomQuoteDecimal1']->type);

        return $structure;
    }

    #[Depends('testAnalyzeEnterpriseQuoteJsonFile')]
    public function testGenerateMySqlFromEnterpriseQuote(array $structure)
    {
        $sql = $this->migrationGenerator->generateMySql('enterprise_quotes', $structure);

        // Test basic structure
        $this->assertStringContains('CREATE TABLE `enterprise_quotes`', $sql);
        $this->assertStringEndsWith(');', $sql);

        // Test key business fields
        $this->assertStringContains('`name` VARCHAR(', $sql);
        $this->assertStringContains('`quoteNumber` INT', $sql);
        $this->assertStringContains('`quoteTotal` DECIMAL(', $sql);
        $this->assertStringContains('`isApproved` INT', $sql);  // Updated: booleans are now integers
        $this->assertStringContains('`accountName` VARCHAR(', $sql);

        // Test date fields
        $this->assertStringContains('`createDate` DATE', $sql);
        $this->assertStringContains('`expectedCloseDate` DATE', $sql);

        // Test custom fields
        $this->assertStringContains('`zCustomQuoteBool1` INT', $sql);  // Updated: booleans are now integers
        $this->assertStringContains('`zCustomQuoteDecimal1` DECIMAL(', $sql);

        // Test timestamps
        $this->assertStringContains('`created_at` TIMESTAMP NULL DEFAULT NULL', $sql);
        $this->assertStringContains('`updated_at` TIMESTAMP NULL DEFAULT NULL', $sql);

        // Test no syntax errors
        $this->assertStringNotContainsString(';;', $sql);
        $this->assertStringNotContainsString(',,', $sql);
    }

    #[Depends('testAnalyzeEnterpriseQuoteJsonFile')]
    public function testGenerateSqlServerFromEnterpriseQuote(array $structure)
    {
        $sql = $this->migrationGenerator->generateSqlServer('enterprise_quotes', $structure);

        // Test SQL Server specific syntax
        $this->assertStringContains('CREATE TABLE [enterprise_quotes]', $sql);
        $this->assertStringEndsWith(');', $sql);

        // Test SQL Server types
        $this->assertStringContains('[name] NVARCHAR(', $sql);
        $this->assertStringContains('[quoteNumber] INT', $sql);
        $this->assertStringContains('[isApproved] INT', $sql);  // Updated: booleans are now integers
        $this->assertStringContains('[createDate] DATE', $sql);

        // Test timestamps with SQL Server format
        $this->assertStringContains('[created_at] DATETIME2 NULL', $sql);
        $this->assertStringContains('[updated_at] DATETIME2 NULL', $sql);
    }

    #[Depends('testAnalyzeEnterpriseQuoteJsonFile')]
    public function testGeneratePostgreSQLFromEnterpriseQuote(array $structure)
    {
        $sql = $this->migrationGenerator->generatePostgreSQL('enterprise_quotes', $structure);

        // Test PostgreSQL specific syntax
        $this->assertStringContains('CREATE TABLE "enterprise_quotes"', $sql);
        $this->assertStringEndsWith(');', $sql);

        // Test PostgreSQL types
        $this->assertStringContains('"name" VARCHAR(', $sql);
        $this->assertStringContains('"quoteNumber" INTEGER', $sql);
        $this->assertStringContains('"isApproved" INT', $sql);  // Updated: booleans are now integers
        $this->assertStringContains('"createDate" DATE', $sql);

        // Test timestamps with PostgreSQL format
        $this->assertStringContains('"created_at" TIMESTAMP NULL', $sql);
        $this->assertStringContains('"updated_at" TIMESTAMP NULL', $sql);
    }

    #[Depends('testAnalyzeEnterpriseQuoteJsonFile')]
    public function testGenerateMigrationFromEnterpriseQuote(array $structure)
    {
        $migration = $this->migrationGenerator->generateMigration('enterprise_quotes', $structure);

        // Test Laravel migration structure
        $this->assertStringContains('use Illuminate\Database\Migrations\Migration;', $migration);
        $this->assertStringContains('use Illuminate\Database\Schema\Blueprint;', $migration);
        $this->assertStringContains('use Illuminate\Support\Facades\Schema;', $migration);

        // Test table creation
        $this->assertStringContains("Schema::create('enterprise_quotes'", $migration);
        $this->assertStringContains('function (Blueprint $table)', $migration);

        // Test key fields in Laravel syntax
        $this->assertStringContains("\$table->string('name'", $migration);
        $this->assertStringContains("\$table->integer('quoteNumber')", $migration);
        $this->assertStringContains("\$table->integer('isApproved')", $migration);  // Updated: booleans are now integers
        $this->assertStringContains("\$table->date('createDate')", $migration);
        $this->assertStringContains("\$table->decimal('quoteTotal'", $migration);

        // Test timestamps
        $this->assertStringContains('$table->timestamps();', $migration);

        // Test down method
        $this->assertStringContains("Schema::dropIfExists('enterprise_quotes');", $migration);
    }

    #[Depends('testAnalyzeEnterpriseQuoteJsonFile')]
    public function testGenerateModelFromEnterpriseQuote(array $structure)
    {
        $model = $this->modelGenerator->generateModel('EnterpriseQuote', $structure, 'enterprise_quotes');

        // Test basic model structure
        $this->assertStringContains('class EnterpriseQuote extends Model', $model);
        $this->assertStringContains('use Illuminate\Database\Eloquent\Model;', $model);

        // Test table name
        $this->assertStringContainsString('protected \\$table = \'enterprise_quotes\';', $model);

        // Test fillable fields
        $this->assertStringContainsString('protected \\$fillable = [', $model);
        $this->assertStringContains("'name',", $model);
        $this->assertStringContains("'quoteNumber',", $model);
        $this->assertStringContains("'accountName',", $model);
        $this->assertStringContains("'quoteTotal',", $model);

        // Test casts for specific types
        $this->assertStringContainsString('protected \\$casts = [', $model);
        $this->assertStringContains("'isApproved' => 'integer',", $model);  // Updated: booleans are now integers
        $this->assertStringContains("'createDate' => 'date',", $model);
    }

    public function testMultipleDatabaseConsistency()
    {
        $testDataPath = TestDataHelper::getTestFilePath('test-json-2.json');
        $structure = $this->analyzer->analyzeJsonFile($testDataPath);

        // Generate SQL for all three databases
        $mysqlSql = $this->migrationGenerator->generateMySql('quotes', $structure);
        $sqlServerSql = $this->migrationGenerator->generateSqlServer('quotes', $structure);
        $postgreSql = $this->migrationGenerator->generatePostgreSQL('quotes', $structure);

        // All should create valid tables
        $this->assertStringContains('CREATE TABLE', $mysqlSql);
        $this->assertStringContains('CREATE TABLE', $sqlServerSql);
        $this->assertStringContains('CREATE TABLE', $postgreSql);

        // All should end properly
        $this->assertStringEndsWith(');', $mysqlSql);
        $this->assertStringEndsWith(');', $sqlServerSql);
        $this->assertStringEndsWith(');', $postgreSql);

        // All should have timestamps
        $this->assertStringContains('created_at', $mysqlSql);
        $this->assertStringContains('created_at', $sqlServerSql);
        $this->assertStringContains('created_at', $postgreSql);
    }

    public function testEnterpriseQuoteSpecificFields()
    {
        $testDataPath = TestDataHelper::getTestFilePath('test-json-2.json');
        $structure = $this->analyzer->analyzeJsonFile($testDataPath);

        // Test that all the enterprise-specific fields are detected
        $expectedFields = [
            'name',
            'quoteNumber',
            'quoteVersion',
            'id',
            'accountName',
            'approvalAmount',
            'approvalComment',
            'approvalMargin',
            'baseCurrency',
            'grossMargin',
            'grossMarginAmount',
            'quoteTotal',
            'quoteStatus',
            'isApproved',
            'isAccepted',
            'isManagerApproved'
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $structure, "Field '{$field}' should be detected in structure");
        }

        // Test custom field ranges
        for ($i = 1; $i <= 10; $i++) {
            $this->assertArrayHasKey("zCustomQuoteBool{$i}", $structure);
            $this->assertEquals('int', $structure["zCustomQuoteBool{$i}"]->type);  // Updated: booleans are now treated as integers
        }

        for ($i = 1; $i <= 20; $i++) {
            $this->assertArrayHasKey("zCustomQuoteDecimal{$i}", $structure);
            $this->assertEquals('decimal', $structure["zCustomQuoteDecimal{$i}"]->type);
        }
    }

    public function testFieldTypeDetectionAccuracy()
    {
        $testDataPath = TestDataHelper::getTestFilePath('test-json-2.json');
        $structure = $this->analyzer->analyzeJsonFile($testDataPath);

        // Test specific type detection
        $typeTests = [
            'name' => 'varchar',
            'quoteNumber' => 'int',
            'approvalMargin' => 'float',
            'createDate' => 'date',
            'isApproved' => 'int',  // Updated: booleans are now treated as integers
            'grossMarginAmount' => 'decimal',
            'approvalComment' => 'varchar',
            'baseCurrency' => 'varchar',
            'taxRate' => 'float'
        ];

        foreach ($typeTests as $field => $expectedType) {
            $this->assertArrayHasKey($field, $structure);
            $this->assertEquals(
                $expectedType,
                $structure[$field]->type,
                "Field '{$field}' should be detected as '{$expectedType}' but was '{$structure[$field]->type}'"
            );
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

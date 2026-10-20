<?php

namespace DVRTech\SchemaTools\Tests\Unit;

use DVRTech\SchemaTools\DTO\ColumnStructureDTO;
use DVRTech\SchemaTools\Services\DatabaseSchemaGenerator;
use DVRTech\SchemaTools\Services\SchemaAnalyzer;
use DVRTech\SchemaTools\Services\SchemaDiff;
use DVRTech\SchemaTools\Support\InputPaths;
use PHPUnit\Framework\TestCase;

class CdkInferenceTest extends TestCase
{
    private SchemaAnalyzer $analyzer;
    private string $directory;

    protected function setUp(): void
    {
        $this->analyzer = new SchemaAnalyzer();
        $this->directory = sys_get_temp_dir() . '/schema-tools-cdk-' . uniqid('', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->directory);
    }

    public function testFractionalSecondsAreDatetime(): void
    {
        $structure = $this->analyzer->analyzeDataStructure([
            ['worked_on' => '2026-10-19 00:00:00.0'],
            ['worked_on' => '2026-10-20 09:34:14.51185'],
        ]);

        $this->assertSame('datetime', $structure['worked_on']->type);
        $this->assertSame(5, $structure['worked_on']->fractionalSeconds);
        $this->assertSame('DATETIME(5)', $structure['worked_on']->getSqlDefinition());
        $this->assertSame('DATETIME2', $structure['worked_on']->getMsSqlTypeDefinition());
        $this->assertSame("dateTime('worked_on', 5)", $structure['worked_on']->getLaravelMigrationDefinition());
        $this->assertSame('datetime', $structure['worked_on']->getEloquentCast());
    }

    public function testPureTimeIsTimeAndDateOnlyStaysDate(): void
    {
        $structure = $this->analyzer->analyzeDataStructure([
            ['clock_in' => '17:26:31', 'day' => '2026-10-19'],
            ['clock_in' => '09:01:02', 'day' => '2026-10-20'],
        ]);

        $this->assertSame('time', $structure['clock_in']->type);
        $this->assertSame('TIME', $structure['clock_in']->getSqlDefinition());
        $this->assertSame('TIME', $structure['clock_in']->getMsSqlTypeDefinition());
        $this->assertSame("time('clock_in')", $structure['clock_in']->getLaravelMigrationDefinition());
        $this->assertNull($structure['clock_in']->getEloquentCast());
        $this->assertSame('date', $structure['day']->type);
        $this->assertSame('DATE', $structure['day']->getMsSqlTypeDefinition());
    }

    public function testEightFractionalDigitsStayVarchar(): void
    {
        $structure = $this->analyzer->analyzeDataStructure([
            ['worked_on' => '2026-10-20 09:34:14.12345678'],
        ]);

        $this->assertSame('varchar', $structure['worked_on']->type);
    }

    public function testImpossibleDatesAndTimesStayVarchar(): void
    {
        $structure = $this->analyzer->analyzeDataStructure([
            ['bad_date' => '2026-02-30', 'bad_time' => '24:00:00', 'zero_date' => '0000-00-00'],
        ]);

        $this->assertSame('varchar', $structure['bad_date']->type);
        $this->assertSame('varchar', $structure['bad_time']->type);
        $this->assertSame('varchar', $structure['zero_date']->type);
    }

    public function testDateMixedWithDatetimePromotesToDatetime(): void
    {
        $structure = $this->analyzer->analyzeDataStructure([
            ['worked_on' => '2026-10-19'],
            ['worked_on' => '2026-10-20 09:34:14.51185'],
        ]);

        $this->assertSame('datetime', $structure['worked_on']->type);
        $this->assertTrue($structure['worked_on']->canAcceptType('date'));
        $this->assertFalse((new ColumnStructureDTO('date'))->canAcceptType('datetime'));
        $this->assertFalse((new ColumnStructureDTO('time'))->canAcceptType('date'));
    }

    public function testOtherTemporalMixesPromoteToVarchar(): void
    {
        $dateAndTime = $this->analyzer->analyzeDataStructure([
            ['value' => '2026-10-19'],
            ['value' => '17:26:31'],
        ]);
        $dateTimeAndTime = $this->analyzer->analyzeDataStructure([
            ['value' => '2026-10-20 09:34:14.51185'],
            ['value' => '17:26:31'],
        ]);

        $this->assertSame('varchar', $dateAndTime['value']->type);
        $this->assertSame('varchar', $dateTimeAndTime['value']->type);
    }

    public function testFloatSqlIsEightByteAndDecimalIsSizedFromTheData(): void
    {
        $structure = $this->analyzer->analyzeDataStructure([
            ['ratio' => '3.14159', 'amount' => '12345.67'],
            ['ratio' => '2.5', 'amount' => '1.2'],
        ]);

        $this->assertSame('float', $structure['ratio']->type);
        $this->assertSame('DOUBLE', $structure['ratio']->getSqlDefinition());
        $this->assertSame('FLOAT', $structure['ratio']->getMsSqlTypeDefinition());
        $this->assertSame("double('ratio')", $structure['ratio']->getLaravelMigrationDefinition());
        $this->assertSame('float', $structure['ratio']->getEloquentCast());

        $this->assertSame('decimal', $structure['amount']->type);
        $this->assertSame(5, $structure['amount']->integerDigits);
        $this->assertSame(2, $structure['amount']->precision);
        $this->assertSame('DECIMAL(7,2)', $structure['amount']->getSqlDefinition());
        $this->assertSame('DECIMAL(7,2)', $structure['amount']->getMsSqlTypeDefinition());
        $this->assertSame("decimal('amount', 7, 2)", $structure['amount']->getLaravelMigrationDefinition());
    }

    public function testBigintMixedWithFloatBecomesDecimal(): void
    {
        $mixed = $this->analyzer->analyzeDataStructure([
            ['value' => '2147483648'],
            ['value' => '1.5'],
        ]);
        $wide = $this->analyzer->analyzeDataStructure([
            ['value' => '1234567890123456.25'],
        ]);
        $small = $this->analyzer->analyzeDataStructure([
            ['value' => '1'],
            ['value' => '1.5'],
        ]);

        $this->assertSame('decimal', $mixed['value']->type);
        $this->assertSame(10, $mixed['value']->integerDigits);
        $this->assertSame(1, $mixed['value']->precision);
        $this->assertSame('DECIMAL(11,1)', $mixed['value']->getSqlDefinition());
        $this->assertSame('decimal', $wide['value']->type);
        $this->assertSame('DECIMAL(18,2)', $wide['value']->getSqlDefinition());
        $this->assertSame('float', $small['value']->type);
    }

    public function testDecimalColumnsOptionAndWholeNumbersInAnExactColumn(): void
    {
        $structure = $this->analyzer->analyzeDataStructure([
            ['hours' => '12', 'rate' => '19.5'],
            ['hours' => '1.25', 'rate' => '7'],
        ], ['decimal_columns' => ['hours', 'Rate']]);

        $this->assertSame('decimal', $structure['hours']->type);
        $this->assertSame(2, $structure['hours']->integerDigits);
        $this->assertSame(2, $structure['hours']->precision);
        $this->assertSame('DECIMAL(4,2)', $structure['hours']->getSqlDefinition());
        $this->assertSame('decimal', $structure['rate']->type);
    }

    public function testHandBuiltDecimalWithoutIntegerDigitsKeepsTheLegacyWidth(): void
    {
        $column = new ColumnStructureDTO('decimal', null, 2, 'balance');

        $this->assertSame('DECIMAL(10,2)', $column->getSqlDefinition());
    }

    public function testLeadingZerosStayVarchar(): void
    {
        $structure = $this->analyzer->analyzeDataStructure([
            ['code' => '007', 'fraction' => '0123.45', 'plain' => '0', 'money' => '0.50'],
            ['code' => '0123', 'fraction' => '15', 'plain' => '8', 'money' => '1.25'],
        ]);

        $this->assertSame('varchar', $structure['code']->type);
        $this->assertSame('varchar', $structure['fraction']->type);
        $this->assertSame('int', $structure['plain']->type);
        $this->assertSame('float', $structure['money']->type);
    }

    public function testIntegerOverflowBecomesBigintAndVeryLongDigitsStayVarchar(): void
    {
        $boundary = $this->analyzer->analyzeDataStructure([
            ['fits' => '2147483647', 'wide' => '2147483648', 'huge' => '9223372036854775807', 'text' => '9223372036854775808'],
            ['fits' => '-2147483648', 'wide' => '-2147483649', 'huge' => '-9223372036854775808', 'text' => '10000000000000000000'],
        ]);

        $this->assertSame('int', $boundary['fits']->type);
        $this->assertSame('INT', $boundary['fits']->getSqlDefinition());
        $this->assertSame('bigint', $boundary['wide']->type);
        $this->assertSame('BIGINT', $boundary['wide']->getSqlDefinition());
        $this->assertSame('BIGINT', $boundary['wide']->getMsSqlTypeDefinition());
        $this->assertSame("bigInteger('wide')", $boundary['wide']->getLaravelMigrationDefinition());
        $this->assertSame('integer', $boundary['wide']->getEloquentCast());
        $this->assertTrue($boundary['wide']->canAcceptType('int'));
        $this->assertFalse((new ColumnStructureDTO('float'))->canAcceptType('bigint'));
        $this->assertSame('bigint', $boundary['huge']->type);
        $this->assertSame('varchar', $boundary['text']->type);
    }

    public function testScientificNotationIsNumericOnlyWhenEveryValueUsesIt(): void
    {
        $only = $this->analyzer->analyzeDataStructure([
            ['value' => '1e5'],
            ['value' => '2.5E3'],
        ]);
        $mixed = $this->analyzer->analyzeDataStructure([
            ['value' => '1e5'],
            ['value' => '100'],
        ]);

        $this->assertSame('float', $only['value']->type);
        $this->assertSame('varchar', $mixed['value']->type);
    }

    public function testStringColumnsOptionForcesVarchar(): void
    {
        $structure = $this->analyzer->analyzeDataStructure([
            ['ronumber' => '100', 'techniciannumber' => '55'],
            ['ronumber' => '200', 'techniciannumber' => '62'],
        ], ['string_columns' => ['RONumber', 'techniciannumber']]);

        $this->assertSame('varchar', $structure['ronumber']->type);
        $this->assertSame('varchar', $structure['techniciannumber']->type);
        $this->assertSame('VARCHAR(255)', $structure['ronumber']->getSqlDefinition());
    }

    public function testCsvKeepsBackslashesSkipsBadRowsAndMergesFiles(): void
    {
        $payroll = $this->directory . '/payroll.csv';
        $other = $this->directory . '/other.csv';
        file_put_contents($payroll, "techniciannumber,note\n1,\"a\\b\"\n2,ok\nshort\n\n3,z\n");
        file_put_contents($other, "TechnicianNumber,note\nT-100,\"c\\d\"\n");

        $structure = $this->analyzer->analyzeFiles([$payroll, $other]);

        $this->assertSame(1, $this->analyzer->getSkippedRowCount());
        $this->assertSame('varchar', $structure['techniciannumber']->type);
        $this->assertSame(5, $structure['techniciannumber']->observedLength);
        $this->assertSame(3, $structure['note']->observedLength);
        $this->assertFalse($structure['techniciannumber']->nullable);
    }

    public function testVarcharReportsObservedLengthAndUsesTheDefaultWidth(): void
    {
        $structure = $this->analyzer->analyzeDataStructure([
            ['name' => 'Jo'],
            ['name' => 'Bob'],
        ], ['varchar_default' => 255]);

        $this->assertSame(3, $structure['name']->observedLength);
        $this->assertSame(255, $structure['name']->length);
        $this->assertSame('NVARCHAR(255)', $structure['name']->getMsSqlTypeDefinition());

        $wide = $this->analyzer->analyzeDataStructure([
            ['name' => str_repeat('a', 300)],
        ]);
        $this->assertSame('text', $wide['name']->type);
        $this->assertSame(300, $wide['name']->observedLength);
        $this->assertSame('NVARCHAR(MAX)', $wide['name']->getMsSqlTypeDefinition());
    }

    public function testGlobAndCommaSeparatedPathsMerge(): void
    {
        file_put_contents($this->directory . '/a.csv', "code\n1\n");
        file_put_contents($this->directory . '/b.csv', "code\nABC\n");

        $paths = InputPaths::expand([
            $this->directory . '/*.csv',
        ]);
        $this->assertCount(2, $paths);

        $structure = $this->analyzer->analyzeFiles($paths);
        $this->assertSame('varchar', $structure['code']->type);

        $listed = InputPaths::expand([
            $this->directory . '/a.csv,' . $this->directory . '/b.csv',
        ]);
        $this->assertCount(2, $listed);
    }

    public function testDuplicateAndEmptyHeadersAreRejected(): void
    {
        $duplicate = $this->directory . '/dup.csv';
        file_put_contents($duplicate, "HostItemID,hostitemid\n1,2\n");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate column');
        $this->analyzer->analyzeCSVFile($duplicate);
    }

    public function testSqlServerCreateTableGuardsTheBatch(): void
    {
        $structure = [
            'hostitemid' => new ColumnStructureDTO('varchar', 255, null, 'hostitemid', null, 12, false, true),
            'cora_acct_id' => new ColumnStructureDTO('int', null, null, 'cora_acct_id', null, 4, false, true),
            'amount' => new ColumnStructureDTO('decimal', null, 2, 'amount', 5, 8, true, true),
            'worked_on' => new ColumnStructureDTO('datetime', null, null, 'worked_on', null, 26, true, true, 5),
            'clock_in' => new ColumnStructureDTO('time', null, null, 'clock_in', null, 8, true, true),
            'notes' => new ColumnStructureDTO('text', null, null, 'notes', null, 400, true, true),
        ];

        $sql = (new DatabaseSchemaGenerator())->generateMsSqlCreateTable(
            'TechPayroll',
            $structure,
            'C208384',
            ['hostitemid', 'cora_acct_id'],
            true,
            'SQL_Latin1_General_CP1_CI_AS'
        );

        $this->assertStringContainsString("IF OBJECT_ID(N'[C208384].[TechPayroll]', N'U') IS NULL", $sql);
        $this->assertStringContainsString('CREATE TABLE [C208384].[TechPayroll]', $sql);
        $this->assertStringContainsString('[hostitemid] NVARCHAR(255) COLLATE SQL_Latin1_General_CP1_CI_AS NOT NULL', $sql);
        $this->assertStringContainsString('[cora_acct_id] INT NOT NULL', $sql);
        $this->assertStringContainsString('[amount] DECIMAL(7,2) NULL', $sql);
        $this->assertStringContainsString('[worked_on] DATETIME2 NULL', $sql);
        $this->assertStringContainsString('[clock_in] TIME NULL', $sql);
        $this->assertStringContainsString('[notes] NVARCHAR(MAX) COLLATE SQL_Latin1_General_CP1_CI_AS NULL', $sql);
        $this->assertStringContainsString('[created_at] DATETIME NULL', $sql);
        $this->assertStringContainsString('[updated_at] DATETIME NULL', $sql);
        $this->assertStringContainsString('CONSTRAINT [PK_C208384_TechPayroll] PRIMARY KEY NONCLUSTERED ([hostitemid], [cora_acct_id])', $sql);
        $this->assertStringNotContainsString('NTEXT', $sql);
        $this->assertStringNotContainsString('FLOAT(', $sql);
    }

    public function testPrimaryKeyRejectsNullsAndMissingColumns(): void
    {
        $generator = new DatabaseSchemaGenerator();
        $structure = [
            'hostitemid' => new ColumnStructureDTO('varchar', 255, null, 'hostitemid', null, 3, true, true),
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('null or empty');
        $generator->generateMsSqlCreateTable('TechPayroll', $structure, 'C208384', ['hostitemid']);
    }

    public function testExistingTableDiffReportsMissingNarrowerAndDatetimeMismatches(): void
    {
        $inferred = [
            'hostitemid' => new ColumnStructureDTO('varchar', 255, null, 'hostitemid'),
            'amount' => new ColumnStructureDTO('decimal', null, 2, 'amount', 5),
            'worked_on' => new ColumnStructureDTO('datetime', null, null, 'worked_on', null, null, false, true, 5),
            'note' => new ColumnStructureDTO('varchar', 50, null, 'note'),
        ];
        $existing = [
            $this->column('hostitemid', 'nvarchar', 50),
            $this->column('amount', 'decimal', null, 10, 1),
            $this->column('worked_on', 'datetime', null, null, null, 3),
            $this->column('note', 'nvarchar', 255),
            $this->column('legacy_flag', 'int'),
        ];

        $diff = (new SchemaDiff())->compare($inferred, $existing);

        $this->assertSame(['hostitemid', 'amount', 'worked_on'], array_column($diff['mismatches'], 'name'));
        $this->assertSame(['note'], array_column($diff['wider'], 'name'));
        $this->assertSame(['legacy_flag'], $diff['extra']);
        $this->assertSame([], $diff['missing']);
    }

    /**
     * @return array{name: string, data_type: string, char_length: int|null, numeric_precision: int|null, numeric_scale: int|null, datetime_precision: int|null}
     */
    private function column(
        string $name,
        string $type,
        ?int $length = null,
        ?int $precision = null,
        ?int $scale = null,
        ?int $datetimePrecision = null
    ): array {
        return [
            'name' => $name,
            'data_type' => $type,
            'char_length' => $length,
            'numeric_precision' => $precision,
            'numeric_scale' => $scale,
            'datetime_precision' => $datetimePrecision,
        ];
    }

}

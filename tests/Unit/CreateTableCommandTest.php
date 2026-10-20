<?php

namespace DVRTech\SchemaTools\Tests\Unit;

use DVRTech\SchemaTools\Tests\PackageTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CreateTableCommandTest extends PackageTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/schema-tools-cmd-' . uniqid('', true);
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
        parent::tearDown();
    }

    public function testAnalyzePrintsSqlServerDefinitionsForAGlob(): void
    {
        file_put_contents(
            $this->directory . '/one.csv',
            "worked_on,clock_in\n2026-10-20 09:34:14.51185,17:26:31\n"
        );

        $code = Artisan::call('schema-tools:analyze', [
            'files' => [$this->directory . '/*.csv'],
            '--driver' => 'sqlsrv',
        ]);

        $output = Artisan::output();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('DATETIME2', $output);
        $this->assertStringContainsString('TIME', $output);
    }

    public function testCreateTablePrintsGuardedSqlServerDdl(): void
    {
        file_put_contents($this->directory . '/payroll.csv', "hostitemid,amount\nA100,12.50\nA200,1.5\n");

        $code = Artisan::call('schema-tools:create-table', [
            'files' => $this->directory . '/payroll.csv',
            'table' => 'TechPayroll',
            '--schema' => 'C208384',
            '--primary-key' => 'hostitemid',
            '--timestamps' => true,
            '--collation' => 'SQL_Latin1_General_CP1_CI_AS',
            '--decimal-columns' => 'amount',
        ]);

        $output = Artisan::output();
        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString("IF OBJECT_ID(N'[C208384].[TechPayroll]', N'U') IS NULL", $output);
        $this->assertStringContainsString('CONSTRAINT [PK_C208384_TechPayroll] PRIMARY KEY NONCLUSTERED ([hostitemid])', $output);
        $this->assertStringContainsString('[created_at] DATETIME NULL', $output);
        $this->assertStringContainsString('DECIMAL(', $output);
    }

    public function testExecuteRefusesWhenTheTableAlreadyExists(): void
    {
        file_put_contents($this->directory . '/payroll.csv', "hostitemid\n1\n2\n");

        $connection = \Mockery::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('sqlsrv');
        $connection->shouldReceive('selectOne')->once()->andReturn((object) ['object_id' => 15]);
        $connection->shouldReceive('select')->once()->andReturn([
            (object) [
                'COLUMN_NAME' => 'hostitemid',
                'DATA_TYPE' => 'nvarchar',
                'CHARACTER_MAXIMUM_LENGTH' => 50,
                'NUMERIC_PRECISION' => null,
                'NUMERIC_SCALE' => null,
                'DATETIME_PRECISION' => null,
            ],
        ]);
        $connection->shouldReceive('unprepared')->never();
        DB::shouldReceive('connection')->once()->with('sqlsrv')->andReturn($connection);

        $code = Artisan::call('schema-tools:create-table', [
            'files' => $this->directory . '/payroll.csv',
            'table' => 'TechPayroll',
            '--schema' => 'C208384',
            '--primary-key' => 'hostitemid',
            '--connection' => 'sqlsrv',
            '--execute' => true,
        ]);

        $output = Artisan::output();
        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('already exists', $output);
        $this->assertStringContainsString('Type mismatches', $output);
        $this->assertStringContainsString('hostitemid', $output);
    }
}

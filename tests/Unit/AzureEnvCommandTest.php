<?php

namespace DVRTech\SchemaTools\Tests\Unit;

use DVRTech\SchemaTools\Console\AzureEnvCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\TestCase;

class AzureEnvCommandTest extends TestCase
{
    private string $testDir;
    private string $azureFile;
    private string $envFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/azure-env-test-' . uniqid();
        mkdir($this->testDir);
        $this->azureFile = $this->testDir . '/azure-settings.json';
        $this->envFile = $this->testDir . '/.env';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->azureFile)) {
            unlink($this->azureFile);
        }
        if (file_exists($this->envFile)) {
            unlink($this->envFile);
        }
        if (is_dir($this->testDir)) {
            rmdir($this->testDir);
        }
        parent::tearDown();
    }

    public function testConvertAzureToEnv()
    {
        // Create test Azure settings JSON
        $azureSettings = [
            [
                'name' => 'APP_NAME',
                'value' => 'TestApp',
                'slotSetting' => false
            ],
            [
                'name' => 'DATABASE_URL',
                'value' => 'mysql://user:pass@localhost/db',
                'slotSetting' => false
            ],
            [
                'name' => 'SPECIAL_VALUE',
                'value' => 'Has spaces and "quotes"',
                'slotSetting' => false
            ]
        ];

        file_put_contents($this->azureFile, json_encode($azureSettings));

        // Test the conversion logic directly
        $command = $this->getMockBuilder(AzureEnvCommand::class)
            ->onlyMethods(['info', 'line'])
            ->getMock();

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('convertAzureToEnv');
        $method->setAccessible(true);

        $method->invoke($command, $this->azureFile, $this->envFile);

        // Verify the .env file was created
        $this->assertTrue(file_exists($this->envFile));

        $envContent = file_get_contents($this->envFile);

        // Check that the content includes our test data
        $this->assertStringContainsString('APP_NAME=TestApp', $envContent);
        $this->assertStringContainsString('DATABASE_URL=mysql://user:pass@localhost/db', $envContent);
        $this->assertStringContainsString('SPECIAL_VALUE="Has spaces and \"quotes\""', $envContent);
    }

    public function testConvertEnvToAzure()
    {
        // Create test .env file
        $envContent = <<<ENV
# Test environment file
APP_NAME=TestApp
DATABASE_URL=mysql://user:pass@localhost/db
SPECIAL_VALUE="Has spaces and \"quotes\""
EMPTY_VALUE=
DEBUG=true
ENV;

        file_put_contents($this->envFile, $envContent);

        // Test the conversion logic directly
        $command = $this->getMockBuilder(AzureEnvCommand::class)
            ->onlyMethods(['info', 'line'])
            ->getMock();

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('convertEnvToAzure');
        $method->setAccessible(true);

        $method->invoke($command, $this->envFile, $this->azureFile);

        // Verify the Azure JSON file was created
        $this->assertTrue(file_exists($this->azureFile));

        $azureContent = file_get_contents($this->azureFile);
        $azureSettings = json_decode($azureContent, true);

        // Verify the structure
        $this->assertIsArray($azureSettings);
        $this->assertCount(5, $azureSettings);

        // Check specific values by finding them in the array
        $appNameSetting = null;
        $specialValueSetting = null;
        $emptyValueSetting = null;

        foreach ($azureSettings as $setting) {
            if ($setting['name'] === 'APP_NAME') {
                $appNameSetting = $setting;
            } elseif ($setting['name'] === 'SPECIAL_VALUE') {
                $specialValueSetting = $setting;
            } elseif ($setting['name'] === 'EMPTY_VALUE') {
                $emptyValueSetting = $setting;
            }
        }

        $this->assertNotNull($appNameSetting);
        $this->assertEquals('TestApp', $appNameSetting['value']);
        $this->assertFalse($appNameSetting['slotSetting']);

        $this->assertNotNull($specialValueSetting);
        $this->assertEquals('Has spaces and "quotes"', $specialValueSetting['value']);

        $this->assertNotNull($emptyValueSetting);
        $this->assertNull($emptyValueSetting['value']);
    }

    public function testAzureToEnvWithInvalidJson()
    {
        file_put_contents($this->azureFile, 'invalid json');

        $command = $this->getMockBuilder(AzureEnvCommand::class)
            ->onlyMethods(['info', 'line'])
            ->getMock();

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('convertAzureToEnv');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $method->invoke($command, $this->azureFile, $this->envFile);
    }

    public function testEnvToAzureWithMissingFile()
    {
        $command = $this->getMockBuilder(AzureEnvCommand::class)
            ->onlyMethods(['info', 'line'])
            ->getMock();

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('convertEnvToAzure');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('.env file not found');

        $method->invoke($command, $this->envFile, $this->azureFile);
    }
}

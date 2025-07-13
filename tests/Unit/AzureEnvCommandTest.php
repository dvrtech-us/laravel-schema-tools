<?php

namespace DVRTech\SchemaTools\Tests\Unit;

use DVRTech\SchemaTools\Console\AzureEnvCommand;
use DVRTech\SchemaTools\Tests\TestDataHelper;
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

    public function testConvertExampleEnvFileToAzure()
    {
        // Use the actual example-env.env.txt file from test data
        $exampleEnvFile = TestDataHelper::getTestFilePath('example-env.env.txt');

        $this->assertTrue(file_exists($exampleEnvFile), 'Example .env file should exist');

        // Test the conversion logic directly
        $command = $this->getMockBuilder(AzureEnvCommand::class)
            ->onlyMethods(['info', 'line'])
            ->getMock();

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('convertEnvToAzure');
        $method->setAccessible(true);

        $method->invoke($command, $exampleEnvFile, $this->azureFile);

        // Verify the Azure JSON file was created
        $this->assertTrue(file_exists($this->azureFile));

        $azureContent = file_get_contents($this->azureFile);
        $azureSettings = json_decode($azureContent, true);

        // Verify the structure
        $this->assertIsArray($azureSettings);

        // Find specific settings from the example file
        $settingsByName = [];
        foreach ($azureSettings as $setting) {
            $settingsByName[$setting['name']] = $setting;
        }

        // Test key Laravel environment variables
        $this->assertArrayHasKey('APP_NAME', $settingsByName);
        $this->assertEquals('Laravel', $settingsByName['APP_NAME']['value']);
        $this->assertFalse($settingsByName['APP_NAME']['slotSetting']);

        $this->assertArrayHasKey('APP_ENV', $settingsByName);
        $this->assertEquals('local', $settingsByName['APP_ENV']['value']);

        $this->assertArrayHasKey('APP_DEBUG', $settingsByName);
        $this->assertEquals('true', $settingsByName['APP_DEBUG']['value']);

        // Test database configuration
        $this->assertArrayHasKey('DB_CONNECTION', $settingsByName);
        $this->assertEquals('mysql', $settingsByName['DB_CONNECTION']['value']);

        $this->assertArrayHasKey('DB_HOST', $settingsByName);
        $this->assertEquals('127.0.0.1', $settingsByName['DB_HOST']['value']);

        $this->assertArrayHasKey('DB_DATABASE', $settingsByName);
        $this->assertEquals('your_database_name', $settingsByName['DB_DATABASE']['value']);

        // Test mail configuration with quoted values
        $this->assertArrayHasKey('MAIL_FROM_NAME', $settingsByName);
        $this->assertEquals('${APP_NAME}', $settingsByName['MAIL_FROM_NAME']['value']);

        // Test empty values (should become null in Azure)
        $this->assertArrayHasKey('AWS_ACCESS_KEY_ID', $settingsByName);
        $this->assertNull($settingsByName['AWS_ACCESS_KEY_ID']['value']);

        $this->assertArrayHasKey('REDIS_PASSWORD', $settingsByName);
        $this->assertEquals('null', $settingsByName['REDIS_PASSWORD']['value']);

        // Verify all settings have the required structure
        foreach ($azureSettings as $setting) {
            $this->assertArrayHasKey('name', $setting);
            $this->assertArrayHasKey('value', $setting);
            $this->assertArrayHasKey('slotSetting', $setting);
            $this->assertIsBool($setting['slotSetting']);
        }
    }

    public function testConvertExampleAzureFileToEnv()
    {
        // Use the actual example-azure-settings.json file from test data
        $exampleAzureFile = TestDataHelper::getTestFilePath('example-azure-settings.json');

        $this->assertTrue(file_exists($exampleAzureFile), 'Example Azure file should exist');

        // Test the conversion logic directly
        $command = $this->getMockBuilder(AzureEnvCommand::class)
            ->onlyMethods(['info', 'line'])
            ->getMock();

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('convertAzureToEnv');
        $method->setAccessible(true);

        $method->invoke($command, $exampleAzureFile, $this->envFile);

        // Verify the .env file was created
        $this->assertTrue(file_exists($this->envFile));

        $envContent = file_get_contents($this->envFile);

        // Check that the content includes expected values from example Azure file
        $this->assertStringContainsString('APP_NAME="My Laravel App"', $envContent);
        $this->assertStringContainsString('APP_ENV=production', $envContent);
        $this->assertStringContainsString('APP_DEBUG=false', $envContent);
        $this->assertStringContainsString('DATABASE_URL=mysql://user:password@localhost:3306/mydb', $envContent);
        $this->assertStringContainsString('REDIS_URL=redis://localhost:6379', $envContent);
        $this->assertStringContainsString('API_KEY=abc123-def456-ghi789', $envContent);
        $this->assertStringContainsString('MAIL_DRIVER=smtp', $envContent);

        // Test proper escaping of values with spaces and quotes
        $this->assertStringContainsString('SPECIAL_CONFIG="This has spaces and \"quotes\""', $envContent);

        // Verify file structure (comments at top)
        $this->assertStringContainsString('# Generated from Azure App Settings', $envContent);
        $this->assertStringContainsString('# Generated on:', $envContent);
        $this->assertStringContainsString('# Source file:', $envContent);
    }

    public function testRoundTripConversion()
    {
        // Test that converting .env -> Azure -> .env preserves values
        $exampleEnvFile = TestDataHelper::getTestFilePath('example-env.env.txt');
        $tempAzureFile = $this->testDir . '/temp-azure.json';
        $tempEnvFile = $this->testDir . '/temp.env';

        $command = $this->getMockBuilder(AzureEnvCommand::class)
            ->onlyMethods(['info', 'line'])
            ->getMock();

        $reflection = new \ReflectionClass($command);
        $envToAzure = $reflection->getMethod('convertEnvToAzure');
        $azureToEnv = $reflection->getMethod('convertAzureToEnv');
        $envToAzure->setAccessible(true);
        $azureToEnv->setAccessible(true);

        // Convert .env to Azure
        $envToAzure->invoke($command, $exampleEnvFile, $tempAzureFile);

        // Convert back to .env
        $azureToEnv->invoke($command, $tempAzureFile, $tempEnvFile);

        // Read both files and compare key values
        $originalContent = file_get_contents($exampleEnvFile);
        $convertedContent = file_get_contents($tempEnvFile);

        // Parse original .env file
        $originalVars = $this->parseEnvFile($originalContent);
        $convertedVars = $this->parseEnvFile($convertedContent);

        // Test that key values are preserved (ignoring comments and empty values)
        foreach ($originalVars as $key => $value) {
            if (!empty($value) && $value !== 'null') {
                $this->assertArrayHasKey($key, $convertedVars, "Variable {$key} should be preserved");
                $this->assertEquals($value, $convertedVars[$key], "Value for {$key} should be preserved");
            }
        }

        // Clean up temp files
        unlink($tempAzureFile);
        unlink($tempEnvFile);
    }

    private function parseEnvFile(string $content): array
    {
        $vars = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);

                    // Remove quotes if present
                    if (strlen($value) >= 2) {
                        if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1)) {
                            $value = substr($value, 1, -1);
                            $value = str_replace('\\"', '"', $value);
                        }
                    }

                    $vars[$key] = $value;
                }
            }
        }

        return $vars;
    }
}

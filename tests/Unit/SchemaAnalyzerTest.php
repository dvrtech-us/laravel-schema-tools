<?php

namespace DVRTech\SchemaTools\Tests\Unit;

use DVRTech\SchemaTools\Services\SchemaAnalyzer;
use DVRTech\SchemaTools\DTO\ColumnStructureDTO;
use DVRTech\SchemaTools\Tests\TestDataHelper;
use PHPUnit\Framework\TestCase;

class SchemaAnalyzerTest extends TestCase
{
    private SchemaAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new SchemaAnalyzer();
    }

    public function testAnalyzeSimpleArray()
    {
        $data = [
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 30],
        ];

        $structure = $this->analyzer->analyzeDataStructure($data);

        $this->assertCount(2, $structure);
        $this->assertInstanceOf(ColumnStructureDTO::class, $structure['name']);
        $this->assertInstanceOf(ColumnStructureDTO::class, $structure['age']);

        $this->assertEquals('varchar', $structure['name']->type);
        $this->assertEquals('int', $structure['age']->type);
    }

    public function testAnalyzeMixedTypes()
    {
        $data = [
            ['version' => 1],
            ['version' => '1.0.0'],
            ['version' => 1.5],
        ];

        $structure = $this->analyzer->analyzeDataStructure($data);

        $this->assertEquals('varchar', $structure['version']->type);
    }

    public function testAnalyzeJsonData()
    {
        $data = [
            [
                'name' => 'Test',
                'metadata' => ['key' => 'value'],
                'price' => 19.99,
                'created_at' => '2023-01-01'
            ],
            [
                'name' => 'Test',
                'metadata' => ['key' => 'value'],
                'price' => "19.99",
                'created_at' => '2023-01-01'
            ]
        ];

        $structure = $this->analyzer->analyzeDataStructure($data);

        $this->assertEquals('varchar', $structure['name']->type);
        $this->assertEquals('json', $structure['metadata']->type);
        $this->assertEquals('decimal', $structure['price']->type); // Updated: price fields are now detected as decimal
        $this->assertEquals('date', $structure['created_at']->type);
    }

    public function testDetermineElementType()
    {
        $this->assertEquals('int', $this->analyzer->determineElementType(42)['type']);
        $this->assertEquals('float', $this->analyzer->determineElementType(3.14)['type']);
        $this->assertEquals('varchar', $this->analyzer->determineElementType('hello')['type']);
        $this->assertEquals('text', $this->analyzer->determineElementType(str_repeat('a', 300))['type']);
        $this->assertEquals('json', $this->analyzer->determineElementType(['key' => 'value'])['type']);
        $this->assertEquals('date', $this->analyzer->determineElementType('2023-01-01')['type']);
    }

    public function testAnalyzeDataWithColumnsInLaterRows()
    {
        $data = [
            ['name' => 'John', 'age' => 25],
            ['name' => 'Jane', 'age' => 30, 'email' => 'jane@example.com'],
            ['name' => 'Bob', 'age' => 35, 'email' => 'bob@example.com', 'city' => 'New York'],
        ];

        $structure = $this->analyzer->analyzeDataStructure($data);

        $this->assertCount(4, $structure);
        $this->assertArrayHasKey('name', $structure);
        $this->assertArrayHasKey('age', $structure);
        $this->assertArrayHasKey('email', $structure);
        $this->assertArrayHasKey('city', $structure);

        $this->assertEquals('varchar', $structure['name']->type);
        $this->assertEquals('int', $structure['age']->type);
        $this->assertEquals('varchar', $structure['email']->type);
        $this->assertEquals('varchar', $structure['city']->type);
    }

    public function testJson1()
    {
        $data = $this->useTestDataJson('test-json-1.json');
        $structure = $this->analyzer->analyzeDataStructure($data);

        // Should detect all 10 columns from all rows, not just the first row
        $this->assertCount(10, $structure);

        // Test the core fields that appear in all rows
        $this->assertEquals('varchar', $structure['name']->type);
        $this->assertEquals('json', $structure['author']->type);
        $this->assertEquals('varchar', $structure['version']->type); // Mixed types should be varchar
        $this->assertEquals('text', $structure['description']->type);

        // Test additional fields that appear in later rows
        $this->assertEquals('int', $structure['integer_value']->type);
        $this->assertEquals('float', $structure['float_value']->type);
        $this->assertEquals('int', $structure['boolean_value']->type); // Updated: booleans are now treated as integers
        $this->assertEquals('varchar', $structure['null_value']->type); // Updated: when all values are null, defaults to varchar
        $this->assertEquals('json', $structure['array_value']->type);
        $this->assertEquals('json', $structure['object_value']->type);
    }

    public function testDynamicVarcharLength()
    {
        // Test minimum length enforcement (50)
        $shortData = [
            ['name' => 'Jo'],
            ['name' => 'Bob'],
            ['name' => 'Sue']
        ];

        $result = $this->analyzer->analyzeDataStructure($shortData);
        $this->assertEquals('varchar', $result['name']->type);
        $this->assertEquals(50, $result['name']->length); // Should use minimum 50

        // Test actual length tracking
        $mediumData = [
            ['description' => 'This is a longer description with more content'],
            ['description' => 'Short'],
            ['description' => 'This description has exactly sixty characters in total here!']
        ];

        $result2 = $this->analyzer->analyzeDataStructure($mediumData);
        $this->assertEquals('varchar', $result2['description']->type);
        $this->assertEquals(60, $result2['description']->length); // Should match actual max length

        // Test text promotion for long strings
        $longString = str_repeat('This is a very long string. ', 10); // >255 chars
        $longData = [
            ['content' => $longString],
            ['content' => 'Short content']
        ];

        $result3 = $this->analyzer->analyzeDataStructure($longData);
        $this->assertEquals('text', $result3['content']->type);
        $this->assertNull($result3['content']->length); // TEXT doesn't have length
    }

    private function useTestDataJson(string $fileName): array
    {
        $filePath = TestDataHelper::getTestFilePath($fileName);
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Test data file not found: " . $filePath);
        }
        return json_decode(file_get_contents($filePath), true);
    }
}

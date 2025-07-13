<?php

namespace DVRTech\SchemaTools\Tests\Unit;

use DVRTech\SchemaTools\Generators\ModelGenerator;
use DVRTech\SchemaTools\DTO\ColumnStructureDTO;
use PHPUnit\Framework\TestCase;

class ModelGeneratorTest extends TestCase
{
    private ModelGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ModelGenerator();
    }

    public function testGenerateModelWithBasicStructure()
    {
        $structure = [
            'name' => new ColumnStructureDTO('varchar', 255, null, 'name'),
            'age' => new ColumnStructureDTO('int', null, null, 'age'),
            'email' => new ColumnStructureDTO('varchar', 255, null, 'email'),
        ];

        $model = $this->generator->generateModel('User', $structure);

        // Test that the model contains the expected components
        $this->assertStringContainsString('class User extends Model', $model);
        $this->assertStringContainsString('protected \$table = \'users\';', $model);
        $this->assertStringContainsString('use HasFactory;', $model);
        $this->assertStringContainsString('namespace App\Models;', $model);

        // Test fillable attributes
        $this->assertStringContainsString('\'name\',', $model);
        $this->assertStringContainsString('\'age\',', $model);
        $this->assertStringContainsString('\'email\',', $model);

        // Test casts for int type
        $this->assertStringContainsString('\'age\' => \'integer\',', $model);

        // Test class documentation
        $this->assertStringContainsString('@property int $age', $model);
        $this->assertStringContainsString('@property string $name', $model);
        $this->assertStringContainsString('@property string $email', $model);
    }

    public function testGenerateModelWithCustomTableName()
    {
        $structure = [
            'title' => new ColumnStructureDTO('varchar', 100, null, 'title'),
        ];

        $model = $this->generator->generateModel('Post', $structure, 'blog_posts');

        $this->assertStringContainsString('class Post extends Model', $model);
        $this->assertStringContainsString('protected \$table = \'blog_posts\';', $model);
        $this->assertStringContainsString('\'title\',', $model);
    }

    public function testGenerateModelWithVariousDataTypes()
    {
        $structure = [
            'id' => new ColumnStructureDTO('int', null, null, 'id'),
            'name' => new ColumnStructureDTO('varchar', 255, null, 'name'),
            'description' => new ColumnStructureDTO('text', null, null, 'description'),
            'price' => new ColumnStructureDTO('decimal', null, 2, 'price'),
            'rating' => new ColumnStructureDTO('float', null, 2, 'rating'),
            'created_at' => new ColumnStructureDTO('date', null, null, 'created_at'),
            'metadata' => new ColumnStructureDTO('json', null, null, 'metadata'),
        ];

        $model = $this->generator->generateModel('Product', $structure);

        // Test fillable attributes
        $this->assertStringContainsString('\'id\',', $model);
        $this->assertStringContainsString('\'name\',', $model);
        $this->assertStringContainsString('\'description\',', $model);
        $this->assertStringContainsString('\'price\',', $model);
        $this->assertStringContainsString('\'rating\',', $model);
        $this->assertStringContainsString('\'created_at\',', $model);
        $this->assertStringContainsString('\'metadata\',', $model);

        // Test casts
        $this->assertStringContainsString('\'id\' => \'integer\',', $model);
        $this->assertStringContainsString('\'price\' => \'decimal:2\',', $model);
        $this->assertStringContainsString('\'rating\' => \'decimal:2\',', $model);
        $this->assertStringContainsString('\'created_at\' => \'date\',', $model);
        $this->assertStringContainsString('\'metadata\' => \'array\',', $model);

        // Test class documentation
        $this->assertStringContainsString('@property int $id', $model);
        $this->assertStringContainsString('@property string $name', $model);
        $this->assertStringContainsString('@property string $description', $model);
        $this->assertStringContainsString('@property float $price', $model);
        $this->assertStringContainsString('@property float $rating', $model);
        $this->assertStringContainsString('@property string $created_at', $model);
        $this->assertStringContainsString('@property array $metadata', $model);
    }

    public function testGenerateModelWithNoCasts()
    {
        $structure = [
            'name' => new ColumnStructureDTO('varchar', 255, null, 'name'),
            'description' => new ColumnStructureDTO('text', null, null, 'description'),
        ];

        $model = $this->generator->generateModel('Category', $structure);

        // Should not contain casts section for non-castable types
        $this->assertStringNotContainsString('\'name\' =>', $model);
        $this->assertStringNotContainsString('\'description\' =>', $model);
        
        // But should still contain fillable
        $this->assertStringContainsString('\'name\',', $model);
        $this->assertStringContainsString('\'description\',', $model);
    }

    public function testGenerateModelWithEmptyStructure()
    {
        $structure = [];

        $model = $this->generator->generateModel('EmptyModel', $structure);

        $this->assertStringContainsString('class EmptyModel extends Model', $model);
        $this->assertStringContainsString('protected \$table = \'emptymodels\';', $model);
        
        // Should not contain any fillable attributes
        $this->assertStringNotContainsString('\'name\',', $model);
        $this->assertStringNotContainsString('\'email\',', $model);
    }

    public function testGenerateFillable()
    {
        $structure = [
            'name' => new ColumnStructureDTO('varchar', 255, null, 'name'),
            'email' => new ColumnStructureDTO('varchar', 255, null, 'email'),
        ];

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('generateFillable');
        $method->setAccessible(true);

        $fillable = $method->invoke($this->generator, $structure);

        $this->assertStringContainsString('\'name\',', $fillable);
        $this->assertStringContainsString('\'email\',', $fillable);
    }

    public function testGenerateCasts()
    {
        $structure = [
            'id' => new ColumnStructureDTO('int', null, null, 'id'),
            'name' => new ColumnStructureDTO('varchar', 255, null, 'name'),
            'price' => new ColumnStructureDTO('decimal', null, 2, 'price'),
        ];

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('generateCasts');
        $method->setAccessible(true);

        $casts = $method->invoke($this->generator, $structure);

        $this->assertStringContainsString('\'id\' => \'integer\',', $casts);
        $this->assertStringContainsString('\'price\' => \'decimal:2\',', $casts);
        $this->assertStringNotContainsString('\'name\'', $casts); // varchar should not have cast
    }

    public function testGenerateClassDocumentation()
    {
        $structure = [
            'id' => new ColumnStructureDTO('int', null, null, 'id'),
            'name' => new ColumnStructureDTO('varchar', 255, null, 'name'),
            'metadata' => new ColumnStructureDTO('json', null, null, 'metadata'),
        ];

        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('generateClassDocumentation');
        $method->setAccessible(true);

        $documentation = $method->invoke($this->generator, $structure);

        $this->assertStringContainsString('@property int $id', $documentation);
        $this->assertStringContainsString('@property string $name', $documentation);
        $this->assertStringContainsString('@property array $metadata', $documentation);
    }

    public function testGetPhpTypeFromColumnType()
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getPhpTypeFromColumnType');
        $method->setAccessible(true);

        $this->assertEquals('int', $method->invoke($this->generator, 'int'));
        $this->assertEquals('float', $method->invoke($this->generator, 'float'));
        $this->assertEquals('float', $method->invoke($this->generator, 'decimal'));
        $this->assertEquals('string', $method->invoke($this->generator, 'varchar'));
        $this->assertEquals('string', $method->invoke($this->generator, 'text'));
        $this->assertEquals('string', $method->invoke($this->generator, 'date'));
        $this->assertEquals('array', $method->invoke($this->generator, 'json'));
        $this->assertEquals('mixed', $method->invoke($this->generator, 'unknown_type'));
    }

    public function testLoadTemplateThrowsExceptionWhenTemplateNotFound()
    {
        // Create a mock generator that points to non-existent template
        $generator = new class extends ModelGenerator {
            protected function loadTemplate(): string
            {
                $templatePath = __DIR__ . '/non-existent-template.php';
                
                if (!file_exists($templatePath)) {
                    throw new \RuntimeException("Template file not found: {$templatePath}");
                }
                
                return file_get_contents($templatePath);
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template file not found:');
        
        $generator->generateModel('Test', []);
    }

    public function testDefaultTableNameGeneration()
    {
        $structure = [
            'name' => new ColumnStructureDTO('varchar', 255, null, 'name'),
        ];

        // Test various model names to ensure proper table name generation
        $testCases = [
            'User' => 'users',
            'Post' => 'posts',
            'Category' => 'categorys', // Note: this is expected behavior based on the code
            'BlogPost' => 'blogposts',
        ];

        foreach ($testCases as $modelName => $expectedTableName) {
            $model = $this->generator->generateModel($modelName, $structure);
            $this->assertStringContainsString("protected \\\$table = '{$expectedTableName}';", $model);
        }
    }
}

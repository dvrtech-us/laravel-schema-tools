<?php

require_once __DIR__ . '/vendor/autoload.php';

use DVRTech\SchemaTools\Services\SchemaAnalyzer;

// Test script to verify dynamic varchar length calculation
$analyzer = new SchemaAnalyzer();

echo "Testing dynamic varchar length calculation:\n\n";

// Test 1: Short strings should use minimum length (50)
$data1 = [
    ['name' => 'Jo'],
    ['name' => 'Jane'],
    ['name' => 'Bob']
];

$result1 = $analyzer->analyzeDataStructure($data1);
echo "Test 1 - Short strings (max 4 chars):\n";
echo "Type: {$result1['name']->type}, Length: {$result1['name']->length}\n";
echo "Expected: varchar, 50 (minimum)\n\n";

// Test 2: Medium strings should use actual max length
$data2 = [
    ['description' => 'This is a short description'],
    ['description' => 'This is a much longer description that contains more text'],
    ['description' => 'Medium length description here']
];

$result2 = $analyzer->analyzeDataStructure($data2);
echo "Test 2 - Medium strings (max ~55 chars):\n";
echo "Type: {$result2['description']->type}, Length: {$result2['description']->length}\n";
echo "Expected: varchar, ~55\n\n";

// Test 3: Long strings should become TEXT
$longString = str_repeat('This is a very long string that will exceed 255 characters. ', 5);
$data3 = [
    ['content' => $longString],
    ['content' => 'Short content']
];

$result3 = $analyzer->analyzeDataStructure($data3);
echo "Test 3 - Very long strings (>255 chars):\n";
echo "Type: {$result3['content']->type}, Length: " . ($result3['content']->length ?? 'null') . "\n";
echo "Expected: text, null\n\n";

// Test 4: Edge case - exactly 255 characters
$exactly255 = str_repeat('a', 255);
$data4 = [
    ['field' => $exactly255],
    ['field' => 'short']
];

$result4 = $analyzer->analyzeDataStructure($data4);
echo "Test 4 - Exactly 255 characters:\n";
echo "Type: {$result4['field']->type}, Length: {$result4['field']->length}\n";
echo "Expected: varchar, 255\n\n";

echo "All tests completed!\n";

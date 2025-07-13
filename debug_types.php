<?php
require_once 'vendor/autoload.php';

use DVRTech\SchemaTools\Services\SchemaAnalyzer;

$analyzer = new SchemaAnalyzer();
$structure = $analyzer->analyzeJsonFile('tests/data/test-json-2.json');

echo "Field types detected:\n";
foreach ($structure as $field => $dto) {
    if (in_array($field, ['createDate', 'isApproved', 'zCustomQuoteBool1', 'grossMargin', 'quoteTotal'])) {
        echo "$field: {$dto->type}\n";
    }
}

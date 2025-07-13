<?php
require_once 'vendor/autoload.php';

$json = file_get_contents('tests/data/test-json-2.json');
$data = json_decode($json, true);

echo "Boolean field values:\n";
echo "isApproved: " . var_export($data['isApproved'], true) . " (type: " . gettype($data['isApproved']) . ")\n";
echo "zCustomQuoteBool1: " . var_export($data['zCustomQuoteBool1'], true) . " (type: " . gettype($data['zCustomQuoteBool1']) . ")\n";
echo "includeZeroSuggestedInDiscount: " . var_export($data['includeZeroSuggestedInDiscount'], true) . " (type: " . gettype($data['includeZeroSuggestedInDiscount']) . ")\n";

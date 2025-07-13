<?php

namespace DVRTech\SchemaTools\Tests;

/**
 * Helper class to provide test data file paths
 */
class TestDataHelper
{
    public static function getTestFilePath(string $filename): string
    {
        // Get the directory where this TestDataHelper.php file is located (tests/)
        // Then append Data directory and filename
        return __DIR__ . DIRECTORY_SEPARATOR . 'Data' . DIRECTORY_SEPARATOR . $filename;
    }
}

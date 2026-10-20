<?php

namespace DVRTech\SchemaTools\Tests;

use DVRTech\SchemaTools\SchemaToolsServiceProvider;
use Orchestra\Testbench\TestCase;

abstract class PackageTestCase extends TestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [SchemaToolsServiceProvider::class];
    }
}

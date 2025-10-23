<?php

namespace LaraUtilX\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use LaraUtilX\LaraUtilXServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up any additional test configuration here
        $this->loadLaravelMigrations();
    }

    protected function getPackageProviders($app)
    {
        return [
            LaraUtilXServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup cache to use array driver
        $app['config']->set('cache.default', 'array');
        
        // Setup queue to use sync driver
        $app['config']->set('queue.default', 'sync');
    }
}

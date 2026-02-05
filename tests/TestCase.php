<?php

declare(strict_types=1);

namespace Dwb\DbExport\Tests;

use Dwb\DbExport\DbExportServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            DbExportServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'DbExport' => \Dwb\DbExport\Facades\DbExport::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}

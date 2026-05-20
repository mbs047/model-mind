<?php

namespace Mbs\ModelMind\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Mbs\ModelMind\ModelMindServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['view']->addNamespace('model-mind-test', __DIR__.'/Fixtures/views');
    }

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ModelMindServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'https://modelmind.test');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/knowledge/{entry}', fn (string $entry): string => $entry)->name('knowledge.show');
    }
}

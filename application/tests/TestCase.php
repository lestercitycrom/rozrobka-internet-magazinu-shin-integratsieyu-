<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fail closed before RefreshDatabase or any test helper can mutate a database.
     * Cached production configuration must never make PHPUnit target MySQL.
     */
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get('database.connections.sqlite.database');

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new \LogicException(sprintf(
                'Refusing to run tests against database.default=%s sqlite.database=%s. Tests require sqlite :memory:. Remove cached config and use a fresh test release.',
                (string) $connection,
                (string) $database,
            ));
        }

        return $app;
    }
}

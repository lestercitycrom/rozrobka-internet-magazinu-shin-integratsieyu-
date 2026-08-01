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

        $externalFlags = [
            'ndi_api.inquiry_enabled',
            'ndi_api.order_enabled',
            'ndi_api.c1_test_mode',
            'ndi_api.fulfilment_read_enabled',
            'payments.enabled',
            'outbox.notion_enabled',
            'outbox.telegram_enabled',
            'vehicle_fitment.enabled',
        ];

        foreach ($externalFlags as $flag) {
            if ($app['config']->get($flag, false)) {
                throw new \LogicException("Refusing to run tests while external integration {$flag} is enabled.");
            }
        }

        return $app;
    }
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        if (!$app->environment('testing')) {
            return $app;
        }

        $connectionName = $app['config']->get('database.default');
        $connection = $app['config']->get("database.connections.{$connectionName}");

        if (
            $connectionName !== 'mysql_testing'
            || ($connection['driver'] ?? null) !== 'mysql'
            || ($connection['database'] ?? null) !== 'careerly_test'
            || getenv('TEST_DB_HOST') === false
            || trim((string) getenv('TEST_DB_HOST')) === ''
            || getenv('TEST_DB_USERNAME') === false
            || trim((string) getenv('TEST_DB_USERNAME')) === ''
            || !in_array(getenv('TEST_DB_URL'), [false, ''], true)
        ) {
            throw new RuntimeException(sprintf(
                'Unsafe test DB config (connection=%s, driver=%s, database=%s, test host set=%s, test user set=%s, test URL empty=%s); refusing to run migrations.',
                $connectionName,
                $connection['driver'] ?? 'missing',
                $connection['database'] ?? 'missing',
                getenv('TEST_DB_HOST') !== false ? 'yes' : 'no',
                getenv('TEST_DB_USERNAME') !== false ? 'yes' : 'no',
                in_array(getenv('TEST_DB_URL'), [false, ''], true) ? 'yes' : 'no'
            ));
        }

        $actualDatabase = $app['db']
            ->connection($connectionName)
            ->selectOne('SELECT DATABASE() AS database_name')
            ->database_name;

        if ($actualDatabase !== 'careerly_test') {
            throw new RuntimeException(
                'The active test connection is not careerly_test; refusing to run migrations.'
            );
        }

        return $app;
    }
}

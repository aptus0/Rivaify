<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Docker Compose injects the production .env into one-off test
     * containers before PHPUnit starts. PHPUnit's <env force="true"> updates
     * the superglobals, but an already exported APP_ENV can still win while
     * Laravel bootstraps. Set the test-only process environment before the
     * application is created so CSRF, queue, cache and session behavior is
     * deterministic without weakening production middleware.
     */
    public function createApplication()
    {
        foreach ([
            'APP_ENV' => 'testing',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => '',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
        ] as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        return parent::createApplication();
    }
}

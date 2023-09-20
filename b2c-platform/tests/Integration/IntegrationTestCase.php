<?php

namespace Tests\Integration;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use RuntimeException;

abstract class IntegrationTestCase extends TestCase
{
    use DatabaseTransactions;

    /** @var bool */
    private static $migrated;

    /**
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $app->loadEnvironmentFrom('.env.testing');
        if (!file_exists($app->environmentFilePath())) {
            throw new RuntimeException(
                'Missing .env.testing environment file. This file is required to prevent accidental use of ' .
                'real development database.'
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        if (!$this->app) {
            $this->refreshApplication();
        }

        $env = $this->app->environment();
        if ($env !== 'testing') {
            throw new RuntimeException(sprintf('Expected environment to be testing, but found %s', $env));
        }
        if (!self::$migrated) {
            $this->artisan('migrate');
            self::$migrated = true;
        }

        parent::setUp();

        $this->withoutMix();
    }
}

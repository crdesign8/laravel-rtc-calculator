<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Tests;

use Crdesign8\LaravelRtcCalculator\RtcServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [RtcServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('rtc.base_url', 'http://localhost:8080');
        $app['config']->set('rtc.timeout', 5);
        $app['config']->set('rtc.retry_times', 1);
        $app['config']->set('rtc.retry_sleep_ms', 0);
        $app['config']->set('rtc.logging', [
            'enabled' => false,
            'channel' => 'stack',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace OpenTelemetryPHP74\Instrumentation\Laravel\Watchers;

use Illuminate\Contracts\Foundation\Application;

abstract class Watcher
{
    /**
     * Register the watcher.
     */
    abstract public function register(Application $app): void;
}

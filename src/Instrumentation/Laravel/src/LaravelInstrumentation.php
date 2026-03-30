<?php

declare(strict_types=1);

/**
 * Based on code from opentelemetry/opentelemetry-php-contrib
 * Copyright 2021 opentelemetry-php-contrib contributors
 * Licensed under the Apache License, Version 2.0
 * 
 * Modifications:
 * - Added support for PHP 7.4
 * - Updated to use OpenTelemetry extension for PHP 7.4
 */

namespace OpenTelemetryPHP74\Instrumentation\Laravel;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\SDK\Common\Configuration\Configuration;

class LaravelInstrumentation
{
    public const NAME = 'laravel';

    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation(
            'arthur1/opentelemetry-php74-auto-laravel',
            null
        );

        Hooks\Illuminate\Contracts\Http\Kernel::hook($instrumentation);
        Hooks\Illuminate\Database\Eloquent\Model::hook($instrumentation);
        Hooks\Illuminate\Foundation\Application::hook($instrumentation);
        Hooks\Illuminate\Foundation\Console\ServeCommand::hook($instrumentation);
    }

    public static function shouldTraceCli(): bool
    {
        return PHP_SAPI !== 'cli' || (
            class_exists(Configuration::class)
            && Configuration::getBoolean('OTEL_PHP_TRACE_CLI_ENABLED', false)
        );
    }
}

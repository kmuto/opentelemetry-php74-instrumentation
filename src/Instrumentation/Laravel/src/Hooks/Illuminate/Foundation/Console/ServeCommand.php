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

namespace OpenTelemetryPHP74\Instrumentation\Laravel\Hooks\Illuminate\Foundation\Console;

use Illuminate\Foundation\Console\ServeCommand as FoundationServeCommand;
use OpenTelemetryPHP74\Instrumentation\Laravel\Hooks\LaravelHook;
use OpenTelemetryPHP74\Instrumentation\Laravel\Hooks\LaravelHookTrait;
use function OpenTelemetryPHP74\Instrumentation\hook;

/**
 * Instrument Laravel's local PHP development server.
 */
class ServeCommand implements LaravelHook
{
    use LaravelHookTrait;

    public function instrument(): void
    {
        /** @psalm-suppress UnusedFunctionCall */
        hook(
            FoundationServeCommand::class,
            'handle',
            static function (FoundationServeCommand $_serveCommand, array $_params, string $_class, string $_function, ?string $_filename, ?int $_lineno) {
                if (!property_exists(FoundationServeCommand::class, 'passthroughVariables')) {
                    return;
                }

                foreach ($_ENV as $key => $_value) {
                    if (str_starts_with($key, 'OTEL_') && !in_array($key, FoundationServeCommand::$passthroughVariables)) {
                        FoundationServeCommand::$passthroughVariables[] = $key;
                    }
                }
            },
        );
    }
}

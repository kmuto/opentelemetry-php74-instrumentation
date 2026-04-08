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

namespace OpenTelemetryPHP74\Instrumentation\Laravel\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;

class QueryWatcher extends Watcher
{
    private CachedInstrumentation $instrumentation;

    public function __construct(
        CachedInstrumentation $instrumentation
    ) {
        $this->instrumentation = $instrumentation;
    }

    /** @psalm-suppress UndefinedInterfaceMethod */
    public function register(Application $app): void
    {
        /** @phan-suppress-next-line PhanTypeArraySuspicious */
        $app['events']->listen(QueryExecuted::class, [$this, 'recordQuery']);
    }

    /**
     * Record a query.
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function recordQuery(QueryExecuted $query): void
    {
        $nowInNs = (int) (microtime(true) * 1E9);

        $operationName = Str::upper(Str::before($query->sql, ' '));
        if (! in_array($operationName, ['SELECT', 'INSERT', 'UPDATE', 'DELETE'])) {
            $operationName = null;
        }
        /** @psalm-suppress ArgumentTypeCoercion */
        $span = $this->instrumentation->tracer()->spanBuilder('sql ' . $operationName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setStartTimestamp($this->calculateQueryStartTime($nowInNs, $query->time))
            ->startSpan();

        $attributes = [
            'db.system.name' => $query->connection->getDriverName(),
            'db.namespace' => $query->connection->getDatabaseName(),
            'db.operation.name' => $operationName,
            //TraceAttributes::DB_USER => $query->connection->getConfig('username'),
        ];

        $attributes['db.query.text'] = $query->sql;
        /** @psalm-suppress PossiblyInvalidArgument */
        $span->setAttributes($attributes);
        $span->end($nowInNs);
    }

    private function calculateQueryStartTime(int $nowInNs, float $queryTimeMs): int
    {
        return (int) ($nowInNs - ($queryTimeMs * 1E6));
    }
}

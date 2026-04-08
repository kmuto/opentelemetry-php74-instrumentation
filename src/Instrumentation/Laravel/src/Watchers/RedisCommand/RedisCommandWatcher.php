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

namespace OpenTelemetryPHP74\Instrumentation\Laravel\Watchers\RedisCommand;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Redis\Events\CommandExecuted;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetryPHP74\Instrumentation\Laravel\Watchers\Watcher;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\TraceAttributeValues;
use Throwable;

/**
 * Watch the Redis Command event
 *
 * Call facade `Redis::enableEvents()` before using this watcher
 */
class RedisCommandWatcher extends Watcher
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
        $app['events']->listen(CommandExecuted::class, [$this, 'recordRedisCommand']);
    }

    /**
     * Record a Redis command.
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function recordRedisCommand(CommandExecuted $event): void
    {
        $nowInNs = (int) (microtime(true) * 1E9);

        $operationName = strtoupper($event->command);

        /** @psalm-suppress ArgumentTypeCoercion */
        $span = $this->instrumentation->tracer()
            ->spanBuilder($operationName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setStartTimestamp($this->calculateQueryStartTime($nowInNs, $event->time))
            ->startSpan();

        // See https://opentelemetry.io/docs/specs/semconv/database/redis/
        $attributes = [
            'db.system.name' => TraceAttributeValues::DB_SYSTEM_REDIS,
            'db.namespace' => $this->fetchDbIndex($event->connection),
            'db.operation.name' => $operationName,
            'db.query.text' => Serializer::serializeCommand($event->command, $event->parameters),
            TraceAttributes::SERVER_ADDRESS => $this->fetchDbHost($event->connection),
        ];

        /** @psalm-suppress PossiblyInvalidArgument */
        $span->setAttributes($attributes);
        $span->end($nowInNs);
    }

    private function calculateQueryStartTime(int $nowInNs, float $queryTimeMs): int
    {
        return (int) ($nowInNs - ($queryTimeMs * 1E6));
    }

    private function fetchDbIndex(Connection $connection): ?int
    {
        try {
            if ($connection instanceof PhpRedisConnection) {
                return $connection->client()->getDbNum();
            } elseif ($connection instanceof PredisConnection) {
                /** @psalm-suppress PossiblyUndefinedMethod */
                return $connection->client()->getConnection()->getParameters()->database;
            }

            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function fetchDbHost(Connection $connection): ?string
    {
        try {
            if ($connection instanceof PhpRedisConnection) {
                return $connection->client()->getHost();
            } elseif ($connection instanceof PredisConnection) {
                /** @psalm-suppress PossiblyUndefinedMethod */
                return $connection->client()->getConnection()->getParameters()->host;
            }

            return null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

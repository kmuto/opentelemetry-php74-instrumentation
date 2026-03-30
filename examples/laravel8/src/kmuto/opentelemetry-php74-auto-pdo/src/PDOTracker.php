<?php

declare(strict_types=1);

namespace OpenTelemetryPHP74\Instrumentation\PDO;

use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use PDO;
use PDOStatement;
use WeakReference;

/**
 * @phan-file-suppress PhanNonClassMethodCall,PhanTypeArraySuspicious
 */
final class PDOTracker
{
    /**
     * @var array<string, array<non-empty-string, bool|int|float|string|array|null>>
     */
    private array $pdoToAttributesMap = [];
    /**
     * @var array<string, WeakReference<PDO>>
     */
    private array $statementMapToPdoMap = [];
    /**
     * @var array<string, WeakReference<SpanContextInterface>>
     */
    private array $preparedStatementToSpanMap = [];

    public function __construct()
    {
        // PHP 7.4 では WeakMap がないため配列で初期化（宣言時に初期化済み）
    }

    /**
     * Maps a prepared statement to the PDO instance and the span context it was created in
     */
    public function trackStatement(PDOStatement $statement, PDO $pdo, SpanContextInterface $spanContext): void
    {
        $hash = spl_object_hash($statement);
        $this->statementMapToPdoMap[$hash] = WeakReference::create($pdo);
        $this->preparedStatementToSpanMap[$hash] = WeakReference::create($spanContext);
    }

    /**
     * Maps a statement back to the connection attributes.
     *
     * @param PDOStatement $statement
     * @return array<non-empty-string, bool|int|float|string|array|null>
     */
    public function trackedAttributesForStatement(PDOStatement $statement): array
    {
        $hash = spl_object_hash($statement);
        $tracker = $this->statementMapToPdoMap[$hash] ?? null;
        $pdo = $tracker ? $tracker->get() : null;
        if ($pdo === null) {
            return [];
        }

        /** @psalm-var array<non-empty-string, bool|int|float|string|array|null> */
        return $this->pdoToAttributesMap[spl_object_hash($pdo)] ?? [];
    }

    /**
     * @param PDO $pdo
     * @param string $dsn
     * @return array<non-empty-string, bool|int|float|string|array|null>
     */
    public function trackPdoAttributes(PDO $pdo, string $dsn): array
    {
        $attributes = self::extractAttributesFromDSN($dsn);

        try {
            /** @var string $dbSystem */
            $dbSystem = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            /** @psalm-suppress InvalidArrayAssignment */
            $attributes[TraceAttributes::DB_SYSTEM_NAME] = self::mapDriverNameToAttribute($dbSystem);
        } catch (\Error $e) {
            // if we caught an exception, the driver is likely not supporting the operation, default to "other"
            /** @psalm-suppress PossiblyInvalidArrayAssignment */
            $attributes[TraceAttributes::DB_SYSTEM_NAME] = 'other_sql';
        }

        $this->pdoToAttributesMap[spl_object_hash($pdo)] = $attributes;

        return $attributes;
    }

    /**
     * @param PDO $pdo
     * @return array<non-empty-string, bool|int|float|string|array|null>
     */
    public function trackedAttributesForPdo(PDO $pdo): array
    {
        /** @psalm-var array<non-empty-string, bool|int|float|string|array|null> */
        return $this->pdoToAttributesMap[spl_object_hash($pdo)] ?? [];
    }

    public function getSpanForPreparedStatement(PDOStatement $statement): ?SpanContextInterface
    {
        $hash = spl_object_hash($statement);
        if (!isset($this->preparedStatementToSpanMap[$hash])) {
            return null;
        }

        $span = $this->preparedStatementToSpanMap[$hash] ?? null;
        return $span ? $span->get() : null;
    }

    /**
     * Mapping to known values
     */
    private static function mapDriverNameToAttribute(?string $driverName): string
    {
        $map = [
            'mysql'  => 'mysql',
            'pgsql'  => 'postgresql',
            'sqlite' => 'sqlite',
            'sqlsrv' => 'mssql',
            'oci'    => 'oracle',
            'ibm'    => 'db2',
        ];

        return $map[$driverName] ?? 'other_sql';
    }

    /**
     * Extracts attributes from a DSN string
     */
    private static function extractAttributesFromDSN(string $dsn): array
    {
        $attributes = [];
        // str_starts_with -> strpos === 0
        if (strpos($dsn, 'sqlite::memory:') === 0) {
            $attributes[TraceAttributes::DB_SYSTEM_NAME] = 'sqlite';
            $attributes[TraceAttributes::DB_NAMESPACE] = 'memory';

            return $attributes;
        } elseif (strpos($dsn, 'sqlite:') === 0) {
            $attributes[TraceAttributes::DB_SYSTEM_NAME] = 'sqlite';
            $attributes[TraceAttributes::DB_NAMESPACE] = substr($dsn, 7);

            return $attributes;
        } elseif (strpos($dsn, 'sqlite') === 0) {
            $attributes[TraceAttributes::DB_SYSTEM_NAME] = 'sqlite';
            $attributes[TraceAttributes::DB_NAMESPACE] = $dsn;

            return $attributes;
        }

        // SQL Server format handling
        if (strpos($dsn, 'sqlsrv:') === 0) {
            if (preg_match('/Server=([^,;]+)(?:,([0-9]+))?/', $dsn, $serverMatches)) {
                $server = $serverMatches[1];
                if ($server !== '') {
                    $attributes[TraceAttributes::SERVER_ADDRESS] = $server;
                }

                if (isset($serverMatches[2]) && $serverMatches[2] !== '') {
                    $attributes[TraceAttributes::SERVER_PORT] = (int) $serverMatches[2];
                }
            }

            if (preg_match('/Database=([^;]*)/', $dsn, $dbMatches)) {
                $dbname = $dbMatches[1];
                if ($dbname !== '') {
                    $attributes[TraceAttributes::DB_NAMESPACE] = $dbname;
                }
            }

            return $attributes;
        }

        // Extract host information
        if (preg_match('/host=([^;]*)/', $dsn, $matches)) {
            $host = $matches[1];
            if ($host !== '') {
                $attributes[TraceAttributes::SERVER_ADDRESS] = $host;
            }
        } elseif (preg_match('/mysql:([^;:]+)/', $dsn, $hostMatches)) {
            $host = $hostMatches[1];
            if ($host !== '' && $host !== 'dbname') {
                $attributes[TraceAttributes::SERVER_ADDRESS] = $host;
            }
        }

        // Extract port information
        if (preg_match('/port=([0-9]+)/', $dsn, $portMatches)) {
            $port = (int) $portMatches[1];
            $attributes[TraceAttributes::SERVER_PORT] = $port;
        } elseif (preg_match('/[.0-9]+:([0-9]+)/', $dsn, $portMatches)) {
            $port = (int) $portMatches[1];
            $attributes[TraceAttributes::SERVER_PORT] = $port;
        } elseif (preg_match('/:([0-9]+)/', $dsn, $portMatches)) {
            $port = (int) $portMatches[1];
            $attributes[TraceAttributes::SERVER_PORT] = $port;
        }

        // Extract database name
        if (preg_match('/dbname=([^;]*)/', $dsn, $matches)) {
            $dbname = $matches[1];
            if ($dbname !== '') {
                $attributes[TraceAttributes::DB_NAMESPACE] = $dbname;
            }
        }

        return $attributes;
    }
}

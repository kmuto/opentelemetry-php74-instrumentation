<?php

declare(strict_types=1);

namespace OpenTelemetryPHP74\Instrumentation\PDO;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use function OpenTelemetryPHP74\Instrumentation\hook;
use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\SemConv\Attributes\CodeAttributes;
use OpenTelemetry\SemConv\Attributes\DbAttributes;
use PDO;
use PDOStatement;
use Throwable;

class PDOInstrumentation
{
    public const NAME = 'pdo';
    private const UNDEFINED = 'undefined';

    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation(
            'io.opentelemetry.contrib.php.pdo',
            null,
            'https://opentelemetry.io/schemas/1.36.0'
        );
        $pdoTracker = new PDOTracker();

        // PDO::connect (PHP 8.4+) 用のフック - 7.4では基本通らないが念のため修正
        if (method_exists(PDO::class, 'connect')) {
            hook(
                PDO::class,
                'connect',
                static function ($object, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                    $builder = self::makeBuilder($instrumentation, 'PDO::connect', $function, $class, $filename, $lineno)
                        ->setSpanKind(SpanKind::KIND_CLIENT);

                    $parent = Context::getCurrent();
                    $span = $builder->startSpan();
                    Context::storage()->attach($span->storeInContext($parent));
                },
                static function ($object, array $params, $result, ?Throwable $exception) use ($pdoTracker) {
                    $scope = Context::storage()->scope();
                    if (!$scope) {
                        return;
                    }
                    $span = Span::fromContext($scope->context());
                    $dsn = $params[0] ?? '';

                    if ($result instanceof PDO) {
                        $attributes = $pdoTracker->trackPdoAttributes($result, $dsn);
                        $span->setAttributes($attributes);
                    }

                    self::end($exception);
                }
            );
        }

        // --- PDO::__construct ---
        hook(
            PDO::class,
            '__construct',
            static function (PDO $pdo, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                $builder = self::makeBuilder($instrumentation, 'PDO::__construct', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            static function (PDO $pdo, array $params, $statement, ?Throwable $exception) use ($pdoTracker) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }
                $span = Span::fromContext($scope->context());
                $dsn = $params[0] ?? '';
                $attributes = $pdoTracker->trackPdoAttributes($pdo, $dsn);
                $span->setAttributes($attributes);

                self::end($exception);
            }
        );

        // --- PDO::query ---
        hook(
            PDO::class,
            'query',
            static function (PDO $pdo, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($pdoTracker, $instrumentation) {
                $builder = self::makeBuilder($instrumentation, 'PDO::query', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                $query = mb_convert_encoding($params[0] ?? self::UNDEFINED, 'UTF-8');
                if (!is_string($query)) {
                    $query = self::UNDEFINED;
                }
                if ($class === PDO::class) {
                    $builder->setAttribute(DbAttributes::DB_QUERY_TEXT, $query);
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();

                $attributes = $pdoTracker->trackedAttributesForPdo($pdo);
                $span->setAttributes($attributes);

                Context::storage()->attach($span->storeInContext($parent));
                return [];
            },
            static function (PDO $pdo, array $params, $statement, ?Throwable $exception) {
                self::end($exception);
            }
        );

        // --- PDO::exec ---
        hook(
            PDO::class,
            'exec',
            static function (PDO $pdo, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($pdoTracker, $instrumentation) {
                $builder = self::makeBuilder($instrumentation, 'PDO::exec', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                $query = mb_convert_encoding($params[0] ?? self::UNDEFINED, 'UTF-8');
                if (!is_string($query)) {
                    $query = self::UNDEFINED;
                }
                if ($class === PDO::class) {
                    $builder->setAttribute(DbAttributes::DB_QUERY_TEXT, $query);
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                $attributes = $pdoTracker->trackedAttributesForPdo($pdo);
                $span->setAttributes($attributes);

                Context::storage()->attach($span->storeInContext($parent));
                return [];
            },
            static function (PDO $pdo, array $params, $statement, ?Throwable $exception) {
                self::end($exception);
            }
        );

        // --- PDO::prepare ---
        hook(
            PDO::class,
            'prepare',
            static function (PDO $pdo, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($pdoTracker, $instrumentation) {
                $builder = self::makeBuilder($instrumentation, 'PDO::prepare', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                if ($class === PDO::class) {
$rawQuery = isset($params[0]) ? $params[0] : 'undefined';
$queryString = is_string($rawQuery) ? $rawQuery : '(non-string query)';

// 一旦、Semantic Conventionの定数を使わずに直接書く
$builder->setAttribute('db.query.text', $queryString);

//                    $builder->setAttribute(DbAttributes::DB_QUERY_TEXT, mb_convert_encoding($params[0] ?? 'undefined', 'UTF-8'));
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                $attributes = $pdoTracker->trackedAttributesForPdo($pdo);
                $span->setAttributes($attributes);

                // Context::storage()->attach($span->storeInContext($parent));
$context = $span->storeInContext($parent);
$scope = $context->activate();
            },
            static function (PDO $pdo, array $params, $statement, ?Throwable $exception) use ($pdoTracker) {
                if ($statement instanceof PDOStatement) {
                    $pdoTracker->trackStatement($statement, $pdo, Span::getCurrent()->getContext());
                }
                self::end($exception);
            }
        );

        // --- 以下のメソッド(beginTransaction, commit, rollBack, fetchAll, execute)も同様に mixed を削除して適用 ---
        $methods = ['beginTransaction', 'commit', 'rollBack'];
        foreach ($methods as $method) {
            hook(
                PDO::class,
                $method,
                static function (PDO $pdo, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($pdoTracker, $instrumentation, $method) {
                    $builder = self::makeBuilder($instrumentation, "PDO::$method", $function, $class, $filename, $lineno)
                        ->setSpanKind(SpanKind::KIND_CLIENT);
                    $parent = Context::getCurrent();
                    $span = $builder->startSpan();
                    $attributes = $pdoTracker->trackedAttributesForPdo($pdo);
                    $span->setAttributes($attributes);
                    Context::storage()->attach($span->storeInContext($parent));
                },
                static function (PDO $pdo, array $params, $statement, ?Throwable $exception) {
                    self::end($exception);
                }
            );
        }

        // --- PDOStatement::fetchAll ---
        hook(
            PDOStatement::class,
            'fetchAll',
            static function (PDOStatement $statement, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($pdoTracker, $instrumentation) {
                $attributes = $pdoTracker->trackedAttributesForStatement($statement);
                if (self::isDistributeStatementToLinkedSpansEnabled()) {
                    $attributes[DbAttributes::DB_QUERY_TEXT] = $statement->queryString;
                }
                $builder = self::makeBuilder($instrumentation, 'PDOStatement::fetchAll', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttributes($attributes);
                if ($spanContext = $pdoTracker->getSpanForPreparedStatement($statement)) {
                    $builder->addLink($spanContext);
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            static function (PDOStatement $statement, array $params, $retval, ?Throwable $exception) {
                self::end($exception);
            }
        );

        // --- PDOStatement::execute ---
        hook(
            PDOStatement::class,
            'execute',
            static function (PDOStatement $statement, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($pdoTracker, $instrumentation) {
                $attributes = $pdoTracker->trackedAttributesForStatement($statement);
                if (self::isDistributeStatementToLinkedSpansEnabled()) {
                    $attributes[DbAttributes::DB_QUERY_TEXT] = $statement->queryString;
                }
                $builder = self::makeBuilder($instrumentation, 'PDOStatement::execute', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttributes($attributes);
                if ($spanContext = $pdoTracker->getSpanForPreparedStatement($statement)) {
                    $builder->addLink($spanContext);
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            static function (PDOStatement $statement, array $params, $retval, ?Throwable $exception) {
                self::end($exception);
            }
        );
    }

    private static function makeBuilder(CachedInstrumentation $instrumentation, string $name, string $function, string $class, ?string $filename, ?int $lineno): SpanBuilderInterface {
    $builder = $instrumentation->tracer()->spanBuilder($name);
    $builder->setAttribute('code.function', $class . '::' . $function);
    $builder->setAttribute('code.filepath', $filename);
    $builder->setAttribute('code.lineno', $lineno);
    return $builder;
    }

    private static function end(?Throwable $exception): void
    {
        $scope = Context::storage()->scope();
        if (!$scope) {
            return;
        }
        $scope->detach();
        $span = Span::fromContext($scope->context());
        if ($exception) {
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        }
        $span->end();
    }

    private static function isDistributeStatementToLinkedSpansEnabled(): bool
    {
        if (class_exists('OpenTelemetry\SDK\Common\Configuration\Configuration')) {
            return Configuration::getBoolean('OTEL_PHP_INSTRUMENTATION_PDO_DISTRIBUTE_STATEMENT_TO_LINKED_SPANS', false);
        }
        $val = get_cfg_var('otel.instrumentation.pdo.distribute_statement_to_linked_spans');
        return filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}

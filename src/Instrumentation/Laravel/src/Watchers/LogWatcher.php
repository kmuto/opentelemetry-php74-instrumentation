<?php

declare(strict_types=1);

namespace OpenTelemetryPHP74\Instrumentation\Laravel\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Log\LogManager;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\SDK\Common\Exception\StackTraceFormatter;
use Psr\Log\LogLevel;
use Throwable;
use TypeError;

class LogWatcher extends Watcher
{
    private LogManager $logger;
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
        $app['events']->listen(MessageLogged::class, [$this, 'recordLog']);

        /** @phan-suppress-next-line PhanTypeArraySuspicious */
        $this->logger = $app['log'];
    }

    /**
     * Record a log.
     * @phan-suppress PhanDeprecatedFunction
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function recordLog(MessageLogged $log): void
    {
        $underlyingLogger = $this->logger->getLogger();

        /**
         * This assumes that the underlying logger (expected to be monolog) would accept `$log->level` as a string.
         * With monolog < 3.x, this method would fail. Let's prevent this blowing up in Laravel<10.x.
         */
        try {
            /** @phan-suppress-next-line PhanUndeclaredMethod */
            if (method_exists($underlyingLogger, 'isHandling') && !$underlyingLogger->isHandling($log->level)) {
                return;
            }
        } catch (TypeError $e) {
            // Should this fail, we should continue to emit the LogRecord.
        }

        $contextToEncode = array_filter($log->context);

        $exception = $this->getExceptionFromContext($log->context);

        if ($exception !== null) {
            unset($contextToEncode['exception']);
        }

        $attributes = [
            'context' => json_encode($contextToEncode)
        ];
        if ($exception != null) {
          $attributes = array_merge($attributes, [
                'exception.type' => get_class($exception),
                'exception.message' => $exception->getMessage(),
                'exception.stacktrace' => StackTraceFormatter::format($exception)
          ]);
        }

        $severityMap = [
            LogLevel::DEBUG     => 5,  // DEBUG
            LogLevel::INFO      => 9,  // INFO
            LogLevel::NOTICE    => 10, // INFO2
            LogLevel::WARNING   => 13, // WARN
            LogLevel::ERROR     => 17, // ERROR
            LogLevel::CRITICAL  => 18, // ERROR2
            LogLevel::ALERT     => 19, // ERROR3
            LogLevel::EMERGENCY => 21, // FATAL
        ];

        $level = strtolower($log->level);
        $severityNumber = isset($severityMap[$level]) ? $severityMap[$level] : 9;

        $logger = $this->instrumentation->logger();

        $record = (new LogRecord($log->message))
            ->setSeverityText($log->level)
            ->setSeverityNumber($severityNumber)
            ->setAttributes($attributes);

        $logger->emit($record);
    }

    private function getExceptionFromContext(array $context): ?Throwable
    {
        if (
            ! isset($context['exception']) ||
            ! $context['exception'] instanceof Throwable
        ) {
            return null;
        }

        return $context['exception'];
    }
}

<?php

declare(strict_types=1);

namespace OpenTelemetryPHP74\Instrumentation\Laravel\Hooks\Illuminate\Foundation;

use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Foundation\Application as FoundationalApplication;
use OpenTelemetryPHP74\Instrumentation\Laravel\Hooks\LaravelHook;
use OpenTelemetryPHP74\Instrumentation\Laravel\Hooks\LaravelHookTrait;
use OpenTelemetryPHP74\Instrumentation\Laravel\Watchers\CacheWatcher;
use OpenTelemetryPHP74\Instrumentation\Laravel\Watchers\ClientRequestWatcher;
use OpenTelemetryPHP74\Instrumentation\Laravel\Watchers\ExceptionWatcher;
use OpenTelemetryPHP74\Instrumentation\Laravel\Watchers\LogWatcher;
use OpenTelemetryPHP74\Instrumentation\Laravel\Watchers\QueryWatcher;
use OpenTelemetryPHP74\Instrumentation\Laravel\Watchers\RedisCommand\RedisCommandWatcher;
use OpenTelemetryPHP74\Instrumentation\Laravel\Watchers\Watcher;
use function OpenTelemetryPHP74\Instrumentation\hook;
use Throwable;

class Application implements LaravelHook
{
    use LaravelHookTrait;

    public function instrument(): void
    {
        /** @psalm-suppress UnusedFunctionCall */
        hook(
            FoundationalApplication::class,
            '__construct',
            null,
            function (FoundationalApplication $application, array $_params, $_returnValue, ?Throwable $_exception) {
                $this->registerWatchers($application, new CacheWatcher());
                $this->registerWatchers($application, new ClientRequestWatcher($this->instrumentation));
                $this->registerWatchers($application, new ExceptionWatcher());
                $this->registerWatchers($application, new LogWatcher($this->instrumentation));
                $this->registerWatchers($application, new QueryWatcher($this->instrumentation));
                $this->registerWatchers($application, new RedisCommandWatcher($this->instrumentation));
            },
        );
    }

    private function registerWatchers(ApplicationContract $app, Watcher $watcher): void
    {
        $watcher->register($app);
    }
}

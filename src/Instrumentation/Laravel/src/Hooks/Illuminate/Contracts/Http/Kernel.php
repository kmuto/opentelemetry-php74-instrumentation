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

namespace OpenTelemetryPHP74\Instrumentation\Laravel\Hooks\Illuminate\Contracts\Http;

use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetryPHP74\Instrumentation\Laravel\Hooks\LaravelHook;
use OpenTelemetryPHP74\Instrumentation\Laravel\Hooks\LaravelHookTrait;
use OpenTelemetryPHP74\Instrumentation\Laravel\Hooks\PostHookTrait;
use OpenTelemetryPHP74\Instrumentation\Laravel\Propagators\HeadersPropagator;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function OpenTelemetryPHP74\Instrumentation\hook;

class Kernel implements LaravelHook
{
    use LaravelHookTrait;

    /** @var array<string, SpanInterface> */
    private array $activeHttpSpans = [];

    /** @var array<string, \OpenTelemetry\Context\ScopeInterface> */
    private array $activeHttpScopes = [];

    public function instrument(): void
    {
        $this->hookHandle();
    }

    /** @psalm-suppress PossiblyUnusedReturnValue  */
    protected function hookHandle(): bool
    {
        return hook(
            KernelContract::class,
            'handle',
            function (KernelContract $kernel, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
                $request = ($params[0] instanceof Request) ? $params[0] : null;
                $spanKey = $this->spanKey($kernel, $request);
                /** @psalm-suppress ArgumentTypeCoercion */
                $builder = $this->instrumentation
                    ->tracer()
                    ->spanBuilder($request !== null ? $request->method() : 'unknown')
                    ->setSpanKind(SpanKind::KIND_SERVER)
                    ->setAttribute('code.function.name', sprintf('%s::%s', $class, $function))
                    ->setAttribute('code.file.path', $filename)
                    ->setAttribute('code.line.number', $lineno);
                $parent = Context::getCurrent();
                if ($request) {
                    $parent = Globals::propagator()->extract($request, HeadersPropagator::instance());
                    $span = $builder
                        ->setParent($parent)
                        ->setAttribute('url.full', $request->fullUrl())
                        ->setAttribute('http.request.method', $request->method())
                        ->setAttribute('http.request.body.size', $request->header('Content-Length'))
                        ->setAttribute('network.protocol.version', $request->getProtocolVersion())
                        ->setAttribute('network.peer.address', $request->server('REMOTE_ADDR'))
                        ->setAttribute('url.scheme', $request->getScheme())
                        ->setAttribute('url.path', $this->httpTarget($request))
                        ->setAttribute('server.address', $this->httpHostName($request))
                        ->setAttribute('server.port', $request->getPort())
                        ->setAttribute('client.port', $request->server('REMOTE_PORT'))
                        ->setAttribute('client.address', $request->ip())
                        ->setAttribute('user_agent.original', $request->userAgent())
                        ->startSpan();
                } else {
                    $span = $builder->startSpan();
                }

                $scope = Context::storage()->attach($span->storeInContext($parent));
                $this->activeHttpSpans[$spanKey] = $span;
                $this->activeHttpScopes[$spanKey] = $scope;

                return [$request];
            },
            function (KernelContract $kernel, array $params, ?Response $response, ?Throwable $exception) {
                $request = ($params[0] instanceof Request) ? $params[0] : null;
                $spanKey = $this->spanKey($kernel, $request);

                $span = $this->activeHttpSpans[$spanKey] ?? null;
                $scope = $this->activeHttpScopes[$spanKey] ?? null;

                if (!$span || !$scope) {
                    return;
                }

                try {
                    $route = $request !== null ? $request->route() : null;

                    if ($request && $route instanceof Route) {
                        $span->updateName("{$request->method()} /" . ltrim($route->uri, '/'));
                        $span->setAttribute('http.route', $route->uri);
                    }

                    if ($response) {
                        if ($response->getStatusCode() >= 500) {
                            $span->setStatus(StatusCode::STATUS_ERROR);
                        }
                        $span->setAttribute('http.response.status_code', $response->getStatusCode());
                        $span->setAttribute('network.protocol.version', $response->getProtocolVersion());
                        $span->setAttribute('http.response.body.size', $response->headers->get('Content-Length'));

                        // $prop = Globals::responsePropagator();
                        //** @phan-suppress-next-line PhanAccessMethodInternal */
                        // $prop->inject($response, ResponsePropagationSetter::instance(), $scope->context());
                    }

                    if ($exception) {
                        $span->recordException($exception);
                        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                    }

                    $span->end();
                } finally {
                    $scope->detach();
                    unset($this->activeHttpSpans[$spanKey], $this->activeHttpScopes[$spanKey]);
                }
            }
        );
    }

    private function spanKey(KernelContract $kernel, ?Request $request): string
    {
        if ($request !== null) {
            return 'request:' . spl_object_id($request);
        }

        return 'kernel:' . spl_object_id($kernel);
    }

    private function httpTarget(Request $request): string
    {
        $query = $request->getQueryString();
        $base = $request->getBaseUrl() . $request->getPathInfo();
        $question = $base === '/' ? '/?' : '?';

        return $query ? $request->path() . $question . $query : $request->path();
    }

    private function httpHostName(Request $request): string
    {
        if (method_exists($request, 'host')) {
            return $request->host();
        }

        if (method_exists($request, 'getHost')) {
            return $request->getHost();
        }

        return '';
    }
}

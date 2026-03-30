<?php
declare(strict_types=1);

use OpenTelemetryPHP74\Instrumentation\PDO\PDOInstrumentation;
use OpenTelemetry\SDK\Sdk;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(PDOInstrumentation::NAME) === true) {
    return;
}

if (extension_loaded('opentelemetry_php74') === false) {
    trigger_error('The opentelemetry_php74 extension must be loaded in order to autoload the OpenTelemetry Laravel auto-instrumentation', E_USER_WARNING);
    return;
}

PDOInstrumentation::register();

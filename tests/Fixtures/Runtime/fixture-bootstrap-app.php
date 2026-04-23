<?php

declare(strict_types=1);

use Oxhq\Oxcribe\OxcribeServiceProvider;
use Tests\Fixtures\Runtime\FixtureTask;
use Tests\Fixtures\Runtime\FixtureTaskHandler;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/vendor/orchestra/testbench-core/laravel/bootstrap/app.php';

$app->register(OxcribeServiceProvider::class);
$app->bind(FixtureTask::class.'Handler', static fn (): FixtureTaskHandler => new FixtureTaskHandler('hello'));
$app->boot();

return $app;

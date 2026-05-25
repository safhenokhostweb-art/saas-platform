<?php

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Debug\ExceptionHandler;

/*
|--------------------------------------------------------------------------
| PHASE 6.875 — CANONICAL APPLICATION BOOTSTRAP
|--------------------------------------------------------------------------
|
| NOTE: Both projects are on Laravel 10.50.2, which uses the classic
| bootstrap/app.php pattern (not the Laravel 11+ Application::configure
| pattern). The customer-site uses Laravel 11 style bootstrap/app.php
| but this is because it was initialized with Laravel 11 installer.
|
| Since the underlying framework is Laravel 10.x, the Application::configure()
| method does NOT exist. We keep the classic L10 bootstrap pattern.
|
| The REAL runtime unification happens at the middleware/config level,
| not at the bootstrap level.
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

return $app;

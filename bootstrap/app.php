<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsureRoleAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureAccountIsActive::class,
        ]);
        $middleware->alias([
            'role' => EnsureRoleAccess::class,
            'module' => EnsureModuleAccess::class,
            'ability' => CheckForAnyAbility::class,
            'abilities' => CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            $postMaxSize = ini_get('post_max_size') ?: '10M';

            $session = $request->hasSession() ? $request->session() : app('session.store');
            if (! $session->isStarted()) {
                $session->start();
            }
            $request->setLaravelSession($session);

            $response = back()
                ->withInput($request->except(['evidence', 'files']))
                ->withErrors([
                    'evidence' => "Total ukuran file melebihi batas server ({$postMaxSize}). Kurangi jumlah atau ukuran foto.",
                ]);

            $session->save();

            return $response;
        });
    })->create();

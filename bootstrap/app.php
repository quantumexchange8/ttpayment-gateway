<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\PaymentSessionTimeout::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);
    })
    ->withSchedule(function(Schedule $schedule) {
        $schedule->command('check:deposit-status')->everyThreeMinutes();
        $schedule->command('check:deposit-expired-status')->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {

            $customMessages = [
                500 => 'Something went wrong on our server.',
                503 => 'Service is temporarily unavailable.',
                404 => 'The page you are looking for could not be found.',
                403 => 'You are not authorized to access this page.',
            ];

            $status = $response->getStatusCode();

            if (app()->environment(['local', 'production', 'testing']) && in_array($response->getStatusCode(), [500, 503, 404, 403])) {
                return Inertia::render('ErrorPage', [
                    'status' => $status,
                    'message' => $customMessages[$status],
                ])->toResponse($request)->setStatusCode($status);
            } elseif ($response->getStatusCode() === 419) {
                return back()->with([
                    'message' => 'The page expired, please try again.',
                ]);
            }

            return $response;
        });
    })->create();

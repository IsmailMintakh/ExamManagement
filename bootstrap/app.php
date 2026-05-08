<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Pre-class reminder: every minute, find period slots starting ~2 min
        // from now and push to the assigned teacher (or today's substitute).
        $schedule->command('timetable:notify-upcoming --minutes=2')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->onOneServer()
            ->runInBackground();

        // Daily digest: once at 07:00 Mon-Sat, push each teacher their day-at-
        // a-glance so the per-period reminders feel less spammy.
        $schedule->command('timetable:send-daily-digest')
            ->dailyAt('07:00')
            ->weekdays()
            ->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, $request) {
            // Don't interfere with JSON/API responses
            if ($request->expectsJson()) {
                return $response;
            }

            // In production, render friendly error pages for Inertia requests
            if (!app()->environment('local', 'testing') || $request->header('X-Inertia')) {
                $status = $response->getStatusCode();

                if (in_array($status, [500, 503, 404, 403, 419, 429])) {
                    return \Inertia\Inertia::render('Errors/Error', [
                        'status' => $status,
                        'message' => $status === 500 ? 'Something went wrong on our side.' : $exception->getMessage(),
                    ])
                        ->toResponse($request)
                        ->setStatusCode($status);
                }
            }

            return $response;
        });
    })->create();

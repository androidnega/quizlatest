<?php

use App\Http\Middleware\EnsureDesktopForExam;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCoordinator;
use App\Http\Middleware\EnsureUserIsExaminer;
use App\Http\Middleware\EnsureUserIsStudent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'coordinator' => EnsureUserIsCoordinator::class,
            'examiner' => EnsureUserIsExaminer::class,
            'student' => EnsureUserIsStudent::class,
            'desktop' => EnsureDesktopForExam::class,
        ]);

        // Laravel's default middleware priority list pins the framework's
        // Authenticate middleware (via the AuthenticatesRequests contract) at
        // a fixed slot regardless of how a route group orders its middleware.
        // Without intervention, that means a mobile request to a quiz route
        // gets a 302 to /login BEFORE our desktop gate runs, which is the
        // wrong UX — the user has no idea their device is the problem.
        //
        // We prepend EnsureDesktopForExam BEFORE the contract (not the
        // concrete class — the priority list keys on the contract) so the
        // desktop check fires first and shows a helpful "desktop only" page.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: EnsureDesktopForExam::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

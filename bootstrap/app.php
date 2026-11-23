<?php
// Configura el inicio de la aplicación.


use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../routes/api.php',
            __DIR__.'/../routes/api/inventory.php',
            __DIR__.'/../routes/api/supplies.php',
            __DIR__.'/../routes/api/prescriptions.php',
            __DIR__.'/../routes/api/dispensations.php',
            __DIR__.'/../routes/api/carts.php',
            __DIR__.'/../routes/api/controlled-ledger.php',
            __DIR__.'/../routes/api/delivery-orders.php',
            __DIR__.'/../routes/api/alerts.php',
            __DIR__.'/../routes/api/reports.php',
            __DIR__.'/../routes/api/search.php',
            __DIR__.'/../routes/api/notifications.php',
            __DIR__.'/../routes/api/patients.php',
            __DIR__.'/../routes/api/professionals.php',
            __DIR__.'/../routes/api/authorized-people.php',
            __DIR__.'/../routes/api/programs.php',
            __DIR__.'/../routes/api/users.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(/* Callback que completa un paso de la operación actual. */ function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/login');
        $middleware->web(append: [\App\Http\Middleware\AuthorizeRoutePermission::class]);
        $middleware->trustProxies(
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR,
        );
    })
    ->withExceptions(/* Callback que completa un paso de la operación actual. */ function (Exceptions $exceptions) {
        $exceptions->dontFlash(['autorizador_rut', 'autorizador_password']);
        $exceptions->shouldRenderJsonWhen(
            static fn (\Illuminate\Http\Request $request, \Throwable $exception): bool => $request->expectsJson()
                || $request->is('api/*'),
        );
    })->create();

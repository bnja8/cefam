<?php
// Configura el inicio de la aplicación.


use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../routes/api/Auth/auth.php',
            __DIR__.'/../routes/api/Alerts/alerts.php',
            __DIR__.'/../routes/api/AuthorizedPeople/authorized-people.php',
            __DIR__.'/../routes/api/Carts/carts.php',
            __DIR__.'/../routes/api/ControlledLedger/controlled-ledger.php',
            __DIR__.'/../routes/api/Dashboard/dashboard.php',
            __DIR__.'/../routes/api/DeliveryOrders/delivery-orders.php',
            __DIR__.'/../routes/api/Dispensations/dispensations.php',
            __DIR__.'/../routes/api/Inventory/inventory.php',
            __DIR__.'/../routes/api/Modules/users.php',
            __DIR__.'/../routes/api/Notifications/notifications.php',
            __DIR__.'/../routes/api/Patients/patients.php',
            __DIR__.'/../routes/api/Prescriptions/prescriptions.php',
            __DIR__.'/../routes/api/Professionals/professionals.php',
            __DIR__.'/../routes/api/Programs/programs.php',
            __DIR__.'/../routes/api/Reports/reports.php',
            __DIR__.'/../routes/api/Search/search.php',
            __DIR__.'/../routes/api/Supplies/supplies.php',
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

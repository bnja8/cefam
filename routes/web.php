<?php
// Registra las rutas de web.

use App\Http\Controllers\Alerts\AlertController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Carts\CartController;
use App\Http\Controllers\ControlledLedger\ControlledLedgerController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\DeliveryOrders\DeliveryOrderController;
use App\Http\Controllers\Dispensations\DispensationController;
use App\Http\Controllers\Inventory\BarcodeLookupController;
use App\Http\Controllers\Inventory\CategoryController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryOperationController;
use App\Http\Controllers\Inventory\LocationController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Modules\ModuleController;
use App\Http\Controllers\Modules\ModuleHubController;
use App\Http\Controllers\Modules\RolePermissionController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Patients\PatientController;
use App\Http\Controllers\Patients\PatientFileController;
use App\Http\Controllers\Prescriptions\PrescriptionController;
use App\Http\Controllers\Professionals\ProfessionalController;
use App\Http\Controllers\Programs\HealthProgramController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Search\GlobalSearchController;
use App\Http\Controllers\Search\PersonLookupController;
use App\Http\Controllers\Supplies\SupplyController;
use App\Http\Controllers\AuthorizedPeople\AuthorizedPersonController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(/* Define la acción que atiende la ruta. */ function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware('auth')->group(/* Define la acción que atiende la ruta. */ function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::post('/notificaciones/leer-todas', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notificaciones/{notificationId}/abrir', [NotificationController::class, 'open'])
        ->whereNumber('notificationId')->name('notifications.open');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/buscar', GlobalSearchController::class)->name('search');
    Route::get('/api/personas/buscar-rut', PersonLookupController::class)
        ->middleware('throttle:60,1')->name('people.lookup');
    Route::get('/panel/{module}', [ModuleHubController::class, 'show'])
        ->whereIn('module', ['pacientes', 'personal', 'recetas', 'programas', 'personas-autorizadas', 'remesas', 'inventario', 'carros', 'dispensaciones', 'alertas', 'reportes', 'libro-digital', 'administracion'])
        ->name('module-hubs.show');

    Route::get('/pacientes', [PatientController::class, 'index'])->defaults('screen', 'list')->name('patients.index');
    Route::get('/pacientes/crear', [PatientController::class, 'index'])->defaults('screen', 'create')->name('patients.create');
    Route::get('/api/pacientes/buscar', [PatientController::class, 'search'])
        ->middleware('throttle:60,1')
        ->name('patients.api.search');
    Route::post('/pacientes', [PatientController::class, 'store'])->name('patients.store');
    Route::get('/pacientes/{patientRut}/ficha', [PatientFileController::class, 'show'])->name('patients.file');
    Route::get('/pacientes/{patientRut}/editar', [PatientController::class, 'edit'])->name('patients.edit');
    Route::put('/pacientes/{patientRut}', [PatientController::class, 'update'])->name('patients.update');

    Route::get('/personal', [ProfessionalController::class, 'index'])->defaults('screen', 'list')->name('professionals.index');
    Route::get('/personal/crear', [ProfessionalController::class, 'index'])->defaults('screen', 'create')->name('professionals.create');
    Route::post('/personal', [ProfessionalController::class, 'store'])->name('professionals.store');
    Route::get('/personal/{professionalRut}/editar', [ProfessionalController::class, 'edit'])->name('professionals.edit');
    Route::put('/personal/{professionalRut}', [ProfessionalController::class, 'update'])->name('professionals.update');
    Route::patch('/personal/{professionalRut}/estado', [ProfessionalController::class, 'toggle'])->name('professionals.toggle');

    Route::get('/recetas', [PrescriptionController::class, 'index'])->defaults('screen', 'list')->name('recipes.index');
    Route::get('/recetas/crear', [PrescriptionController::class, 'index'])->defaults('screen', 'create')->name('recipes.create');
    Route::get('/api/recetas/productos', [PrescriptionController::class, 'searchProducts'])
        ->middleware('throttle:60,1')->name('recipes.products.search');
    Route::post('/recetas', [PrescriptionController::class, 'store'])->name('recipes.store');
    Route::get('/recetas/{prescriptionId}/imprimir', [PrescriptionController::class, 'print'])->whereNumber('prescriptionId')->name('recipes.print');

    Route::get('/programas', [HealthProgramController::class, 'index'])->defaults('screen', 'programs')->name('programs.index');
    Route::get('/programas/afiliaciones', [HealthProgramController::class, 'index'])->defaults('screen', 'affiliations')->name('programs.affiliations.index');
    Route::get('/programas/afiliaciones/crear', [HealthProgramController::class, 'index'])->defaults('screen', 'enroll')->name('programs.enroll.create');
    Route::get('/programas/crear', [HealthProgramController::class, 'index'])->defaults('screen', 'create')->name('programs.create');
    Route::get('/programas/{programId}/editar', [HealthProgramController::class, 'index'])->whereNumber('programId')->defaults('screen', 'edit')->name('programs.edit');
    Route::post('/programas', [HealthProgramController::class, 'store'])->name('programs.store');
    Route::put('/programas/{programId}', [HealthProgramController::class, 'update'])->whereNumber('programId')->name('programs.update');
    Route::patch('/programas/{programId}/estado', [HealthProgramController::class, 'toggle'])->whereNumber('programId')->name('programs.toggle');
    Route::post('/programas/afiliaciones', [HealthProgramController::class, 'enroll'])->name('programs.enroll');

    Route::get('/personas-autorizadas', [AuthorizedPersonController::class, 'index'])->defaults('screen', 'list')->name('authorized-people.index');
    Route::get('/personas-autorizadas/crear', [AuthorizedPersonController::class, 'index'])->defaults('screen', 'create')->name('authorized-people.create');
    Route::post('/personas-autorizadas', [AuthorizedPersonController::class, 'store'])->name('authorized-people.store');
    Route::get('/personas-autorizadas/{authorizedPersonId}/editar', [AuthorizedPersonController::class, 'edit'])->whereNumber('authorizedPersonId')->name('authorized-people.edit');
    Route::put('/personas-autorizadas/{authorizedPersonId}', [AuthorizedPersonController::class, 'update'])->whereNumber('authorizedPersonId')->name('authorized-people.update');
    Route::patch('/personas-autorizadas/{authorizedPersonId}/estado', [AuthorizedPersonController::class, 'toggle'])->whereNumber('authorizedPersonId')->name('authorized-people.toggle');

    Route::get('/remesas', [SupplyController::class, 'index'])->defaults('screen', 'shipments')->name('supplies.index');
    Route::get('/remesas/solicitudes', [SupplyController::class, 'index'])->defaults('screen', 'requests')->name('supplies.requests.index');
    Route::get('/remesas/solicitudes/crear', [SupplyController::class, 'index'])->defaults('screen', 'request-create')->name('supplies.requests.create');
    Route::get('/api/remesas/solicitudes/productos', [SupplyController::class, 'searchRequestProducts'])
        ->middleware('throttle:60,1')->name('supplies.requests.products.search');
    Route::get('/remesas/recepcion', [SupplyController::class, 'index'])->defaults('screen', 'receive')->name('supplies.receive.form');
    Route::post('/remesas/solicitudes', [SupplyController::class, 'storeRequest'])->name('supplies.requests.store');
    Route::post('/remesas/recepcion', [SupplyController::class, 'receive'])->name('supplies.receive');

    Route::get('/alertas', [AlertController::class, 'home'])->name('alerts.index');
    Route::get('/alertas/mapa', [AlertController::class, 'index'])->name('alerts.map');
    Route::get('/alertas/historial', [AlertController::class, 'history'])->name('alerts.history');
    Route::get('/alertas/{alertId}', [AlertController::class, 'show'])->whereNumber('alertId')->name('alerts.show');
    Route::get('/alertas/{alertId}/solicitar-retiro', [AlertController::class, 'requestExpiryWithdrawal'])
        ->whereNumber('alertId')->name('alerts.expiry-withdrawal');
    Route::post('/alertas/{alertId}/solicitar-reposicion', [AlertController::class, 'replenish'])->whereNumber('alertId')->name('alerts.replenish');
    Route::patch('/alertas/{alertId}/asignar', [AlertController::class, 'assign'])->whereNumber('alertId')->name('alerts.assign');
    Route::patch('/alertas/{alertId}/reconocer', [AlertController::class, 'acknowledge'])->whereNumber('alertId')->name('alerts.acknowledge');
    Route::post('/alertas/{alertId}/seguimiento', [AlertController::class, 'follow'])->whereNumber('alertId')->name('alerts.follow');

    Route::get('/reportes/entregas', [ReportController::class, 'index'])->defaults('screen', 'deliveries')->name('reports.deliveries');
    Route::get('/reportes/existencias', [ReportController::class, 'index'])->defaults('screen', 'stock')->name('reports.stock');
    Route::get('/reportes/movimientos', [ReportController::class, 'index'])->defaults('screen', 'movements')->name('reports.movements');
    Route::get('/reportes/kardex', [ReportController::class, 'index'])->defaults('screen', 'kardex')->name('reports.kardex');
    Route::get('/reportes/controlados', [ReportController::class, 'index'])->defaults('screen', 'controlled')->name('reports.controlled');
    Route::get('/reportes/exportar', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/libro-digital', [ControlledLedgerController::class, 'index'])->defaults('screen', 'movements')->name('controlled-ledger.index');
    Route::get('/libro-digital/registrar', [ControlledLedgerController::class, 'index'])->defaults('screen', 'create')->name('controlled-ledger.create');
    Route::get('/libro-digital/conciliar', [ControlledLedgerController::class, 'index'])->defaults('screen', 'reconcile')->name('controlled-ledger.reconcile.form');
    Route::post('/libro-digital', [ControlledLedgerController::class, 'store'])->name('controlled-ledger.store');
    Route::post('/libro-digital/conciliar', [ControlledLedgerController::class, 'reconcile'])->name('controlled-ledger.reconcile');

    Route::get('/inventario', [InventoryController::class, 'index'])->defaults('screen', 'stock')->name('inventory.index');
    Route::get('/inventario/catalogo', [ProductController::class, 'index'])->name('inventory.catalog');
    Route::get('/inventario/ubicaciones', [LocationController::class, 'index'])->name('locations.index');
    Route::get('/inventario/ubicaciones/crear', [LocationController::class, 'create'])->name('locations.create');
    Route::post('/inventario/ubicaciones', [LocationController::class, 'store'])->name('locations.store');
    Route::get('/inventario/ubicaciones/{locationId}/editar', [LocationController::class, 'edit'])->whereNumber('locationId')->name('locations.edit');
    Route::put('/inventario/ubicaciones/{locationId}', [LocationController::class, 'update'])->whereNumber('locationId')->name('locations.update');
    Route::get('/categorias-producto', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categorias-producto/crear', [CategoryController::class, 'create'])->name('categories.create');
    Route::get('/productos/crear', [ProductController::class, 'create'])->name('products.create');
    Route::get('/inventario/recepcion', /* Define la acción que atiende la ruta. */ fn () => redirect()->route('supplies.receive.form'))->name('inventory.receive.form');
    Route::get('/inventario/traspasos/crear', [InventoryOperationController::class, 'createTransfer'])->name('inventory.transfer.form');
    Route::get('/inventario/ajustes', [InventoryOperationController::class, 'adjustmentRequests'])->name('inventory.adjustments.index');
    Route::get('/inventario/ajustes/crear', [InventoryOperationController::class, 'createAdjustment'])->name('inventory.adjust.form');
    Route::get('/inventario/ajustes/{groupId}', [InventoryOperationController::class, 'showAdjustmentRequest'])
        ->whereNumber('groupId')
        ->name('inventory.adjustments.show');
    Route::get('/api/inventario', [InventoryController::class, 'apiIndex'])->name('inventory.api.index');
    Route::get('/api/medicamentos/{productId}/lotes', [InventoryController::class, 'apiProductLots'])
        ->whereNumber('productId')
        ->name('products.api.lots');
    Route::get('/api/productos/{productId}/fefo', [InventoryController::class, 'apiFefoLots'])
        ->whereNumber('productId')
        ->name('products.api.fefo');
    Route::get('/productos/buscar-codigo-barra', BarcodeLookupController::class)->name('products.barcode.lookup');
    Route::post('/categorias-producto', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categorias-producto/{categoryId}/editar', [CategoryController::class, 'edit'])->whereNumber('categoryId')->name('categories.edit');
    Route::put('/categorias-producto/{categoryId}', [CategoryController::class, 'update'])->whereNumber('categoryId')->name('categories.update');
    Route::post('/productos', [ProductController::class, 'store'])->name('products.store');
    Route::get('/productos/{productId}/existencias', [InventoryController::class, 'productStock'])->whereNumber('productId')->name('products.stock');
    Route::put('/productos/{productId}/limites-stock', [InventoryOperationController::class, 'updateStockLimits'])->whereNumber('productId')->name('products.stock-limits.update');
    Route::get('/productos/{productId}/editar', [ProductController::class, 'edit'])->whereNumber('productId')->name('products.edit');
    Route::put('/productos/{productId}', [ProductController::class, 'update'])->whereNumber('productId')->name('products.update');
    Route::post('/inventario/recepciones', [InventoryOperationController::class, 'receive'])->name('inventory.receive');
    Route::post('/inventario/ajustes', [InventoryOperationController::class, 'adjust'])->name('inventory.adjust');
    Route::patch('/inventario/ajustes/{adjustmentId}/resolver', [InventoryOperationController::class, 'resolveAdjustment'])->whereNumber('adjustmentId')->name('inventory.adjustments.resolve');
    Route::post('/inventario/traspasos', [InventoryOperationController::class, 'transfer'])->name('inventory.transfer');

    Route::get('/carros', [CartController::class, 'index'])->defaults('screen', 'stock')->name('carts.index');
    Route::get('/carros/paro/uso', [CartController::class, 'index'])->defaults('screen', 'usage')->name('carts.usage.index');
    Route::get('/carros/paro/salidas/crear', [CartController::class, 'index'])->defaults('screen', 'paro-create')->name('carts.paro.create');
    Route::get('/carros/tratamientos/crear', [CartController::class, 'index'])->defaults('screen', 'treatment-create')->name('carts.treatments.create');
    Route::get('/carros/unidosis', [CartController::class, 'index'])->defaults('screen', 'preparations')->name('carts.unidosis.index');
    Route::get('/carros/unidosis/preparar', [CartController::class, 'index'])->defaults('screen', 'unidosis-create')->name('carts.unidosis.create');
    Route::post('/carros/tratamientos', [CartController::class, 'storeTreatment'])->name('carts.treatments.store');
    Route::get('/api/carros/unidosis/productos', [CartController::class, 'searchUnidosisProducts'])
        ->middleware('throttle:60,1')->name('carts.unidosis.products.search');
    Route::post('/carros/unidosis', [CartController::class, 'prepare'])->name('carts.unidosis.prepare');
    Route::patch('/carros/unidosis/{preparationId}/confirmar', [CartController::class, 'confirm'])->whereNumber('preparationId')->name('carts.unidosis.confirm');
    Route::post('/carros/paro/salidas', [CartController::class, 'dispenseFromParo'])->name('carts.paro.dispense');

    Route::get('/dispensaciones', [DispensationController::class, 'index'])->defaults('screen', 'create')->name('dispensations.index');
    Route::get('/dispensaciones/entregas', [DispensationController::class, 'index'])->defaults('screen', 'deliveries')->name('dispensations.deliveries');
    Route::get('/api/pacientes/{patientRut}/dispensacion-opciones', [DispensationController::class, 'patientOptions'])
        ->where('patientRut', '[0-9A-Za-z-]+')->name('dispensations.patient-options');
    Route::get('/api/dispensaciones/productos', [DispensationController::class, 'searchProducts'])
        ->middleware('throttle:60,1')->name('dispensations.products.search');
    Route::post('/dispensaciones', [DispensationController::class, 'store'])->name('dispensations.store');
    Route::get('/ordenes-entrega/{orderId}', [DeliveryOrderController::class, 'show'])->whereNumber('orderId')->name('delivery-orders.show');
    Route::get('/ordenes-entrega/{orderId}/imprimir', [DeliveryOrderController::class, 'receipt'])->whereNumber('orderId')->name('delivery-orders.receipt');
    Route::get('/ordenes-entrega/{orderId}/etiquetas', [DeliveryOrderController::class, 'labels'])->whereNumber('orderId')->name('delivery-orders.labels');
    Route::get('/ordenes-entrega/{orderId}/detalle/{detailId}/etiqueta', [DeliveryOrderController::class, 'label'])->whereNumber('orderId')->whereNumber('detailId')->name('delivery-orders.label');

    Route::get('/modulos/{module}', [ModuleController::class, 'show'])
        ->whereIn('module', ['administracion'])
        ->name('modules.show');
    Route::get('/usuarios', [ModuleController::class, 'show'])
        ->defaults('module', 'administracion')
        ->defaults('screen', 'list')
        ->name('users.index');
    Route::get('/modulos/administracion/usuarios/crear', [ModuleController::class, 'show'])
        ->defaults('module', 'administracion')
        ->defaults('screen', 'create-user')
        ->name('users.create');
    Route::post('/usuarios', [ModuleController::class, 'storeUser'])->name('users.store');
    Route::get('/usuarios/{userRut}/editar', [ModuleController::class, 'editUser'])->name('users.edit');
    Route::put('/usuarios/{userRut}', [ModuleController::class, 'updateUser'])->name('users.update');
    Route::patch('/usuarios/{userRut}/estado', [ModuleController::class, 'toggleUser'])->name('users.toggle');
    Route::get('/roles', [RolePermissionController::class, 'index'])->name('roles.index');
    Route::get('/roles/crear', [RolePermissionController::class, 'create'])->name('roles.create');
    Route::post('/roles', [RolePermissionController::class, 'store'])->name('roles.store');
    Route::get('/roles/{roleId}/permisos', [RolePermissionController::class, 'showPermissions'])
        ->whereNumber('roleId')->name('roles.permissions.show');
    Route::get('/permisos', [RolePermissionController::class, 'index'])->name('permissions.index');
    Route::put('/roles/{roleId}/permisos', [RolePermissionController::class, 'updatePermissions'])
        ->whereNumber('roleId')->name('roles.permissions.update');
    Route::patch('/roles/{roleId}/estado', [RolePermissionController::class, 'toggleStatus'])
        ->whereNumber('roleId')->name('roles.toggle');
});

Route::fallback(/* Define la acción que atiende la ruta. */ fn () => response()->view('errors.404', [], 404));

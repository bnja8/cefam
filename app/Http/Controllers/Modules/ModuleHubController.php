<?php


namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ModuleHubController extends Controller
{
    private const ACTIVE_ROUTE_PATTERNS = [
        'pacientes' => ['patients.*'],
        'personal' => ['professionals.*'],
        'recetas' => ['recipes.*'],
        'programas' => ['programs.*'],
        'personas-autorizadas' => ['authorized-people.*'],
        'remesas' => ['supplies.*'],
        'inventario' => ['inventory.*', 'categories.*', 'locations.*', 'products.*'],
        'carros' => ['carts.*'],
        'dispensaciones' => ['dispensations.*', 'delivery-orders.*'],
        'reportes' => ['reports.*'],
        'libro-digital' => ['controlled-ledger.*'],
        'administracion' => ['modules.show', 'users.*', 'roles.*', 'permissions.*'],
        'alertas' => ['alerts.*'],
    ];

    private const MODULES = [
        'pacientes' => [
            'title' => 'Pacientes',
            'description' => 'Consulta y gestiona las fichas de pacientes.',
            'icon' => 'fa-hospital-user',
            'cards' => [
                ['title' => 'Consultar pacientes', 'description' => 'Busca fichas y revisa programas activos.', 'icon' => 'fa-magnifying-glass', 'route' => 'patients.index', 'tone' => 'blue'],
                ['title' => 'Registrar paciente', 'description' => 'Incorpora una nueva ficha al sistema.', 'icon' => 'fa-user-plus', 'route' => 'patients.create', 'tone' => 'green', 'roles' => ['Administrador']],
            ],
        ],
        'personal' => [
            'title' => 'Personal de Salud',
            'description' => 'Consulta y registra profesionales de salud del CESFAM.',
            'icon' => 'fa-user-doctor',
            'roles' => ['Administrador', 'Químico Farmacéutico', 'Técnico Farmacéutico'],
            'cards' => [
                ['title' => 'Consultar personal', 'description' => 'Busca profesionales y revisa sus registros.', 'icon' => 'fa-magnifying-glass', 'route' => 'professionals.index', 'tone' => 'blue'],
                ['title' => 'Registrar personal', 'description' => 'Incorpora un profesional de salud al sistema.', 'icon' => 'fa-user-plus', 'route' => 'professionals.create', 'tone' => 'green', 'roles' => ['Administrador', 'Químico Farmacéutico']],
            ],
        ],
        'recetas' => [
            'title' => 'Recetas médicas',
            'description' => 'Registra y consulta prescripciones vigentes y retenidas.',
            'icon' => 'fa-file-prescription',
            'cards' => [
                ['title' => 'Consultar recetas', 'description' => 'Revisa las prescripciones registradas.', 'icon' => 'fa-list-check', 'route' => 'recipes.index', 'tone' => 'blue'],
                ['title' => 'Registrar receta', 'description' => 'Ingresa una receta con sus medicamentos.', 'icon' => 'fa-file-circle-plus', 'route' => 'recipes.create', 'tone' => 'green'],
            ],
        ],
        'programas' => [
            'title' => 'Programas de salud',
            'description' => 'Administra programas, afiliaciones y cobertura de pacientes.',
            'icon' => 'fa-heart-pulse',
            'cards' => [
                ['title' => 'Consultar programas', 'description' => 'Revisa los programas de salud disponibles.', 'icon' => 'fa-list-check', 'route' => 'programs.index', 'tone' => 'blue'],
                ['title' => 'Pacientes inscritos', 'description' => 'Revisa la cobertura y afiliaciones registradas.', 'icon' => 'fa-users', 'route' => 'programs.affiliations.index', 'tone' => 'violet'],
                ['title' => 'Afiliar paciente', 'description' => 'Inscribe un paciente y define su cobertura.', 'icon' => 'fa-user-plus', 'route' => 'programs.enroll.create', 'tone' => 'green'],
                ['title' => 'Crear programa', 'description' => 'Agrega un programa de salud al catálogo.', 'icon' => 'fa-folder-plus', 'route' => 'programs.create', 'tone' => 'violet', 'roles' => ['Administrador', 'Químico Farmacéutico']],
            ],
        ],
        'personas-autorizadas' => [
            'title' => 'Personas autorizadas',
            'description' => 'Consulta y administra las autorizaciones de retiro.',
            'icon' => 'fa-users-gear',
            'cards' => [
                ['title' => 'Consultar personas autorizadas', 'description' => 'Revisa las personas autorizadas por paciente.', 'icon' => 'fa-list-check', 'route' => 'authorized-people.index', 'tone' => 'blue'],
                ['title' => 'Registrar persona autorizada', 'description' => 'Registra a una persona autorizada para retirar medicamentos.', 'icon' => 'fa-user-plus', 'route' => 'authorized-people.create', 'tone' => 'green', 'roles' => ['Administrador', 'Técnico Farmacéutico']],
            ],
        ],
        'remesas' => [
            'title' => 'Abastecimiento farmacéutico',
            'description' => 'Gestiona solicitudes de reposición y recepción de remesas.',
            'icon' => 'fa-truck-ramp-box',
            'cards' => [
                ['title' => 'Consultar remesas', 'description' => 'Revisa las remesas recibidas.', 'icon' => 'fa-list-check', 'route' => 'supplies.index', 'tone' => 'blue'],
                ['title' => 'Consultar solicitudes', 'description' => 'Revisa el estado de las solicitudes de reposición.', 'icon' => 'fa-clipboard-list', 'route' => 'supplies.requests.index', 'tone' => 'violet'],
                ['title' => 'Solicitar reposición', 'description' => 'Genera una solicitud de productos.', 'icon' => 'fa-file-circle-plus', 'route' => 'supplies.requests.create', 'tone' => 'green', 'roles' => ['Administrador', 'Químico Farmacéutico', 'Técnico Farmacéutico']],
                ['title' => 'Registrar recepción', 'description' => 'Ingresa la remesa recibida y sus lotes.', 'icon' => 'fa-box-open', 'route' => 'supplies.receive.form', 'tone' => 'amber', 'roles' => ['Administrador', 'Químico Farmacéutico', 'Técnico Farmacéutico']],
            ],
        ],
        'inventario' => [
            'title' => 'Gestión de existencias',
            'description' => 'Consulta existencias y accede a las operaciones de inventario.',
            'icon' => 'fa-boxes-stacked',
            'cards' => [
                ['title' => 'Consultar existencias', 'description' => 'Busca productos y revisa stock por lote.', 'icon' => 'fa-magnifying-glass-chart', 'route' => 'inventory.index', 'tone' => 'blue'],
                ['title' => 'Medicamentos e insumos', 'description' => 'Revisa los productos y sus datos de catálogo.', 'icon' => 'fa-capsules', 'route' => 'inventory.catalog', 'tone' => 'violet', 'roles' => ['Administrador']],
                ['title' => 'Consultar categorías', 'description' => 'Revisa las categorías disponibles en el catálogo.', 'icon' => 'fa-list', 'route' => 'categories.index', 'tone' => 'blue', 'roles' => ['Administrador', 'Químico Farmacéutico']],
                ['title' => 'Consultar ubicaciones', 'description' => 'Revisa y administra bodegas, vitrinas y carros.', 'icon' => 'fa-location-dot', 'route' => 'locations.index', 'tone' => 'blue', 'roles' => ['Administrador', 'Químico Farmacéutico']],
                ['title' => 'Nueva categoría', 'description' => 'Agrega una categoría para organizar los productos.', 'icon' => 'fa-tags', 'route' => 'categories.create', 'tone' => 'green', 'roles' => ['Administrador', 'Químico Farmacéutico']],
                ['title' => 'Agregar producto al catálogo', 'description' => 'Registra un producto nuevo en el catálogo.', 'icon' => 'fa-capsules', 'route' => 'products.create', 'tone' => 'violet', 'roles' => ['Administrador']],
                ['title' => 'Traspasar existencias', 'description' => 'Mueve stock entre ubicaciones.', 'icon' => 'fa-right-left', 'route' => 'inventory.transfer.form', 'tone' => 'amber', 'roles' => ['Administrador', 'Químico Farmacéutico']],
                ['title' => 'Solicitudes de ajuste', 'description' => 'Solicita correcciones o mermas y revisa sus autorizaciones.', 'icon' => 'fa-scale-balanced', 'route' => 'inventory.adjustments.index', 'tone' => 'red', 'roles' => ['Administrador', 'Químico Farmacéutico', 'Técnico Farmacéutico']],
            ],
        ],
        'carros' => [
            'title' => 'Carro de Paro y Unidosis',
            'description' => 'Consulta existencias y gestiona tratamientos, salidas y preparaciones.',
            'icon' => 'fa-truck-medical',
            'cards' => [
                ['title' => 'Consultar existencias', 'description' => 'Revisa lotes disponibles en los carros.', 'icon' => 'fa-boxes-stacked', 'route' => 'carts.index', 'tone' => 'blue'],
                ['title' => 'Consultar uso del Carro de Paro', 'description' => 'Revisa el historial de salidas de emergencia.', 'icon' => 'fa-clock-rotate-left', 'route' => 'carts.usage.index', 'tone' => 'blue'],
                ['title' => 'Registrar uso de Carro de Paro', 'description' => 'Registra una salida de emergencia trazable.', 'icon' => 'fa-kit-medical', 'route' => 'carts.paro.create', 'tone' => 'red'],
                ['title' => 'Registrar tratamiento', 'description' => 'Crea un tratamiento para preparación Unidosis.', 'icon' => 'fa-notes-medical', 'route' => 'carts.treatments.create', 'tone' => 'green'],
                ['title' => 'Preparar Unidosis', 'description' => 'Prepara medicamentos y dosis para un tratamiento.', 'icon' => 'fa-pills', 'route' => 'carts.unidosis.create', 'tone' => 'violet'],
                ['title' => 'Consultar preparaciones', 'description' => 'Revisa las preparaciones y sus rótulos.', 'icon' => 'fa-clipboard-check', 'route' => 'carts.unidosis.index', 'tone' => 'blue'],
            ],
        ],
        'dispensaciones' => [
            'title' => 'Dispensación de medicamentos',
            'description' => 'Registra entregas y consulta su trazabilidad.',
            'icon' => 'fa-pills',
            'cards' => [
                ['title' => 'Registrar dispensación', 'description' => 'Valida receta, paciente y disponibilidad FEFO.', 'icon' => 'fa-prescription-bottle-medical', 'route' => 'dispensations.index', 'tone' => 'green'],
                ['title' => 'Historial de dispensaciones', 'description' => 'Revisa las entregas y sus órdenes imprimibles.', 'icon' => 'fa-clock-rotate-left', 'route' => 'dispensations.deliveries', 'tone' => 'blue'],
            ],
        ],
        'reportes' => [
            'title' => 'Informes de gestión',
            'description' => 'Consulta movimientos, existencias y resultados operativos.',
            'icon' => 'fa-chart-line',
            'roles' => ['Administrador', 'Químico Farmacéutico'],
            'cards' => [
                ['title' => 'Entregas y dispensaciones', 'description' => 'Consulta las entregas registradas.', 'icon' => 'fa-prescription-bottle-medical', 'route' => 'reports.deliveries', 'tone' => 'teal'],
                ['title' => 'Existencias por categoría', 'description' => 'Consulta el stock agrupado por categoría.', 'icon' => 'fa-chart-pie', 'route' => 'reports.stock', 'tone' => 'violet'],
                ['title' => 'Movimientos de inventario', 'description' => 'Revisa el detalle de movimientos registrados.', 'icon' => 'fa-clock-rotate-left', 'route' => 'reports.movements', 'tone' => 'amber'],
                ['title' => 'Kardex de inventario histórico', 'description' => 'Consulta lotes agotados al dispensar o reponer existencias en la ubicación.', 'icon' => 'fa-box-archive', 'route' => 'reports.kardex', 'tone' => 'teal'],
                ['title' => 'Movimientos controlados', 'description' => 'Consulta el libro de medicamentos controlados.', 'icon' => 'fa-shield-halved', 'route' => 'reports.controlled', 'tone' => 'red'],
            ],
        ],
        'libro-digital' => [
            'title' => 'Libro digital de controlados',
            'description' => 'Consulta movimientos y registra operaciones de medicamentos controlados.',
            'icon' => 'fa-book-bookmark',
            'roles' => ['Químico Farmacéutico'],
            'cards' => [
                ['title' => 'Consultar movimientos', 'description' => 'Revisa el historial cronológico del libro.', 'icon' => 'fa-clock-rotate-left', 'route' => 'controlled-ledger.index', 'tone' => 'blue'],
                ['title' => 'Registrar movimiento', 'description' => 'Registra ingresos o salidas con confirmación QF.', 'icon' => 'fa-file-signature', 'route' => 'controlled-ledger.create', 'tone' => 'green'],
                ['title' => 'Conciliar existencias', 'description' => 'Registra el conteo físico de productos controlados.', 'icon' => 'fa-scale-balanced', 'route' => 'controlled-ledger.reconcile.form', 'tone' => 'violet'],
            ],
        ],
        'administracion' => [
            'title' => 'Administración del sistema',
            'description' => 'Consulta y administra las cuentas del sistema.',
            'icon' => 'fa-user-shield',
            'roles' => ['Administrador', 'Químico Farmacéutico'],
            'cards' => [
                ['title' => 'Consultar usuarios', 'description' => 'Revisa cuentas y estados de acceso.', 'icon' => 'fa-users', 'route' => 'users.index', 'tone' => 'blue'],
                ['title' => 'Crear cuenta', 'description' => 'Registra una nueva cuenta de usuario.', 'icon' => 'fa-user-plus', 'route' => 'users.create', 'tone' => 'green', 'roles' => ['Administrador']],
                ['title' => 'Roles y permisos', 'description' => 'Configura los permisos y módulos disponibles para cada rol.', 'icon' => 'fa-shield-halved', 'route' => 'permissions.index', 'tone' => 'violet'],
            ],
        ],
        'alertas' => [
            'title' => 'Alertas operativas',
            'description' => 'Consulta alertas operativas y su historial.',
            'icon' => 'fa-bell',
            'cards' => [
                ['title' => 'Mapa operativo', 'description' => 'Busca alertas activas por tipo.', 'icon' => 'fa-map-location-dot', 'route' => 'alerts.map'],
                ['title' => 'Historial de Alertas', 'description' => 'Consulta responsables, acciones y fechas.', 'icon' => 'fa-clock-rotate-left', 'route' => 'alerts.history', 'roles' => ['Administrador', 'Químico Farmacéutico']],
            ],
        ],
    ];

    public function show(string $module): RedirectResponse
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        $configuration = self::MODULES[$module];
        $user = Auth::user();
        abort_unless($user instanceof Usuario, 401);

        $card = $this->visibleCards($configuration, $user, $user->effectivePermissions())->first();
        abort_unless($card !== null, 403);

        return redirect()->to($card['url']);
    }

    public function navigationFor(Usuario $user): array
    {
        $grantedPermissions = $user->effectivePermissions();
        $modules = collect(self::MODULES)
            ->map(function (array $module, string $slug) use ($user, $grantedPermissions): array {
                $module['items'] = $this->visibleCards($module, $user, $grantedPermissions)->all();
                $module['active_routes'] = self::ACTIVE_ROUTE_PATTERNS[$slug] ?? [];

                return $module;
            })
            ->all();

        $items = static function (string $module, array $routes) use ($modules): array {
            $availableItems = collect($modules[$module]['items']);

            return collect($routes)
                ->map(static fn (string $route) => $availableItems->firstWhere('route', $route))
                ->filter()
                ->values()
                ->all();
        };

        $group = static fn (
            string $key,
            string $title,
            array $groupItems,
            array $activeRoutes,
            array $subsections = []
        ): array => [
            'key' => $key,
            'title' => $title,
            'items' => $groupItems,
            'subsections' => $subsections,
            'active_routes' => $activeRoutes,
        ];

        $authorizedPeopleItems = $items('personas-autorizadas', [
            'authorized-people.index',
            'authorized-people.create',
        ]);
        $cartStockItems = $items('carros', ['carts.index']);
        if ($cartStockItems !== []) {
            $cartStockItems[0]['title'] = 'Consultar existencias de Carro de Paro y Unidosis';
        }

        $groups = [
            $group('dispensaciones', $modules['dispensaciones']['title'], $items('dispensaciones', [
                'dispensations.index',
                'dispensations.deliveries',
            ]), self::ACTIVE_ROUTE_PATTERNS['dispensaciones']),
            $group('recetas', $modules['recetas']['title'], $items('recetas', [
                'recipes.index',
                'recipes.create',
            ]), self::ACTIVE_ROUTE_PATTERNS['recetas']),
            $group('carro-paro', 'Carro de Paro', $items('carros', [
                'carts.usage.index',
                'carts.paro.create',
            ]), ['carts.usage.*', 'carts.paro.*']),
            $group('unidosis', 'Unidosis', $items('carros', [
                'carts.unidosis.create',
                'carts.unidosis.index',
            ]), ['carts.unidosis.*']),
            $group('pacientes', $modules['pacientes']['title'], array_merge(
                $items('pacientes', ['patients.index', 'patients.create']),
                $items('carros', ['carts.treatments.create']),
                $items('programas', ['programs.affiliations.index'])
            ), ['patients.*', 'authorized-people.*', 'carts.treatments.*'], $authorizedPeopleItems === [] ? [] : [[
                'key' => 'personas-autorizadas',
                'title' => 'Personas autorizadas',
                'items' => $authorizedPeopleItems,
            ]]),
            $group('programas', $modules['programas']['title'], $items('programas', [
                'programs.index',
                'programs.enroll.create',
                'programs.create',
            ]), self::ACTIVE_ROUTE_PATTERNS['programas']),
            $group('inventario', $modules['inventario']['title'], array_merge(
                $cartStockItems,
                $items('inventario', [
                    'inventory.index',
                    'inventory.catalog',
                    'categories.index',
                    'locations.index',
                    'categories.create',
                    'products.create',
                    'inventory.transfer.form',
                ])
            ), ['inventory.index', 'inventory.catalog', 'inventory.transfer.*', 'categories.*', 'locations.*', 'products.*', 'carts.index']),
            $group('remesas', 'Abastecimiento y Ajustes Farmacéuticos', array_merge(
                $items('inventario', ['inventory.adjustments.index']),
                $items('remesas', [
                    'supplies.requests.index',
                    'supplies.requests.create',
                    'supplies.receive.form',
                    'supplies.index',
                ])
            ), ['supplies.*', 'inventory.adjustments.*', 'inventory.adjust.form']),
            $group('libro-digital', $modules['libro-digital']['title'], $items('libro-digital', [
                'controlled-ledger.index',
                'controlled-ledger.create',
                'controlled-ledger.reconcile.form',
            ]), self::ACTIVE_ROUTE_PATTERNS['libro-digital']),
            $group('personal', $modules['personal']['title'], $items('personal', [
                'professionals.index',
                'professionals.create',
            ]), self::ACTIVE_ROUTE_PATTERNS['personal']),
            $group('administracion', $modules['administracion']['title'], $items('administracion', [
                'users.index',
                'users.create',
                'permissions.index',
            ]), self::ACTIVE_ROUTE_PATTERNS['administracion']),
            $group('reportes', $modules['reportes']['title'], $items('reportes', [
                'reports.deliveries',
                'reports.stock',
                'reports.movements',
                'reports.kardex',
                'reports.controlled',
            ]), self::ACTIVE_ROUTE_PATTERNS['reportes']),
        ];

        return [
            'principal_items' => $items('alertas', ['alerts.map', 'alerts.history']),
            'groups' => collect($groups)
                ->filter(static fn (array $module): bool => $module['items'] !== []
                    || collect($module['subsections'])->contains(static fn (array $section): bool => $section['items'] !== []))
                ->values()
                ->all(),
        ];
    }

    private function visibleCards(
        array $configuration,
        ?Usuario $user,
        array $grantedPermissions
    ): Collection
    {
        return collect($configuration['cards'])
            ->filter(function (array $card) use ($user, $grantedPermissions): bool {
                if (! $user instanceof Usuario) {
                    return false;
                }

                $permissions = PermissionCatalog::permissionsForRoute($card['route']);
                if ($card['route'] === 'inventory.adjustments.index'
                    && array_intersect(['inventory.adjust.request', 'inventory.adjust.resolve'], $grantedPermissions) === []) {
                    return false;
                }
                if ($card['route'] === 'alerts.history'
                    && ! in_array('alerts.manage', $grantedPermissions, true)) {
                    return false;
                }
                if ($permissions === []) {
                    return false;
                }

                foreach ($permissions as $permission) {
                    if (! in_array($permission, $grantedPermissions, true)) {
                        return false;
                    }
                }

                return true;
            })
            ->map(static function (array $card): array {
                $card['url'] = route($card['route'], $card['params'] ?? []);

                return $card;
            })
            ->values();
    }
}

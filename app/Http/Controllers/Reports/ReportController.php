<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Rules\FourDigitDate;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ReportCsvValueSanitizer;
use App\Services\Reports\ReportQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, AuditLogger $audit, ReportQueryService $queries): View|JsonResponse
    {
        $this->authorizeReportAccess($audit, 'reports.view');
        $screen = $request->route('screen', 'deliveries');
        if ($screen === 'controlled') {
            $this->authorizeReportAccess($audit, 'controlled-ledger.view');
        }

        $filters = in_array($screen, ['deliveries', 'movements', 'controlled', 'kardex'], true) ? $request->validate([
            'desde' => ['nullable', 'bail', new FourDigitDate()],
            'hasta' => ['nullable', 'bail', new FourDigitDate(), 'after_or_equal:desde'],
        ]) : [];
        $stockFilters = $screen === 'stock' ? $request->validate([
            'buscar_categoria' => ['nullable', 'string', 'max:100'],
            'estado_producto' => ['nullable', Rule::in(['activo', 'inactivo', 'todos'])],
            'disponibilidad' => ['nullable', Rule::in(['con_existencias', 'sin_existencias'])],
        ]) : [];
        $movementFilters = $screen === 'movements' ? $request->validate([
            'buscar_movimiento' => ['nullable', 'string', 'max:100'],
            'tipo_movimiento' => ['nullable', Rule::in([
                'RECEPCION',
                'SALIDA',
                'TRASPASO_ENTRADA',
                'TRASPASO_SALIDA',
                'AJUSTE_ENTRADA',
                'AJUSTE_SALIDA',
            ])],
        ]) : [];
        $deliveryFilters = $screen === 'deliveries' ? $request->validate([
            'buscar_entrega' => ['nullable', 'string', 'max:100'],
        ]) : [];
        $kardexFilters = $screen === 'kardex' ? $request->validate([
            'buscar_kardex' => ['nullable', 'string', 'max:100'],
        ]) : [];
        $movements = $screen === 'movements'
            ? $queries->movements($filters + $movementFilters)->paginate(15)->withQueryString()
            : null;
        $deliveries = $screen === 'deliveries'
            ? $queries->deliveries($filters + $deliveryFilters)->paginate(15)->withQueryString()
            : null;
        $controlledMovements = $screen === 'controlled'
            ? $queries->controlledMovements($filters)->paginate(15)->withQueryString()
            : null;
        $kardexEntries = $screen === 'kardex'
            ? $queries->kardex($filters + $kardexFilters)->paginate(15)->withQueryString()
            : null;
        $stockByCategory = $screen === 'stock' ? DB::table('categorias_producto')
            ->leftJoin('productos', function ($join) use ($stockFilters) {
                $join->on('productos.categoria_id', '=', 'categorias_producto.categoria_id')
                    ->when(($stockFilters['estado_producto'] ?? 'activo') !== 'todos', fn ($query) => $query
                        ->where('productos.activo', ($stockFilters['estado_producto'] ?? 'activo') === 'activo'));
            })
            ->leftJoin('lotes', function ($join) {
                $join->on('lotes.producto_id', '=', 'productos.producto_id')
                    ->where('lotes.estado', 'ACTIVO')
                    ->whereDate('lotes.fecha_vencimiento', '>=', now()->toDateString());
            })
            ->leftJoin('inventario', function ($join) {
                $join->on('inventario.lote_id', '=', 'lotes.lote_id')
                    ->where('inventario.cantidad', '>', 0);
            })
            ->when($stockFilters['buscar_categoria'] ?? null, fn ($query, string $search) => $query->where('categorias_producto.nombre', 'like', '%'.$search.'%'))
            ->select([
                'categorias_producto.categoria_id',
                'categorias_producto.nombre',
                DB::raw('COUNT(DISTINCT CASE WHEN inventario.inventario_id IS NOT NULL THEN productos.producto_id END) as total'),
            ])
            ->groupBy('categorias_producto.categoria_id', 'categorias_producto.nombre')
            ->when(($stockFilters['disponibilidad'] ?? null) === 'con_existencias', fn ($query) => $query->havingRaw('COUNT(DISTINCT CASE WHEN inventario.inventario_id IS NOT NULL THEN productos.producto_id END) > 0'))
            ->when(($stockFilters['disponibilidad'] ?? null) === 'sin_existencias', fn ($query) => $query->havingRaw('COUNT(DISTINCT CASE WHEN inventario.inventario_id IS NOT NULL THEN productos.producto_id END) = 0'))
            ->orderBy('categorias_producto.nombre')
            ->paginate(15)
            ->withQueryString() : null;
        $titles = [
            'deliveries' => 'Reporte de entregas',
            'stock' => 'Existencias por categoría',
            'movements' => 'Movimientos de inventario',
            'controlled' => 'Movimientos controlados',
            'kardex' => 'Kardex de inventario histórico',
        ];

        $data = [
            'screen' => $screen,
            'title' => $titles[$screen] ?? 'Reportes',
            'description' => 'Movimientos de inventario, dispensaciones y existencias para auditoría',
            'canExport' => Auth::user()?->hasPermission('reports.export') ?? false,
            'movements' => $movements,
            'filters' => $filters,
            'stockByCategory' => $stockByCategory,
            'stockFilters' => $stockFilters,
            'movementFilters' => $movementFilters,
            'deliveries' => $deliveries,
            'controlledMovements' => $controlledMovements,
            'kardexEntries' => $kardexEntries,
            'deliveryFilters' => $deliveryFilters,
        ];

        return $request->expectsJson()
            ? response()->json(['data' => $data])
            : view('reports.index', $data);
    }

    public function export(Request $request, AuditLogger $audit, ReportQueryService $queries): StreamedResponse
    {
        $this->authorizeReportAccess($audit, 'reports.export');
        $data = $request->validate([
            'tipo' => ['nullable', 'in:movimientos,entregas,existencias,vencimientos,controlados,kardex'],
            'desde' => ['nullable', 'bail', new FourDigitDate()],
            'hasta' => ['nullable', 'bail', new FourDigitDate(), 'after_or_equal:desde'],
            'buscar_entrega' => ['nullable', 'string', 'max:100'],
            'buscar_kardex' => ['nullable', 'string', 'max:100'],
        ]);
        $type = $data['tipo'] ?? 'movimientos';
        if ($type === 'controlados') {
            $this->authorizeReportAccess($audit, 'controlled-ledger.view');
        }
        unset($data['tipo']);
        $filters = $data;

        $specifications = [
            'movimientos' => [
                'filename' => 'reporte-inventario.csv',
                'entity' => 'movimientos_inventario',
                'headers' => ['Fecha y hora', 'Producto', 'Código', 'Lote', 'Movimiento', 'Cantidad en unidad base', 'Unidad base', 'Cantidad original', 'Unidad ingresada', 'Factor de conversión', 'Envases completos', 'Unidades sueltas', 'Presentación', 'Ubicación', 'Responsable', 'Referencia'],
                'query' => fn () => $queries->movements($filters),
                'row' => fn ($row) => [
                    $row->fecha_hora,
                    $row->producto,
                    $row->codigo_producto,
                    $row->numero_lote,
                    $row->tipo_movimiento,
                    $row->cantidad,
                    $row->unidad_base_codigo,
                    $row->cantidad_original,
                    $row->unidad_ingresada,
                    $row->factor_conversion,
                    $row->cantidad_envases,
                    $row->unidades_sueltas,
                    $row->presentacion_nombre,
                    $row->ubicacion,
                    $row->responsable,
                    $row->referencia_tipo.' '.($row->referencia_id ?? ''),
                ],
            ],
            'entregas' => [
                'filename' => 'reporte-entregas.csv',
                'entity' => 'entregas',
                'headers' => ['Fecha y hora', 'N° entrega', 'Paciente', 'Producto', 'Código', 'Lote', 'Cantidad en unidad base', 'Unidad base', 'Responsable'],
                'query' => fn () => $queries->deliveries($filters),
                'row' => fn ($row) => [
                    $row->fecha_hora,
                    $row->entrega_id,
                    $row->paciente,
                    $row->producto,
                    $row->codigo_producto,
                    $row->numero_lote,
                    $row->cantidad,
                    $row->unidad_base_codigo,
                    $row->responsable,
                ],
            ],
            'existencias' => [
                'filename' => 'reporte-existencias.csv',
                'entity' => 'inventario',
                'headers' => ['Producto', 'Código', 'Categoría', 'Lote', 'Vencimiento', 'Estado del lote', 'Ubicación', 'Cantidad en unidad base', 'Unidad base', 'Stock mínimo'],
                'query' => fn () => $queries->inventory(),
                'row' => fn ($row) => [
                    $row->producto,
                    $row->codigo_producto,
                    $row->categoria,
                    $row->numero_lote,
                    $row->fecha_vencimiento,
                    $row->estado !== 'ACTIVO' ? $row->estado : ($row->fecha_vencimiento < now()->toDateString() ? 'VENCIDO' : 'ACTIVO'),
                    $row->ubicacion,
                    $row->cantidad,
                    $row->unidad_base_codigo,
                    $row->stock_minimo,
                ],
            ],
            'vencimientos' => [
                'filename' => 'reporte-vencimientos.csv',
                'entity' => 'lotes',
                'headers' => ['Estado', 'Producto', 'Código', 'Lote', 'Vencimiento', 'Ubicación', 'Cantidad en unidad base', 'Unidad base'],
                'query' => fn () => $queries->expiring(),
                'row' => fn ($row) => [
                    $row->fecha_vencimiento < now()->toDateString() ? 'VENCIDO' : 'POR VENCER',
                    $row->producto,
                    $row->codigo_producto,
                    $row->numero_lote,
                    $row->fecha_vencimiento,
                    $row->ubicacion,
                    $row->cantidad,
                    $row->unidad_base_codigo,
                ],
            ],
            'controlados' => [
                'filename' => 'reporte-medicamentos-controlados.csv',
                'entity' => 'movimientos_controlados',
                'headers' => ['Fecha y hora', 'Producto', 'Código', 'Lote', 'Movimiento', 'Cantidad', 'Unidad base', 'Responsable', 'Autoriza', 'Firmado', 'Referencia', 'Observación'],
                'query' => fn () => $queries->controlledMovements($filters),
                'row' => fn ($row) => [$row->fecha_hora, $row->producto, $row->codigo_producto, $row->numero_lote, $row->tipo_movimiento, $row->cantidad, $row->unidad_base_codigo, $row->responsable, $row->autorizador, $row->firmado ? 'Sí' : 'No', $row->referencia_tipo.' '.($row->referencia_id ?? ''), $row->observacion],
            ],
            'kardex' => [
                'filename' => 'reporte-kardex-historico.csv',
                'entity' => 'kardex_inventario',
                'headers' => ['Fecha de archivo', 'Producto', 'Código', 'Lote', 'Ubicación', 'Documento de recepción', 'Tipo de referencia', 'ID de referencia', 'Estado histórico', 'Cantidad final', 'Responsable', 'Observación'],
                'query' => fn () => $queries->kardex($filters),
                'row' => fn ($row) => [
                    $row->fecha_hora,
                    $row->producto,
                    $row->codigo_producto,
                    $row->numero_lote,
                    $row->ubicacion,
                    $row->documento_recepcion,
                    $row->referencia_tipo,
                    $row->referencia_id,
                    str_replace('_', ' ', $row->motivo),
                    $row->cantidad_final,
                    $row->responsable ?? 'Migración de datos',
                    $row->observacion,
                ],
            ],
        ];
        $specification = $specifications[$type];
        $query = ($specification['query'])();
        $audit->record('EXPORTACION', $specification['entity'], null, 'EXITOSO', $filters + ['tipo' => $type, 'formato' => 'CSV']);

        return response()->streamDownload(function () use ($query, $specification) {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('No fue posible iniciar el archivo de reporte.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $specification['headers'], ';');
            $query->chunk(500, function ($rows) use ($stream, $specification) {
                foreach ($rows as $row) {
                    fputcsv($stream, array_map([ReportCsvValueSanitizer::class, 'spreadsheetSafe'], ($specification['row'])($row)), ';');
                }
            });

            fclose($stream);
        }, $specification['filename'], ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeReportAccess(AuditLogger $audit, string $permission): void
    {
        $user = Auth::user();
        if (! $user instanceof Usuario || ! $user->hasPermission($permission)) {
            $audit->record('ACCESO_DENEGADO', 'reportes', null, 'RECHAZADO');
            abort(403);
        }
    }
}

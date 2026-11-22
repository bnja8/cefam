<?php


namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Alerts\StockAlertService;
use App\Support\People\PersonName;
use App\View\Presenters\DashboardChartPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        StockAlertService $alerts,
        DashboardChartPresenter $chartPresenter
    ): JsonResponse|View
    {
        $activityPeriods = [
            '7d' => ['label' => 'Últimos 7 días', 'type' => 'days', 'count' => 7],
            '30d' => ['label' => 'Últimos 30 días', 'type' => 'days', 'count' => 30],
            '1m' => ['label' => 'Mes actual', 'type' => 'months', 'count' => 1],
            '3m' => ['label' => 'Últimos 3 meses', 'type' => 'months', 'count' => 3],
            '6m' => ['label' => 'Últimos 6 meses', 'type' => 'months', 'count' => 6],
            '12m' => ['label' => 'Últimos 12 meses', 'type' => 'months', 'count' => 12],
        ];
        $filters = $request->validate([
            'periodo_actividad' => ['sometimes', 'string', Rule::in(array_keys($activityPeriods))],
        ]);
        $selectedActivityPeriod = $filters['periodo_actividad'] ?? '7d';
        $activityPeriod = $activityPeriods[$selectedActivityPeriod];
        $todayDate = now()->startOfDay();
        $today = $todayDate->toDateString();
        $activityStartDate = $activityPeriod['type'] === 'days'
            ? $todayDate->copy()->subDays($activityPeriod['count'] - 1)
            : $todayDate->copy()->startOfMonth()->subMonths($activityPeriod['count'] - 1);
        $tomorrow = $todayDate->copy()->addDay()->toDateString();
        $alerts->refreshConfiguredStockAlerts();

        $activityByDay = DB::table('entregas')
            ->where('estado', 'CONFIRMADA')
            ->where('fecha_hora', '>=', $activityStartDate->toDateString())
            ->where('fecha_hora', '<', $tomorrow)
            ->groupByRaw('DATE(fecha_hora)')
            ->get([
                DB::raw('DATE(fecha_hora) as dia'),
                DB::raw('COUNT(*) as total'),
            ])
            ->keyBy('dia');

        if ($activityPeriod['type'] === 'days') {
            $dispensationActivity = collect(range($activityPeriod['count'] - 1, 0))
                ->map( function (int $daysAgo) use ($todayDate, $activityByDay, $activityPeriod): array {
                    $date = $todayDate->copy()->subDays($daysAgo);

                    return [
                        'label' => $activityPeriod['count'] === 7 ? $date->translatedFormat('D') : $date->format('d'),
                        'date' => $date->toDateString(),
                        'total' => (int) ($activityByDay->get($date->toDateString())->total ?? 0),
                    ];
                });
        } else {
            $dispensationActivity = collect(range($activityPeriod['count'] - 1, 0))
                ->map( function (int $monthsAgo) use ($todayDate, $activityByDay): array {
                    $month = $todayDate->copy()->startOfMonth()->subMonths($monthsAgo);
                    $monthKey = $month->format('Y-m');

                    return [
                        'label' => $month->translatedFormat('M'),
                        'date' => $monthKey,
                        'total' => $activityByDay
                            ->filter( fn ($activity) => Carbon::parse($activity->dia)->format('Y-m') === $monthKey)
                            ->sum( fn ($activity) => (int) $activity->total),
                    ];
                });
        }

        $deliveries = DB::table('entregas')
            ->join('pacientes', 'pacientes.rut', '=', 'entregas.paciente_rut')
            ->join('usuarios', 'usuarios.rut', '=', 'entregas.usuario_rut')
            ->leftJoin('entrega_detalle', 'entrega_detalle.entrega_id', '=', 'entregas.entrega_id')
            ->leftJoin('productos', 'productos.producto_id', '=', 'entrega_detalle.producto_id')
            ->where('entregas.fecha_hora', '>=', $today)
            ->where('entregas.fecha_hora', '<', $tomorrow)
            ->where('entregas.estado', 'CONFIRMADA')
            ->orderByDesc('entregas.fecha_hora')
            ->limit(8)
            ->get([
                'entregas.fecha_hora',
                PersonName::expression('pacientes', 'paciente'),
                'productos.nombre as producto',
                'entrega_detalle.cantidad',
                PersonName::expression('usuarios', 'responsable'),
            ]);

        $lowStock = DB::table('alertas')
            ->join('productos', 'productos.producto_id', '=', 'alertas.producto_id')
            ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'alertas.ubicacion_id')
            ->leftJoin('stock_minimos_ubicacion',  function ($join) {
                $join->on('stock_minimos_ubicacion.producto_id', '=', 'alertas.producto_id')
                    ->on('stock_minimos_ubicacion.ubicacion_id', '=', 'alertas.ubicacion_id');
            })
            ->where('alertas.tipo_alerta', 'STOCK_CRITICO')
            ->whereNull('alertas.resuelta_at')
            ->where('alertas.atendida', false)
            ->select([
                'alertas.alerta_id',
                'alertas.producto_id',
                'productos.nombre as producto',
                'ubicaciones.nombre as ubicacion',
                'alertas.nivel_actual as stock_disponible',
                'stock_minimos_ubicacion.stock_minimo',
            ])
            ->orderBy('alertas.alerta_id')
            ->limit(5)
            ->get();

        $expiring = DB::table('inventario')
            ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
            ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
            ->where('productos.activo', true)
            ->where('lotes.estado', 'ACTIVO')
            ->where('inventario.cantidad', '>', 0)
            ->whereBetween('lotes.fecha_vencimiento', [$today, now()->addDays(30)->toDateString()])
            ->orderBy('lotes.fecha_vencimiento')
            ->limit(5)
            ->get(['productos.nombre as producto', 'lotes.numero_lote', 'lotes.fecha_vencimiento']);

        $dashboardData = [
            'stockCount' => (float) DB::table('inventario')
                ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->where('productos.activo', true)
                ->where('lotes.estado', 'ACTIVO')
                ->whereDate('lotes.fecha_vencimiento', '>=', $today)
                ->sum('inventario.cantidad'),
            'deliveries' => $deliveries,
            'dispensationActivity' => $dispensationActivity,
            'selectedActivityPeriod' => $selectedActivityPeriod,
            'activityPeriodLabel' => $activityPeriod['label'],
            'lowStock' => $lowStock,
            'expiring' => $expiring,
            'stockByCategory' => DB::table('inventario')
                ->join('lotes', 'lotes.lote_id', '=', 'inventario.lote_id')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->join('categorias_producto', 'categorias_producto.categoria_id', '=', 'productos.categoria_id')
                ->where('productos.activo', true)
                ->where('lotes.estado', 'ACTIVO')
                ->whereDate('lotes.fecha_vencimiento', '>=', $today)
                ->groupBy('categorias_producto.nombre')
                ->orderBy('categorias_producto.nombre')
                ->get(['categorias_producto.nombre', DB::raw('SUM(inventario.cantidad) as total')]),
            'mostDispensed' => DB::table('entrega_detalle')
                ->join('entregas', 'entregas.entrega_id', '=', 'entrega_detalle.entrega_id')
                ->join('productos', 'productos.producto_id', '=', 'entrega_detalle.producto_id')
                ->where('entregas.estado', 'CONFIRMADA')
                ->whereDate('entregas.fecha_hora', '>=', now()->subDays(29)->toDateString())
                ->whereDate('entregas.fecha_hora', '<=', $today)
                ->groupBy('productos.producto_id', 'productos.nombre')
                ->orderByDesc(DB::raw('SUM(entrega_detalle.cantidad)'))
                ->limit(5)
                ->get([
                    'productos.nombre',
                    DB::raw('SUM(entrega_detalle.cantidad) as cantidad'),
                ]),
        ];

        if ($request->expectsJson() || $request->routeIs('api.*')) {
            return response()->json(['data' => $dashboardData]);
        }

        return view('dashboard.index', array_merge(
            $dashboardData,
            $chartPresenter->present(
                $dashboardData['dispensationActivity'],
                $dashboardData['stockByCategory'],
                $dashboardData['mostDispensed'],
                $dashboardData['stockCount']
            )
        ));
    }
}

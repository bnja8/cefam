<?php

namespace App\Services\Modules;

use App\Support\People\PersonName;
use App\Support\People\Rut;
use Illuminate\Support\Facades\DB;

class ModuleQueryService
{
    public function rows(string $module, array $filters): mixed
    {
        return match ($module) {
            'recetas' => DB::table('recetas')->join('pacientes', 'pacientes.rut', '=', 'recetas.paciente_rut')
                ->join('profesionales_salud', 'profesionales_salud.rut', '=', 'recetas.profesional_rut')
                ->orderByDesc('recetas.fecha_emision')->limit(100)
                ->get(['recetas.numero_receta', PersonName::expression('pacientes', 'paciente'), PersonName::expression('profesionales_salud', 'profesional'), 'recetas.tipo_receta', 'recetas.fecha_vencimiento', 'recetas.estado']),
            'programas' => DB::table('programas_salud')->orderBy('nombre')
                ->get(['nombre', 'descripcion', 'activo']),
            'cenabast' => DB::table('remesas')->join('usuarios', 'usuarios.rut', '=', 'remesas.usuario_rut')
                ->orderByDesc('remesas.fecha_recepcion')->limit(100)
                ->get(['remesas.codigo_remesa', 'remesas.origen', 'remesas.fecha_recepcion', PersonName::expression('usuarios', 'responsable')]),
            'personas-autorizadas' => DB::table('personas_autorizadas')->join('pacientes', 'pacientes.rut', '=', 'personas_autorizadas.paciente_rut')
                ->orderBy('personas_autorizadas.apellidoPaterno')->orderBy('personas_autorizadas.apellidoMaterno')->orderBy('personas_autorizadas.nombre')
                ->get(['personas_autorizadas.rut', PersonName::expression('personas_autorizadas', 'persona_autorizada'), 'personas_autorizadas.vinculo', PersonName::expression('pacientes', 'paciente'), 'personas_autorizadas.activo']),
            'reportes' => DB::table('movimientos_inventario')->join('lotes', 'lotes.lote_id', '=', 'movimientos_inventario.lote_id')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->join('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'movimientos_inventario.ubicacion_id')
                ->join('usuarios', 'usuarios.rut', '=', 'movimientos_inventario.usuario_rut')
                ->orderByDesc('movimientos_inventario.fecha_hora')->limit(100)
                ->get([
                    'movimientos_inventario.fecha_hora',
                    'productos.nombre as producto',
                    'lotes.numero_lote',
                    'movimientos_inventario.tipo_movimiento',
                    'movimientos_inventario.cantidad',
                    'movimientos_inventario.cantidad_original',
                    'movimientos_inventario.unidad_ingresada',
                    'movimientos_inventario.factor_conversion',
                    'movimientos_inventario.presentacion_nombre',
                    'ubicaciones.nombre as ubicacion',
                    PersonName::expression('usuarios', 'responsable'),
                ]),
            'administracion' => DB::table('usuarios')->leftJoin('usuario_rol', 'usuario_rol.usuario_rut', '=', 'usuarios.rut')
                ->leftJoin('roles', 'roles.rol_id', '=', 'usuario_rol.rol_id')
                ->when($filters['estado'] === 'activo', fn ($query) => $query->where('usuarios.activo', true))
                ->when($filters['estado'] === 'inactivo', fn ($query) => $query->where('usuarios.activo', false))
                ->when($filters['rol_id'], fn ($query, $roleId) => $query->where('roles.rol_id', $roleId))
                ->when($filters['buscar'], function ($query, string $search) {
                    $term = '%'.$search.'%';
                    $rutTerm = '%'.Rut::limpiarRut($search).'%';
                    $query->where(function ($query) use ($search, $term, $rutTerm) {
                        $query->where('usuarios.rut', 'like', $rutTerm)
                            ->orWhere('usuarios.telefono', 'like', $term)
                            ->orWhere('roles.nombre', 'like', $term)
                            ->orWhere(function ($nameQuery) use ($search) {
                                PersonName::whereMatches($nameQuery, 'usuarios', $search);
                            });
                    });
                })
                ->orderBy('usuarios.apellidoPaterno')->orderBy('usuarios.apellidoMaterno')->orderBy('usuarios.nombre')
                ->paginate(15, ['usuarios.rut', PersonName::expression('usuarios', 'nombre_completo'), 'usuarios.telefono', 'usuarios.activo', 'roles.nombre as rol'])
                ->withQueryString(),
            'libro-digital' => DB::table('movimientos_controlados')->join('productos', 'productos.producto_id', '=', 'movimientos_controlados.producto_id')
                ->join('lotes', 'lotes.lote_id', '=', 'movimientos_controlados.lote_id')
                ->join('usuarios', 'usuarios.rut', '=', 'movimientos_controlados.usuario_responsable_rut')
                ->orderByDesc('movimientos_controlados.fecha_hora')->limit(100)
                ->get(['movimientos_controlados.fecha_hora', 'productos.nombre as producto', 'lotes.numero_lote', 'movimientos_controlados.tipo_movimiento', 'movimientos_controlados.cantidad', PersonName::expression('usuarios', 'responsable'), 'movimientos_controlados.firmado']),
            'alertas' => DB::table('alertas')->leftJoin('lotes', 'lotes.lote_id', '=', 'alertas.lote_id')
                ->leftJoin('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->leftJoin('ubicaciones', 'ubicaciones.ubicacion_id', '=', 'alertas.ubicacion_id')
                ->where('alertas.atendida', false)->orderByDesc('alertas.fecha_generacion')->limit(100)
                ->get(['alertas.tipo_alerta', 'alertas.mensaje', 'productos.nombre as producto', 'lotes.numero_lote', 'ubicaciones.nombre as ubicacion', 'alertas.fecha_generacion']),
        };
    }
}

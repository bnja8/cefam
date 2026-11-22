<?php


namespace App\Services\ControlledLedger;

use App\Services\Audit\AuditLogger;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ControlledInventoryReconciliationService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function reconcile(array $data, Usuario $user): bool
    {
        if (! isset($data['password'], $data['lote_id'], $data['producto_id'], $data['ubicacion_id'])) {
            throw ValidationException::withMessages([
                'lote_id' => 'Los datos del lote y de la ubicación son obligatorios.',
            ]);
        }

        if (! isset($data['cantidad_contada'])
            || ! is_numeric($data['cantidad_contada'])
            || ! is_finite((float) $data['cantidad_contada'])
            || (float) $data['cantidad_contada'] < 0
        ) {
            throw ValidationException::withMessages([
                'cantidad_contada' => 'Ingresa una cantidad física válida igual o mayor que cero.',
            ]);
        }

        if (! Hash::check($data['password'], $user->getAuthPassword())) {
            $this->audit->record('CONCILIACION_CONTROLADO_RECHAZADA', 'alertas', null, 'RECHAZADO');
            throw ValidationException::withMessages(['password' => 'La clave de firma no es correcta.']);
        }

        return DB::transaction( function () use ($data, $user) {
            $lot = DB::table('lotes')
                ->join('productos', 'productos.producto_id', '=', 'lotes.producto_id')
                ->where('lotes.lote_id', $data['lote_id'])
                ->where('productos.producto_id', $data['producto_id'])
                ->where('productos.es_controlado', true)
                ->where('productos.activo', true)
                ->lockForUpdate()
                ->first(['lotes.lote_id', 'productos.nombre', 'lotes.numero_lote']);

            if (! $lot) {
                throw ValidationException::withMessages(['lote_id' => 'El lote no corresponde a un medicamento controlado activo.']);
            }

            $location = DB::table('ubicaciones')
                ->where('ubicacion_id', $data['ubicacion_id'])
                ->lockForUpdate()
                ->first(['nombre']);
            if (! $location) {
                throw ValidationException::withMessages(['ubicacion_id' => 'La ubicación seleccionada ya no está disponible.']);
            }

            $inventory = DB::table('inventario')
                ->where('lote_id', $lot->lote_id)
                ->where('ubicacion_id', $data['ubicacion_id'])
                ->lockForUpdate()
                ->first();
            $systemQuantity = (float) ($inventory->cantidad ?? 0);
            $countedQuantity = (float) $data['cantidad_contada'];
            $openAlert = DB::table('alertas')
                ->where('tipo_alerta', 'DESCUADRE_CONTROLADO')
                ->where('lote_id', $lot->lote_id)
                ->where('ubicacion_id', $data['ubicacion_id'])
                ->where('atendida', false)
                ->lockForUpdate()
                ->first();

            if (abs($systemQuantity - $countedQuantity) < 0.00001) {
                if ($openAlert) {
                    DB::table('alertas')->where('alerta_id', $openAlert->alerta_id)->update([
                        'atendida' => true,
                        'atendida_por_rut' => $user->rut,
                        'atendida_at' => now(),
                    ]);
                }

                $this->audit->record('CONTEO_CONTROLADO', 'inventario', $inventory->inventario_id ?? null, 'SIN_DESCUADRE', [
                    'lote_id' => $lot->lote_id,
                    'ubicacion_id' => $data['ubicacion_id'],
                    'cantidad_sistema' => $systemQuantity,
                    'cantidad_contada' => $countedQuantity,
                    'observacion' => $data['observacion'],
                ]);

                return false;
            }

            $difference = $countedQuantity - $systemQuantity;
            $message = "Descuadre en {$lot->nombre}, lote {$lot->numero_lote}, {$location->nombre}: sistema {$systemQuantity}; conteo físico {$countedQuantity}; diferencia {$difference}.";
            $alertData = [
                'nivel_actual' => $countedQuantity,
                'mensaje' => $message,
            ];

            if ($openAlert) {
                DB::table('alertas')->where('alerta_id', $openAlert->alerta_id)->update($alertData);
                $alertId = $openAlert->alerta_id;
            } else {
                $alertId = DB::table('alertas')->insertGetId($alertData + [
                    'tipo_alerta' => 'DESCUADRE_CONTROLADO',
                    'lote_id' => $lot->lote_id,
                    'ubicacion_id' => $data['ubicacion_id'],
                    'atendida' => false,
                ]);
            }

            $this->audit->record('CONTEO_CONTROLADO', 'alertas', $alertId, 'DESCUADRE_DETECTADO', [
                'lote_id' => $lot->lote_id,
                'ubicacion_id' => $data['ubicacion_id'],
                'cantidad_sistema' => $systemQuantity,
                'cantidad_contada' => $countedQuantity,
                'diferencia' => $difference,
                'observacion' => $data['observacion'],
            ]);

            return true;
        });
    }
}

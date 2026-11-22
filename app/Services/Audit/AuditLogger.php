<?php


namespace App\Services\Audit;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public function record(string $action, string $entity, ?int $recordId, string $result, array $details = []): void
    {
        DB::table('auditoria')->insert([
            'usuario_rut' => Auth::id(),
            'accion' => $action,
            'entidad' => $entity,
            'registro_id' => $recordId,
            'resultado' => $result,
            'detalle' => json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip_origen' => request()->ip(),
            'fecha_hora' => now(),
        ]);
    }
}

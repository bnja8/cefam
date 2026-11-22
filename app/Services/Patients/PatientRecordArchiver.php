<?php

namespace App\Services\Patients;

use Illuminate\Support\Facades\DB;

class PatientRecordArchiver
{
    public function archiveExpired(): void
    {
        $cutoff = now()->subDays(30);

        DB::transaction(function () use ($cutoff) {
            DB::table('recetas')
                ->where('estado_ficha', '!=', 'ARCHIVADA')
                ->where('fecha_registro', '<=', $cutoff)
                ->update(['estado_ficha' => 'ARCHIVADA']);

            DB::table('entregas')
                ->where('estado_ficha', '!=', 'ARCHIVADA')
                ->where('fecha_hora', '<=', $cutoff)
                ->update(['estado_ficha' => 'ARCHIVADA']);
        });
    }
}

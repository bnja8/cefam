<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    protected $table = 'unidades_medida';

    protected $primaryKey = 'unidad_medida_id';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'tipo', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}

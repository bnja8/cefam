<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PrincipioActivo extends Model
{
    protected $table = 'principios_activos';

    protected $primaryKey = 'principio_activo_id';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(
            Producto::class,
            'productos_principios_activos',
            'principio_activo_id',
            'producto_id',
            'principio_activo_id',
            'producto_id'
        )->withPivot([
            'concentracion_cantidad',
            'concentracion_unidad_id',
            'concentracion_base_cantidad',
            'concentracion_base_unidad_id',
        ]);
    }
}

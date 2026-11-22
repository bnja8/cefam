<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'productos';

    protected $primaryKey = 'producto_id';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'es_medicamento' => 'boolean',
            'es_controlado' => 'boolean',
            'activo' => 'boolean',
            'contenido_envase_cantidad' => 'decimal:3',
            'presentaciones_comerciales' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function formaFarmaceutica(): BelongsTo
    {
        return $this->belongsTo(FormaFarmaceutica::class, 'forma_farmaceutica_id', 'forma_farmaceutica_id');
    }

    public function principiosActivos(): BelongsToMany
    {
        return $this->belongsToMany(
            PrincipioActivo::class,
            'productos_principios_activos',
            'producto_id',
            'principio_activo_id',
            'producto_id',
            'principio_activo_id'
        )->withPivot([
            'concentracion_cantidad',
            'concentracion_unidad_id',
            'concentracion_base_cantidad',
            'concentracion_base_unidad_id',
        ]);
    }

    public function unidadContenido(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'contenido_envase_unidad_id', 'unidad_medida_id');
    }

    public function unidadBaseInventario(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_base_inventario_id', 'unidad_medida_id');
    }

    public function presentaciones(): HasMany
    {
        return $this->hasMany(PresentacionProducto::class, 'producto_id', 'producto_id');
    }

    public function codigosBarra(): HasMany
    {
        return $this->hasMany(CodigoBarraProducto::class, 'producto_id', 'producto_id');
    }
}

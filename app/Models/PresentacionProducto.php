<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresentacionProducto extends Model
{
    protected $table = 'presentaciones_producto';

    protected $primaryKey = 'presentacion_id';

    public $timestamps = false;

    protected $fillable = ['nombre', 'factor_conversion', 'activo'];

    protected function casts(): array
    {
        return [
            'factor_conversion' => 'decimal:4',
            'activo' => 'boolean',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'producto_id');
    }
}

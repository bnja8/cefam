<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodigoBarraProducto extends Model
{
    protected $table = 'productos_codigo_barra';

    protected $primaryKey = 'codigo_barra_id';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'producto_id');
    }
}

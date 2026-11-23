<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'rol_id';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'permissions_initialized' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            Usuario::class,
            'usuario_rol',
            'rol_id',
            'usuario_rut',
            'rol_id',
            'rut'
        )->withPivot('asignado_at');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'permission_role',
            'rol_id',
            'permission_id',
            'rol_id',
            'permission_id'
        );
    }
}

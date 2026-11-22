<?php


namespace App\Models;

use App\Support\People\PersonName;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'rut';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $hidden = ['password_hash'];

    public function getNombreCompletoAttribute(): string
    {
        return PersonName::format($this);
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'creado_at' => 'datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Rol::class,
            'usuario_rol',
            'usuario_rut',
            'rol_id',
            'rut',
            'rol_id'
        )->withPivot('asignado_at');
    }

    public function hasAnyRole(array $names): bool
    {
        return $names !== []
            && $this->roles()->whereIn('nombre', $names)->exists();
    }
}

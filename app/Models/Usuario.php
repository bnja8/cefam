<?php


namespace App\Models;

use App\Support\People\PersonName;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        )->where('roles.activo', true)->withPivot('asignado_at');
    }

    public function personalSalud(): HasOne
    {
        return $this->hasOne(PersonalSalud::class, 'usuario_rut', 'rut');
    }

    public function hasAnyRole(array $names): bool
    {
        return $names !== []
            && $this->roles()->whereIn('nombre', $names)->exists();
    }

    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->effectivePermissions(), true);
    }

    public function hasAnyPermission(array $slugs): bool
    {
        return array_intersect($slugs, $this->effectivePermissions()) !== [];
    }

    public function effectivePermissions(): array
    {
        if ($this->hasAnyRole(['Administrador'])) {
            return PermissionCatalog::defaultsForRole('Administrador');
        }

        return $this->roles()
            ->with('permissions:permission_id,slug')
            ->get()
            ->flatMap(fn (Rol $role) => $role->permissions->pluck('slug')
                ->merge($role->permissions_initialized ? [] : PermissionCatalog::defaultsForRole($role->nombre)))
            ->unique()
            ->values()
            ->all();
    }
}

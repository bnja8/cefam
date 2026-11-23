<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $primaryKey = 'permission_id';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['locked' => 'boolean'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Rol::class,
            'permission_role',
            'permission_id',
            'rol_id',
            'permission_id',
            'rol_id'
        );
    }
}

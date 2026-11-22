<?php


namespace App\Models;

use App\Support\People\PersonName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Paciente extends Model
{
    protected $table = 'pacientes';

    protected $primaryKey = 'rut';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    public function getNombreCompletoAttribute(): string
    {
        return PersonName::format($this);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'fecha_nacimiento' => 'date',
        ];
    }
}

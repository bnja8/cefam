<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormaFarmaceutica extends Model
{
    protected $table = 'formas_farmaceuticas';

    protected $primaryKey = 'forma_farmaceutica_id';

    public $timestamps = false;

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}

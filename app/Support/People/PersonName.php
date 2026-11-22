<?php
// Normaliza nombres para su consulta y presentación.


namespace App\Support\People;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use LogicException;

class PersonName
{
    // Construye la expresión de consulta para el nombre.
    public static function expression(string $table, string $alias): Expression
    {
        $columns = [
            "{$table}.nombre",
            "{$table}.apellidoPaterno",
            "{$table}.apellidoMaterno",
        ];

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $parts = implode(', ', array_map( fn (string $column) => "NULLIF({$column}, '')", $columns));
            $sql = "CONCAT_WS(' ', {$parts}) AS {$alias}";
        } elseif ($driver === 'sqlite') {
            $sql = "COALESCE(NULLIF({$columns[0]}, ''), '')";
            foreach (array_slice($columns, 1) as $index => $column) {
                $previous = $columns[$index];
                $sql .= " || CASE WHEN NULLIF({$previous}, '') IS NOT NULL AND NULLIF({$column}, '') IS NOT NULL THEN ' ' ELSE '' END";
                $sql .= " || COALESCE(NULLIF({$column}, ''), '')";
            }
            $sql = "TRIM({$sql}) AS {$alias}";
        } else {
            throw new LogicException("Unsupported database driver [{$driver}] for person names.");
        }

        return DB::raw($sql);
    }

    // Formatea el valor para presentarlo de forma consistente.
    public static function format(object $person): string
    {
        return implode(' ', array_filter(array_map(
            // Transforma cada elemento de la colección.
            fn (string $field) => trim((string) ($person->{$field} ?? '')),
            ['nombre', 'apellidoPaterno', 'apellidoMaterno']
        ),  fn (string $part) => $part !== ''));
    }

    // Filtra los registros que coinciden con el término.
    public static function whereMatches(QueryBuilder|EloquentBuilder $query, string $table, string $term): void
    {
        $tokens = preg_split('/\s+/', trim($term), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($tokens as $token) {
            $like = '%'.$token.'%';
            $query->where( function ($nameQuery) use ($table, $like) {
                $nameQuery->where("{$table}.nombre", 'like', $like)
                    ->orWhere("{$table}.apellidoPaterno", 'like', $like)
                    ->orWhere("{$table}.apellidoMaterno", 'like', $like);
            });
        }
    }
}

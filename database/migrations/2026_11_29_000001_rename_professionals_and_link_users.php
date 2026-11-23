<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('profesionales_salud') && ! Schema::hasTable('personal_salud')) {
            Schema::rename('profesionales_salud', 'personal_salud');
        } elseif (Schema::hasTable('profesionales_salud') && Schema::hasTable('personal_salud')) {
            $legacyStaff = DB::table('profesionales_salud')
                ->orderBy('rut')
                ->get(['rut', 'nombre', 'apellidoPaterno', 'apellidoMaterno', 'tipo_profesional', 'activo']);
            foreach ($legacyStaff as $staff) {
                DB::table('personal_salud')->insertOrIgnore((array) $staff);
            }

            if (Schema::hasTable('recetas')) {
                if (Schema::hasForeignKey('recetas', 'fk_recetas_profesional_rut')) {
                    Schema::table('recetas', fn (Blueprint $table) => $table->dropForeign('fk_recetas_profesional_rut'));
                }
                Schema::table('recetas', fn (Blueprint $table) => $table
                    ->foreign('profesional_rut', 'fk_recetas_profesional_rut')
                    ->references('rut')
                    ->on('personal_salud'));
            }

            Schema::drop('profesionales_salud');
        }

        if (Schema::hasTable('personal_salud')) {
            Schema::table('personal_salud', function (Blueprint $table): void {
                if (! Schema::hasColumn('personal_salud', 'especialidad')) {
                    $table->string('especialidad', 100)->nullable();
                }
                if (! Schema::hasColumn('personal_salud', 'usuario_rut')) {
                    $table->string('usuario_rut', 12)->nullable();
                }
            });

            if (! Schema::hasIndex('personal_salud', 'personal_salud_usuario_rut_unique')) {
                Schema::table('personal_salud', fn (Blueprint $table) => $table->unique('usuario_rut', 'personal_salud_usuario_rut_unique'));
            }
            if (! Schema::hasForeignKey('personal_salud', 'personal_salud_usuario_rut_foreign')) {
                Schema::table('personal_salud', fn (Blueprint $table) => $table
                    ->foreign('usuario_rut', 'personal_salud_usuario_rut_foreign')
                    ->references('rut')
                    ->on('usuarios')
                    ->nullOnDelete());
            }
        }

        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table): void {
                if (! Schema::hasColumn('roles', 'slug')) {
                    $table->string('slug', 80)->nullable();
                }
                if (! Schema::hasColumn('roles', 'permissions_initialized')) {
                    $table->boolean('permissions_initialized')->default(false);
                }
            });

            foreach (DB::table('roles')->whereNull('slug')->get(['rol_id', 'nombre']) as $role) {
                DB::table('roles')->where('rol_id', $role->rol_id)->update([
                    'slug' => \Illuminate\Support\Str::slug($role->nombre).'-'.$role->rol_id,
                ]);
            }
            if (! Schema::hasIndex('roles', 'roles_slug_unique')) {
                Schema::table('roles', fn (Blueprint $table) => $table->unique('slug', 'roles_slug_unique'));
            }
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table): void {
                $table->bigIncrements('permission_id');
                $table->string('slug', 120)->unique();
                $table->string('name', 120);
                $table->string('module', 60);
                $table->string('module_name', 100);
                $table->string('action', 60);
                $table->boolean('locked')->default(false);
            });
        }

        if (! Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table): void {
                $table->unsignedBigInteger('rol_id');
                $table->unsignedBigInteger('permission_id');
                $table->primary(['rol_id', 'permission_id']);
                $table->foreign('rol_id')->references('rol_id')->on('roles')->cascadeOnDelete();
                $table->foreign('permission_id')->references('permission_id')->on('permissions')->cascadeOnDelete();
            });
        }

        $submoduleReadPermissions = [
            'programs.affiliations.view',
            'supplies.requests.view',
            'inventory.catalog.view',
            'inventory.products.view',
        ];
        foreach (\App\Support\Authorization\PermissionCatalog::definitions()
            ->reject(static fn (array $permission): bool => in_array($permission['slug'], $submoduleReadPermissions, true)) as $permission) {
            DB::table('permissions')->insertOrIgnore(collect($permission)->only([
                'slug', 'name', 'module', 'module_name', 'action', 'locked',
            ])->all());
        }

        foreach (DB::table('roles')->get(['rol_id', 'nombre', 'permissions_initialized']) as $role) {
            if ($role->permissions_initialized) {
                continue;
            }
            foreach (\App\Support\Authorization\PermissionCatalog::defaultsForRole($role->nombre) as $slug) {
                $permissionId = DB::table('permissions')->where('slug', $slug)->value('permission_id');
                if ($permissionId !== null) {
                    DB::table('permission_role')->insertOrIgnore([
                        'rol_id' => $role->rol_id,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
            DB::table('roles')->where('rol_id', $role->rol_id)->update(['permissions_initialized' => true]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');

        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table): void {
                if (Schema::hasIndex('roles', 'roles_slug_unique')) {
                    $table->dropUnique('roles_slug_unique');
                }
                $columns = array_values(array_filter(
                    ['slug', 'permissions_initialized'],
                    static fn (string $column): bool => Schema::hasColumn('roles', $column)
                ));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('personal_salud')) {
            Schema::table('personal_salud', function (Blueprint $table): void {
                if (Schema::hasForeignKey('personal_salud', 'personal_salud_usuario_rut_foreign')) {
                    $table->dropForeign('personal_salud_usuario_rut_foreign');
                }
                if (Schema::hasIndex('personal_salud', 'personal_salud_usuario_rut_unique')) {
                    $table->dropUnique('personal_salud_usuario_rut_unique');
                }
                $columns = array_values(array_filter(
                    ['especialidad', 'usuario_rut'],
                    static fn (string $column): bool => Schema::hasColumn('personal_salud', $column)
                ));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
            Schema::rename('personal_salud', 'profesionales_salud');
        }
    }
};

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationsCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_can_run_over_the_latest_schema_without_duplicate_objects(): void
    {
        DB::table('migrations')->delete();

        $exitCode = Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertTrue(Schema::hasTable('personas_autorizadas'));
        $this->assertTrue(Schema::hasColumn('entregas', 'paciente_nombre_snapshot'));
        $this->assertTrue(Schema::hasTable('jobs'));
    }
}

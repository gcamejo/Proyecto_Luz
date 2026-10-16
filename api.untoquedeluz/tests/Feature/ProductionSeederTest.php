<?php

namespace Tests\Feature;

use App\Models\Yoguini;
use Carbon\Carbon;
use Database\Seeders\AdminInicialSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\FeriadosArgentinaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_seeder_is_idempotent_for_current_and_following_year(): void
    {
        Carbon::setTestNow('2026-10-16 12:00:00');

        try {
            $this->seed(FeriadosArgentinaSeeder::class);

            $this->assertDatabaseHas('feriados', ['fecha' => '2026-06-15']);
            $this->assertDatabaseHas('feriados', ['fecha' => '2026-11-23']);
            $this->assertDatabaseHas('feriados', ['fecha' => '2027-06-21']);
            $this->assertDatabaseHas('feriados', ['fecha' => '2027-08-16']);
            $this->assertDatabaseHas('feriados', ['fecha' => '2027-10-11']);
            $this->assertDatabaseCount('feriados', 32);

            $this->seed(FeriadosArgentinaSeeder::class);

            $this->assertDatabaseCount('feriados', 32);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_initial_admin_seeder_creates_once_and_does_not_reset_existing_password(): void
    {
        config([
            'initial_admin.email' => 'initial-admin@example.test',
            'initial_admin.password' => 'secure-initial-password',
        ]);

        $this->seed(AdminInicialSeeder::class);
        $admin = Yoguini::where('email', 'initial-admin@example.test')->firstOrFail();

        $this->assertSame('Admin', $admin->perfil);
        $this->assertTrue(Hash::check('secure-initial-password', $admin->password));
        $admin->password = Hash::make('password-changed-after-creation');
        $admin->save();

        $this->seed(AdminInicialSeeder::class);

        $this->assertDatabaseCount('yoguinis', 1);
        $this->assertTrue(Hash::check('password-changed-after-creation', $admin->fresh()->password));
    }

    public function test_configuration_seeder_sets_twenty_four_hour_notice_and_is_idempotent(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ConfiguracionSeeder::class);

        $this->assertDatabaseCount('configuracion', 1);
        $this->assertDatabaseHas('configuracion', [
            'clave' => 'horas_aviso_minimas',
            'valor' => '24',
        ]);
    }
}
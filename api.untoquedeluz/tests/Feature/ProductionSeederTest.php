<?php

namespace Tests\Feature;

use App\Models\Yoguini;
use Carbon\Carbon;
use Database\Seeders\AdminInicialSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\FeriadosArgentinaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
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

    public function test_timezone_and_production_logging_configuration_are_documented_in_env_examples(): void
    {
        $this->assertSame('America/Argentina/Buenos_Aires', config('app.timezone'));

        $development = file_get_contents(base_path('.env.example'));
        $production = file_get_contents(base_path('.env.production.example'));
        $this->assertStringContainsString('APP_TIMEZONE=America/Argentina/Buenos_Aires', $development);
        $this->assertStringContainsString('SANCTUM_EXPIRATION_MINUTES=43200', $development);
        $this->assertStringContainsString('SANCTUM_TOKEN_TTL_MINUTES=43200', $development);
        $this->assertStringContainsString('APP_ENV=production', $production);
        $this->assertStringContainsString('APP_DEBUG=false', $production);
        $this->assertStringContainsString('APP_TIMEZONE=America/Argentina/Buenos_Aires', $production);
        $this->assertStringContainsString('LOG_CHANNEL=daily', $production);
        $this->assertStringContainsString('LOG_DAYS=7', $production);
        $this->assertStringContainsString('LOG_LEVEL=warning', $production);
        $this->assertStringContainsString('SANCTUM_EXPIRATION_MINUTES=43200', $production);
        $this->assertStringContainsString('SANCTUM_TOKEN_TTL_MINUTES=43200', $production);
    }

    public function test_credit_expiration_command_is_registered_and_idempotent_in_app_timezone(): void
    {
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:30:00', config('app.timezone')));

        try {
            $this->assertArrayHasKey('booking:expire-credits', Artisan::all());

            $expired = \App\Models\Recuperacion::factory()->create([
                'estado' => 'disponible',
                'vence_en' => '2026-09-30',
            ]);
            $today = \App\Models\Recuperacion::factory()->create([
                'estado' => 'disponible',
                'vence_en' => '2026-10-01',
            ]);

            $this->artisan('booking:expire-credits')->assertExitCode(0);
            $this->artisan('booking:expire-credits')->assertExitCode(0);

            $this->assertSame('vencida', $expired->fresh()->estado);
            $this->assertSame('disponible', $today->fresh()->estado);
        } finally {
            Carbon::setTestNow();
        }
    }
}
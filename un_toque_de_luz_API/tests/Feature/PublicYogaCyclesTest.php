<?php

namespace Tests\Feature;

use App\Models\Clase;
use App\Models\Ciclo;
use App\Models\Horario;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicYogaCyclesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-10-09 08:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_public_endpoint_returns_only_current_cycles_with_future_classes_and_no_personal_data()
    {
        $visible = $this->cycle('Visible', '2026-10-01', '2026-10-31');
        $this->makeClass($visible, '2026-10-13', '10:00:00');

        $paused = $this->cycle('Paused', '2026-10-01', '2026-10-31', false);
        $this->makeClass($paused, '2026-10-13', '11:00:00');

        $archived = $this->cycle('Archived', '2026-10-01', '2026-10-31');
        $this->makeClass($archived, '2026-10-13', '12:00:00');
        $archived->delete();

        $expired = $this->cycle('Expired', '2026-09-01', '2026-10-08');
        $this->makeClass($expired, '2026-10-13', '13:00:00');

        $withoutClasses = $this->cycle('Without classes', '2026-10-01', '2026-10-31');

        $pastOnly = $this->cycle('Past only', '2026-10-01', '2026-10-31');
        $this->makeClass($pastOnly, '2026-10-08', '10:00:00');

        $cancelledOnly = $this->cycle('Cancelled only', '2026-10-01', '2026-10-31');
        $this->makeClass($cancelledOnly, '2026-10-13', '10:00:00', ['estado' => 'cancelada']);

        $response = $this->getJson('/api/public/yoga/cycles')->assertOk()->assertJsonCount(1, 'cycles');

        $cycleData = $response->json('cycles.0');
        $this->assertSame('Visible', $cycleData['nombre']);
        $this->assertTrue($cycleData['hay_lugares']);
        $this->assertSame('martes', $cycleData['horarios'][0]['dia']);
        $this->assertSame('10:00', $cycleData['horarios'][0]['hora_inicio']);
        $this->assertArrayNotHasKey('reservas', $cycleData);
        $this->assertArrayNotHasKey('inscripciones', $cycleData);
        $this->assertArrayNotHasKey('email', $cycleData);
        $this->assertArrayNotHasKey('profesor', $cycleData['horarios'][0]);
        $this->assertArrayNotHasKey('alumnos', $cycleData['horarios'][0]);
    }

    public function test_public_endpoint_needs_no_authentication_and_registration_route_is_throttled()
    {
        $this->getJson('/api/public/yoga/cycles')->assertOk();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/yoguinis', [])->assertUnprocessable();
        }

        $this->postJson('/api/yoguinis', [])->assertStatus(429);
    }

    public function test_public_cycles_endpoint_has_its_own_throttle()
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->getJson('/api/public/yoga/cycles')->assertOk();
        }

        $this->getJson('/api/public/yoga/cycles')->assertStatus(429);
    }

    private function cycle($name, $start, $end, $active = true)
    {
        return Ciclo::create([
            'nombre' => $name,
            'fecha_inicio' => $start,
            'fecha_fin' => $end,
            'clases_por_semana' => 1,
            'precio' => '1000.00',
            'activo' => $active,
        ]);
    }

    private function makeClass(Ciclo $cycle, $date, $start, array $attributes = [])
    {
        $schedule = Horario::create([
            'dia_semana' => Carbon::parse($date)->dayOfWeekIso,
            'hora_inicio' => $start,
            'duracion_min' => 60,
            'nivel' => 'General',
            'profesor' => 'Private instructor detail',
            'cupo' => 8,
            'activo' => true,
        ]);

        return Clase::create(array_merge([
            'ciclo_id' => $cycle->id,
            'horario_id' => $schedule->id,
            'fecha' => $date,
            'hora_inicio' => $start,
            'duracion_min' => 60,
            'nivel' => 'General',
            'profesor' => 'Private instructor detail',
            'cupo' => 8,
            'estado' => 'programada',
        ], $attributes));
    }
}

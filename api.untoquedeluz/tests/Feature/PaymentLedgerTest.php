<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Ciclo;
use App\Models\Clase;
use App\Models\Horario;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\PagoAplicacion;
use App\Models\Reserva;
use App\Models\Yoguini;
use App\Http\Resources\Payments\CargoResource;
use App\Services\CargoService;
use App\Services\GenerateCycleClasses;
use App\Services\InscripcionService;
use App\Services\PagoService;
use App\Services\ReservaService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentLedgerTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00', config('app.timezone')));
        $this->admin = Yoguini::factory()->create(['perfil' => 'Admin']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_enrollment_creates_one_charge_without_touching_capacity()
    {
        [$cycle, $schedule, $class] = $this->openCycle('12500.50');
        $student = Yoguini::factory()->create();

        $enrollment = app(InscripcionService::class)->enroll($student, $cycle, [$schedule->id]);

        $charge = Cargo::where('inscripcion_id', $enrollment->id)->firstOrFail();
        $this->assertSame($student->id, (int) $charge->user_id);
        $this->assertSame(1250050, Money::cents($charge->monto_final));
        $this->assertSame('2026-10-01', $charge->periodo->toDateString());
        $this->assertSame(Cargo::PENDIENTE, $charge->estado);
        $this->assertSame(10, (int) $class->fresh()->cupo);
        $this->assertSame(1, Reserva::where('clase_id', $class->id)->count());

        app(CargoService::class)->createForEnrollment($enrollment, $cycle);
        $this->assertSame(1, Cargo::where('inscripcion_id', $enrollment->id)->count());
    }

    public function test_cycle_without_price_creates_no_charge()
    {
        [$cycle, $schedule] = $this->openCycle('0.00');

        app(InscripcionService::class)->enroll(Yoguini::factory()->create(), $cycle, [$schedule->id]);

        $this->assertSame(0, Cargo::count());
    }

    public function test_generating_missing_cycle_charges_is_idempotent_and_skips_dropped_enrollments()
    {
        $cycle = Ciclo::create([
            'nombre' => 'Octubre', 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-31',
            'clases_por_semana' => 1, 'precio' => '8000.00', 'activo' => true,
        ]);
        $missing = $this->enrollment(Yoguini::factory()->create(), $cycle);
        $existing = $this->enrollment(Yoguini::factory()->create(), $cycle);
        $dropped = $this->enrollment(Yoguini::factory()->create(), $cycle, 'cancelada');
        app(CargoService::class)->createForEnrollment($existing, $cycle);

        $first = app(CargoService::class)->generateMissingForCycle($cycle);
        $second = app(CargoService::class)->generateMissingForCycle($cycle);

        $this->assertSame(['creados' => 1, 'omitidos' => 1, 'sin_precio' => false], $first);
        $this->assertSame(['creados' => 0, 'omitidos' => 2, 'sin_precio' => false], $second);
        $this->assertSame(2, Cargo::count());
        $this->assertTrue(Cargo::where('inscripcion_id', $missing->id)->exists());
        $this->assertFalse(Cargo::where('inscripcion_id', $dropped->id)->exists());
    }

    public function test_partial_payments_move_a_charge_from_pending_to_partial_to_paid()
    {
        $student = Yoguini::factory()->create();
        $charge = $this->charge($student, '1000.00');

        $this->pay($student, '400.00', $charge);
        $this->assertSame(Cargo::PARCIAL, $charge->fresh()->estado);
        $this->assertSame('600.00', Money::decimal($charge->fresh()->balanceCents()));

        $this->pay($student, '600.00', $charge);
        $this->assertSame(Cargo::PAGADO, $charge->fresh()->estado);
        $this->assertSame('0.00', Money::decimal($charge->fresh()->balanceCents()));
        $this->assertSame('0.00', $this->credit($student));
    }

    public function test_payment_above_the_balance_leaves_the_surplus_as_credit()
    {
        $student = Yoguini::factory()->create();
        $charge = $this->charge($student, '1000.00');

        $result = $this->pay($student, '1500.00', $charge);

        $this->assertSame('1000.00', $result['aplicado']);
        $this->assertSame('500.00', $result['saldo_a_favor_generado']);
        $this->assertSame(Cargo::PAGADO, $charge->fresh()->estado);
        $this->assertSame('500.00', $this->credit($student));
    }

    public function test_payment_without_a_charge_is_entirely_credit()
    {
        $student = Yoguini::factory()->create();
        $charge = $this->charge($student, '300.00');

        $result = $this->pay($student, '750.25');

        $this->assertSame('0.00', $result['aplicado']);
        $this->assertSame(0, PagoAplicacion::count());
        $this->assertSame('750.25', $this->credit($student));
        $this->assertSame(Cargo::PENDIENTE, $charge->fresh()->estado);
    }

    public function test_surplus_is_spread_from_the_oldest_period_and_the_rest_stays_as_credit()
    {
        $student = Yoguini::factory()->create();
        $august = $this->charge($student, '300.00', '2026-08-01');
        $september = $this->charge($student, '200.00', '2026-09-01');
        $october = $this->charge($student, '500.00', '2026-10-01');

        $result = $this->pay($student, '1100.00', $october, ['aplicar_excedente' => true]);

        $this->assertSame('1000.00', $result['aplicado']);
        $this->assertSame('100.00', $result['saldo_a_favor_generado']);
        foreach ([$august, $september, $october] as $charge) {
            $this->assertSame(Cargo::PAGADO, $charge->fresh()->estado);
        }
        $this->assertSame(
            [$october->id, $august->id, $september->id],
            PagoAplicacion::orderBy('id')->pluck('cargo_id')->map(function ($id) { return (int) $id; })->all()
        );
        $this->assertSame('100.00', $this->credit($student));
    }

    public function test_surplus_without_a_selected_charge_is_rejected()
    {
        $student = Yoguini::factory()->create();

        $this->assertRejected(function () use ($student) {
            $this->pay($student, '100.00', null, ['aplicar_excedente' => true]);
        }, 'aplicar_excedente');
    }

    public function test_applying_credit_respects_the_maximum_and_the_charge_state()
    {
        $student = Yoguini::factory()->create();
        $other = Yoguini::factory()->create();
        $charge = $this->charge($student, '500.00');
        $otherCharge = $this->charge($other, '500.00');
        $this->pay($student, '300.00');

        $this->assertRejected(function () use ($charge) {
            app(PagoService::class)->aplicarSaldoAFavor($charge, '300.01', $this->admin);
        }, 'monto');
        $this->assertRejected(function () use ($otherCharge) {
            app(PagoService::class)->aplicarSaldoAFavor($otherCharge, '1.00', $this->admin);
        }, 'monto');

        $result = app(PagoService::class)->aplicarSaldoAFavor($charge, '300.00', $this->admin);
        $this->assertSame('0.00', $result['saldo_a_favor']);
        $this->assertSame(Cargo::PARCIAL, $charge->fresh()->estado);

        $paid = $this->charge($student, '100.00');
        $this->pay($student, '100.00', $paid);
        $voided = $this->charge($student, '100.00');
        app(CargoService::class)->void($voided, 'Cargo duplicado', $this->admin);
        $this->pay($student, '50.00');
        foreach ([$paid, $voided] as $closed) {
            $this->assertRejected(function () use ($closed) {
                app(PagoService::class)->aplicarSaldoAFavor($closed, '10.00', $this->admin);
            }, 'cargo');
        }
    }

    public function test_credit_is_taken_from_the_oldest_payment_first()
    {
        $student = Yoguini::factory()->create();
        $charge = $this->charge($student, '500.00');
        $older = $this->pay($student, '100.00', null, ['fecha_pago' => '2026-10-01'])['pago'];
        $newer = $this->pay($student, '200.00', null, ['fecha_pago' => '2026-10-10'])['pago'];

        app(PagoService::class)->aplicarSaldoAFavor($charge, '150.00', $this->admin);

        $this->assertSame('100.00', Money::decimal($this->applied($older)));
        $this->assertSame('50.00', Money::decimal($this->applied($newer)));
    }

    public function test_voiding_an_application_returns_the_amount_to_credit_and_recalculates_the_charge()
    {
        $student = Yoguini::factory()->create();
        $charge = $this->charge($student, '1000.00');
        $this->pay($student, '1000.00', $charge);
        $application = PagoAplicacion::firstOrFail();

        $result = app(PagoService::class)->desaplicar($application, 'Imputado por error', $this->admin);

        $this->assertSame('1000.00', $result['saldo_a_favor']);
        $this->assertSame(Cargo::PENDIENTE, $charge->fresh()->estado);
        $this->assertSame(PagoAplicacion::ANULADA, $application->fresh()->estado);
        $this->assertSame('Imputado por error', $application->fresh()->motivo_anulacion);
        $this->assertRejected(function () use ($application) {
            app(PagoService::class)->desaplicar($application, 'Otra vez', $this->admin);
        }, 'aplicacion');
    }

    public function test_voiding_a_payment_reverts_every_application_and_lists_the_affected_charges()
    {
        $student = Yoguini::factory()->create();
        $first = $this->charge($student, '600.00', '2026-09-01');
        $second = $this->charge($student, '400.00', '2026-10-01');
        $payment = $this->pay($student, '1000.00', $first, ['aplicar_excedente' => true])['pago'];

        $affected = (new \App\Http\Resources\Payments\PagoResource($payment->fresh(['aplicaciones.cargo'])))->resolve();
        $this->assertSame([$first->id, $second->id], array_column($affected['cargos_afectados'], 'cargo_id'));

        $result = app(PagoService::class)->anular($payment, 'Cheque rechazado', $this->admin);

        $this->assertSame(Pago::ANULADO, $result['pago']->estado);
        $this->assertSame('0.00', $result['saldo_a_favor']);
        $this->assertSame(0, PagoAplicacion::where('estado', PagoAplicacion::VIGENTE)->count());
        $this->assertSame(Cargo::PENDIENTE, $first->fresh()->estado);
        $this->assertSame(Cargo::PENDIENTE, $second->fresh()->estado);
        $this->assertRejected(function () use ($payment) {
            app(PagoService::class)->anular($payment, 'Otra vez', $this->admin);
        }, 'pago');
    }

    public function test_a_charge_with_applications_cannot_be_voided_until_they_are_removed()
    {
        $student = Yoguini::factory()->create();
        $charge = $this->charge($student, '500.00');
        $this->pay($student, '200.00', $charge);

        $this->assertRejected(function () use ($charge) {
            app(CargoService::class)->void($charge, 'Error de carga', $this->admin);
        }, 'cargo');

        app(PagoService::class)->desaplicar(PagoAplicacion::firstOrFail(), 'Se anula el cargo', $this->admin);
        $voided = app(CargoService::class)->void($charge, 'Error de carga', $this->admin);

        $this->assertSame(Cargo::ANULADO, $voided->estado);
        $this->assertSame('0.00', Money::decimal($voided->balanceCents()));
        $this->assertSame('200.00', $this->credit($student));
    }

    public function test_discount_needs_a_reason_and_never_leaves_the_final_amount_below_what_was_applied()
    {
        $student = Yoguini::factory()->create();
        $charge = $this->charge($student, '1000.00');
        $this->pay($student, '600.00', $charge);
        $cargos = app(CargoService::class);

        $this->assertRejected(function () use ($cargos, $charge) { $cargos->applyDiscount($charge, '100.00', ''); }, 'motivo_descuento');
        $this->assertRejected(function () use ($cargos, $charge) { $cargos->applyDiscount($charge, '1000.01', 'Beca'); }, 'descuento');
        $this->assertRejected(function () use ($cargos, $charge) { $cargos->applyDiscount($charge, '500.00', 'Beca'); }, 'descuento');

        $discounted = $cargos->applyDiscount($charge, '300.00', 'Beca parcial');
        $this->assertSame('700.00', Money::decimal($discounted->finalCents()));
        $this->assertSame(Cargo::PARCIAL, $discounted->estado);

        $settled = $cargos->applyDiscount($charge, '400.00', 'Beca parcial');
        $this->assertSame('600.00', Money::decimal($settled->finalCents()));
        $this->assertSame(Cargo::PAGADO, $settled->estado);
    }

    public function test_cents_add_up_exactly_without_rounding_errors()
    {
        $student = Yoguini::factory()->create();
        $small = $this->charge($student, '0.30');
        $this->pay($student, '0.10', $small);
        $this->pay($student, '0.20', $small);
        $this->assertSame(Cargo::PAGADO, $small->fresh()->estado);
        $this->assertSame('0.00', Money::decimal($small->fresh()->balanceCents()));

        $thirds = $this->charge($student, '99.99', '2026-09-01');
        foreach ([1, 2, 3] as $ignored) {
            $this->pay($student, '33.33', $thirds);
        }
        $this->assertSame(Cargo::PAGADO, $thirds->fresh()->estado);

        $this->pay($student, '0.07');
        $this->pay($student, '0.01');
        $this->assertSame('0.08', $this->credit($student));
        $this->assertSame(1, Money::cents('0.01'));
        $this->assertSame(1999, Money::cents('19.99'));
        $this->assertSame('19.99', Money::decimal(1999));
    }

    public function test_monthly_summary_separates_applied_to_the_period_from_collected_in_the_month()
    {
        $this->assertSame('America/Argentina/Buenos_Aires', config('app.timezone'));
        $this->assertSame('America/Argentina/Buenos_Aires', Carbon::now()->timezoneName);
        Carbon::setTestNow(Carbon::parse('2026-10-31 23:30:00', config('app.timezone')));

        $first = Yoguini::factory()->create();
        $second = Yoguini::factory()->create();
        $this->charge($first, '1000.00', '2026-10-01');
        $octoberSecond = $this->charge($second, '500.00', '2026-10-01');
        $voided = $this->charge($second, '300.00', '2026-10-01');
        app(CargoService::class)->void($voided, 'Duplicado', $this->admin);
        $september = $this->charge($first, '800.00', '2026-09-01');
        $this->pay($first, '1200.00', $september, ['fecha_pago' => '2026-10-05']);
        $this->pay($second, '200.00', $octoberSecond, ['fecha_pago' => '2026-10-10']);
        $this->pay($first, '50.00', null, ['fecha_pago' => '2026-10-31']);
        $this->pay($first, '100.00', null, ['fecha_pago' => '2026-09-20']);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/payments/admin/summary?periodo=2026-10')
            ->assertOk()
            ->assertExactJson([
                'periodo' => '2026-10',
                'facturado' => '1500.00',
                'aplicado_al_periodo' => '200.00',
                'pendiente' => '1300.00',
                'cobrado_en_mes' => '1450.00',
                'saldo_a_favor_total' => '550.00',
            ]);
    }

    public function test_charge_list_filters_by_period_status_and_student_and_flags_credit()
    {
        $ana = Yoguini::factory()->create(['nombre' => 'Ana', 'apellido' => 'Lopez']);
        $beto = Yoguini::factory()->create(['nombre' => 'Beto', 'apellido' => 'Diaz']);
        $anaCharge = $this->charge($ana, '100.00', '2026-10-01');
        $this->charge($beto, '100.00', '2026-10-01');
        $this->charge($ana, '100.00', '2026-09-01');
        $this->pay($ana, '250.00', $anaCharge);
        $this->actingAs($this->admin, 'sanctum');

        $all = $this->getJson('/api/payments/admin/charges?periodo=2026-10')->assertOk()->assertJsonCount(2);
        $this->assertSame('150.00', $all->json('0.saldo_a_favor_alumno'));
        $this->getJson('/api/payments/admin/charges?periodo=2026-10&estado=pagado')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/payments/admin/charges?periodo=2026-10&q=Beto')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/payments/admin/charges?periodo=2026-09')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/payments/admin/charges?periodo=10-2026')->assertStatus(422);
    }

    public function test_register_payment_endpoint_validates_amounts_dates_and_methods()
    {
        $student = Yoguini::factory()->create();
        $charge = $this->charge($student, '1000.00');
        $this->actingAs($this->admin, 'sanctum');
        $valid = [
            'user_id' => $student->id, 'monto' => '1500.00', 'fecha_pago' => '2026-10-14',
            'metodo' => 'transferencia', 'cargo_id' => $charge->id, 'aplicar_excedente' => false,
        ];

        foreach ([['monto' => '10.123'], ['monto' => '0'], ['monto' => '-5'], ['monto' => 'abc'],
            ['fecha_pago' => '2026-10-15'], ['metodo' => 'tarjeta'], ['user_id' => 999999],
            ['cargo_id' => null, 'aplicar_excedente' => true]] as $override) {
            $this->postJson('/api/payments/admin/payments', array_merge($valid, $override))->assertStatus(422);
        }
        $this->assertSame(0, Pago::count());

        $this->postJson('/api/payments/admin/payments', $valid)
            ->assertCreated()
            ->assertJsonPath('aplicado', '1000.00')
            ->assertJsonPath('saldo_a_favor_generado', '500.00')
            ->assertJsonPath('saldo_a_favor', '500.00')
            ->assertJsonPath('pago.monto', '1500.00');

        $this->postJson('/api/payments/admin/payments', array_merge($valid, ['monto' => 12500.5, 'cargo_id' => null]))
            ->assertCreated()
            ->assertJsonPath('pago.monto', '12500.50');
    }

    public function test_charge_discount_and_void_endpoints_require_their_reasons()
    {
        $student = Yoguini::factory()->create();
        $charge = $this->charge($student, '1000.00');
        $this->actingAs($this->admin, 'sanctum');

        $this->patchJson("/api/payments/admin/charges/{$charge->id}/discount", ['descuento' => '100.00'])->assertStatus(422);
        $this->patchJson("/api/payments/admin/charges/{$charge->id}/discount", ['descuento' => '100.00', 'motivo_descuento' => 'Beca'])
            ->assertOk()->assertJsonPath('monto_final', '900.00')->assertJsonPath('saldo', '900.00');
        $this->patchJson("/api/payments/admin/charges/{$charge->id}/void", [])->assertStatus(422);
        $this->patchJson("/api/payments/admin/charges/{$charge->id}/void", ['motivo' => 'Cargo duplicado'])
            ->assertOk()->assertJsonPath('estado', 'anulado');

        $this->postJson('/api/payments/admin/charges', [
            'user_id' => $student->id, 'periodo' => '2026-11', 'concepto' => 'Matrícula', 'monto_original' => '2500.00',
        ])->assertCreated()->assertJsonPath('periodo', '2026-11-01')->assertJsonPath('estado', 'pendiente');
        $this->postJson('/api/payments/admin/charges', [
            'user_id' => $student->id, 'periodo' => '2026-11', 'concepto' => 'Matrícula', 'monto_original' => '25.005',
        ])->assertStatus(422);
    }

    public function test_admin_routes_require_authentication_and_the_admin_role()
    {
        $student = Yoguini::factory()->create();
        $charge = $this->charge($student, '500.00');
        $this->pay($student, '200.00', $charge);
        $payment = Pago::firstOrFail();
        $application = PagoAplicacion::firstOrFail();
        $cycle = Ciclo::create([
            'nombre' => 'Ciclo', 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-31',
            'clases_por_semana' => 1, 'precio' => '100.00', 'activo' => true,
        ]);
        $routes = [
            ['GET', '/api/payments/admin/charges'],
            ['GET', '/api/payments/admin/summary'],
            ['GET', "/api/payments/admin/students/{$student->id}"],
            ['POST', '/api/payments/admin/charges'],
            ['PATCH', "/api/payments/admin/charges/{$charge->id}/discount"],
            ['PATCH', "/api/payments/admin/charges/{$charge->id}/void"],
            ['POST', "/api/payments/admin/charges/{$charge->id}/apply-credit"],
            ['POST', "/api/payments/admin/cycles/{$cycle->id}/charges/generate"],
            ['POST', '/api/payments/admin/payments'],
            ['PATCH', "/api/payments/admin/payments/{$payment->id}/void"],
            ['PATCH', "/api/payments/admin/applications/{$application->id}/void"],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri, [])->assertUnauthorized();
        }
        $this->getJson('/api/payments/me')->assertUnauthorized();

        $this->actingAs($student, 'sanctum');
        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri, [])->assertForbidden();
        }
        $this->assertSame(Cargo::PARCIAL, $charge->fresh()->estado);
    }

    public function test_a_student_only_reads_their_own_payments_and_balances()
    {
        $mine = Yoguini::factory()->create();
        $theirs = Yoguini::factory()->create();
        $myCharge = $this->charge($mine, '500.00', '2026-10-01', ['concepto' => 'Mi cargo']);
        $theirCharge = $this->charge($theirs, '900.00', '2026-10-01', ['concepto' => 'OTRO-ALUMNO']);
        $this->pay($mine, '700.00', $myCharge);
        $this->pay($theirs, '900.00', $theirCharge);

        $response = $this->actingAs($mine, 'sanctum')->getJson('/api/payments/me')->assertOk();

        $this->assertSame([$myCharge->id], array_column($response->json('cargos'), 'id'));
        $this->assertCount(1, $response->json('pagos'));
        $this->assertSame('200.00', $response->json('saldo_a_favor'));
        $this->assertSame('500.00', $response->json('saldo_por_mes.0.facturado'));
        $this->assertSame('0.00', $response->json('saldo_por_mes.0.saldo'));
        $this->assertStringNotContainsString('OTRO-ALUMNO', $response->getContent());
        $this->assertStringNotContainsString('registrado_por', $response->getContent());
        $this->assertArrayNotHasKey('alumno', $response->json('cargos.0'));
    }

    public function test_dropping_an_enrollment_keeps_the_charge_untouched_and_flags_it()
    {
        [$cycle, $schedule] = $this->openCycle('9000.00');
        $student = Yoguini::factory()->create();
        $enrollment = app(InscripcionService::class)->enroll($student, $cycle, [$schedule->id]);
        $before = Cargo::where('inscripcion_id', $enrollment->id)->firstOrFail()->toArray();

        app(ReservaService::class)->cancelEnrollment($student, $enrollment);

        $after = Cargo::where('inscripcion_id', $enrollment->id)->firstOrFail();
        $this->assertSame($before, $after->toArray());
        $this->assertSame(Cargo::PENDIENTE, $after->estado);
        $payload = (new CargoResource($after->load('inscripcion')))->resolve();
        $this->assertTrue($payload['inscripcion_baja']);
    }

    private function openCycle($price)
    {
        $schedule = Horario::create([
            'dia_semana' => 2, 'hora_inicio' => '10:00:00', 'duracion_min' => 60,
            'nivel' => 'General', 'profesor' => 'Instructor', 'cupo' => 10, 'activo' => true,
        ]);
        $cycle = Ciclo::create([
            'nombre' => 'Ciclo de octubre', 'fecha_inicio' => '2026-10-20', 'fecha_fin' => '2026-10-20',
            'clases_por_semana' => 1, 'precio' => $price, 'activo' => true,
        ]);
        $cycle->horarios()->sync([$schedule->id]);
        app(GenerateCycleClasses::class)->generate($cycle);

        return [$cycle, $schedule, Clase::where('ciclo_id', $cycle->id)->firstOrFail()];
    }

    private function enrollment(Yoguini $student, Ciclo $cycle, $state = 'activa')
    {
        return Inscripcion::create([
            'user_id' => $student->id, 'ciclo_id' => $cycle->id, 'estado' => $state, 'precio' => $cycle->precio,
        ]);
    }

    private function charge(Yoguini $student, $amount, $period = '2026-10-01', array $attributes = [])
    {
        return Cargo::create(array_merge([
            'user_id' => $student->id, 'periodo' => $period, 'concepto' => 'Concepto '.$period,
            'monto_original' => $amount, 'descuento' => '0.00', 'monto_final' => $amount,
            'estado' => Cargo::PENDIENTE,
        ], $attributes));
    }

    private function pay(Yoguini $student, $amount, $charge = null, array $extra = [])
    {
        return app(PagoService::class)->registrar($student, array_merge([
            'monto' => $amount, 'fecha_pago' => '2026-10-14', 'metodo' => 'efectivo',
            'cargo_id' => $charge ? $charge->id : null,
        ], $extra), $this->admin);
    }

    private function credit(Yoguini $student)
    {
        return Money::decimal(app(PagoService::class)->creditCents($student->id));
    }

    private function applied(Pago $payment)
    {
        return Money::sum(PagoAplicacion::where('pago_id', $payment->id)->where('estado', PagoAplicacion::VIGENTE)->pluck('monto'));
    }

    private function assertRejected(callable $callback, $key)
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
            return;
        }
        $this->fail("Expected a validation failure on {$key}.");
    }
}

<?php

namespace Tests\Feature;

use App\Models\Clase;
use App\Models\Ciclo;
use App\Models\Configuracion;
use App\Models\Feriado;
use App\Models\Horario;
use App\Models\Inscripcion;
use App\Models\Recuperacion;
use App\Models\Reserva;
use App\Models\Yoguini;
use App\Services\GenerateCycleClasses;
use App\Services\RecuperacionService;
use App\Services\ReservaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-01 08:00:00', 'UTC'));
        config(['app.timezone' => 'UTC']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generation_is_idempotent_skips_holidays_and_accepts_single_day_cycles()
    {
        $tuesdayOne = $this->schedule(2);
        $tuesdayTwo = $this->schedule(2);
        $wednesday = $this->schedule(3);
        $thursday = $this->schedule(4);
        Feriado::create(['fecha' => '2026-10-07', 'descripcion' => 'Holiday']);

        $singleDay = $this->cycle('2026-10-06', '2026-10-06');
        $generator = app(GenerateCycleClasses::class);
        $first = $generator->generate($singleDay);
        $second = $generator->generate($singleDay);

        $this->assertSame(['created' => 2, 'skipped' => 0], $first);
        $this->assertSame(['created' => 0, 'skipped' => 2], $second);
        $this->assertDatabaseCount('clases', 2);
        $this->assertDatabaseHas('clases', ['ciclo_id' => $singleDay->id, 'horario_id' => $tuesdayOne->id]);
        $this->assertDatabaseHas('clases', ['ciclo_id' => $singleDay->id, 'horario_id' => $tuesdayTwo->id]);

        $holidayRange = $this->cycle('2026-10-07', '2026-10-08');
        $generator->generate($holidayRange);
        $this->assertDatabaseMissing('clases', ['ciclo_id' => $holidayRange->id, 'horario_id' => $wednesday->id]);
        $this->assertDatabaseHas('clases', [
            'ciclo_id' => $holidayRange->id,
            'horario_id' => $thursday->id,
            'fecha' => '2026-10-08',
        ]);
    }

    public function test_generation_rejects_a_schedule_date_owned_by_another_cycle()
    {
        $schedule = $this->schedule(2);
        $firstCycle = $this->cycle('2026-10-06', '2026-10-06');
        $secondCycle = $this->cycle('2026-10-06', '2026-10-06');
        $generator = app(GenerateCycleClasses::class);
        $generator->generate($firstCycle);

        try {
            $generator->generate($secondCycle);
            $this->fail('Expected overlapping schedule generation to be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('horarios', $exception->errors());
        }

        $this->assertDatabaseHas('clases', ['ciclo_id' => $firstCycle->id, 'horario_id' => $schedule->id]);
        $this->assertDatabaseMissing('clases', ['ciclo_id' => $secondCycle->id, 'horario_id' => $schedule->id]);
    }

    public function test_admin_cannot_change_cycle_dates_after_classes_are_generated()
    {
        $admin = $this->yoguini('Admin');
        $cycle = $this->cycle('2026-10-06', '2026-10-20');
        $schedule = $this->schedule(2);
        $this->makeClass($cycle, $schedule, '2026-10-06', '10:00:00');
        $this->actingAs($admin, 'sanctum');

        $this->patchJson("/api/booking/admin/cycles/{$cycle->id}", ['fecha_inicio' => '2026-10-07'])
            ->assertUnprocessable()->assertJsonValidationErrors(['fecha_inicio']);

        $this->assertDatabaseHas('ciclos', ['id' => $cycle->id, 'fecha_inicio' => '2026-10-06']);
    }

    public function test_admin_can_partially_update_schedule_without_sending_time()
    {
        $admin = $this->yoguini('Admin');
        $schedule = $this->schedule(2);
        $this->actingAs($admin, 'sanctum');

        $this->patchJson("/api/booking/admin/schedules/{$schedule->id}", ['cupo' => 12])
            ->assertOk()
            ->assertJsonPath('cupo', 12)
            ->assertJsonPath('hora_inicio', '10:00:00');
    }

    public function test_admin_can_update_holiday_but_not_move_it_onto_a_generated_class_date()
    {
        $admin = $this->yoguini('Admin');
        $holiday = Feriado::create(['fecha' => '2026-10-10', 'descripcion' => 'Initial']);
        $cycle = $this->cycle('2026-10-01', '2026-10-31');
        $this->makeClass($cycle, $this->schedule(2), '2026-10-12', '10:00:00');
        $this->actingAs($admin, 'sanctum');

        $this->patchJson("/api/booking/admin/holidays/{$holiday->id}", ['descripcion' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('descripcion', 'Updated');
        $this->patchJson("/api/booking/admin/holidays/{$holiday->id}", ['fecha' => '2026-10-12'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha']);
        $this->patchJson("/api/booking/admin/holidays/{$holiday->id}", ['fecha' => '2026-10-11'])
            ->assertOk()
            ->assertJsonPath('fecha', '2026-10-11');
    }

    public function test_enrollment_rejects_schedule_count_and_full_classes_without_partial_records()
    {
        $cycle = $this->cycle('2026-10-06', '2026-10-06', 1);
        $schedule = $this->schedule(2, ['cupo' => 1]);
        $extraSchedule = $this->schedule(3);
        $class = $this->makeClass($cycle, $schedule, '2026-10-06', '10:00:00');
        $owner = $this->yoguini();
        $enrollment = $this->enrollment($owner, $cycle);
        $this->reserve($owner, $enrollment, $class);
        $student = $this->yoguini();
        $this->actingAs($student, 'sanctum');

        $this->postJson("/api/booking/cycles/{$cycle->id}/enroll", [
            'horario_ids' => [$schedule->id, $extraSchedule->id],
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('inscripciones', ['user_id' => $student->id, 'ciclo_id' => $cycle->id]);

        $this->postJson("/api/booking/cycles/{$cycle->id}/enroll", [
            'horario_ids' => [$schedule->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['clases_sin_cupo']);
        $this->assertDatabaseMissing('inscripciones', ['user_id' => $student->id, 'ciclo_id' => $cycle->id]);
        $this->assertDatabaseCount('reservas', 1);
    }

    public function test_enrollment_creates_pivot_and_regular_reservations_for_all_future_classes()
    {
        $cycle = $this->cycle('2026-10-06', '2026-10-15', 2);
        $tuesday = $this->schedule(2);
        $thursday = $this->schedule(4);
        foreach (['2026-10-06', '2026-10-13'] as $date) {
            $this->makeClass($cycle, $tuesday, $date, '10:00:00');
        }
        foreach (['2026-10-08', '2026-10-15'] as $date) {
            $this->makeClass($cycle, $thursday, $date, '11:00:00');
        }
        $student = $this->yoguini();
        $this->actingAs($student, 'sanctum');

        $response = $this->postJson("/api/booking/cycles/{$cycle->id}/enroll", [
            'horario_ids' => [$tuesday->id, $thursday->id],
        ])->assertCreated();

        $enrollmentId = $response->json('id');
        $this->assertDatabaseHas('inscripcion_horarios', [
            'inscripcion_id' => $enrollmentId,
            'horario_id' => $tuesday->id,
        ]);
        $this->assertDatabaseCount('reservas', 4);
        $this->assertDatabaseHas('reservas', [
            'inscripcion_id' => $enrollmentId,
            'user_id' => $student->id,
            'tipo' => 'regular',
            'estado' => 'reservada',
        ]);
    }

    public function test_enrollment_rejects_a_schedule_with_missing_future_generated_classes()
    {
        $cycle = $this->cycle('2026-10-06', '2026-10-20', 1);
        $schedule = $this->schedule(2);
        $this->makeClass($cycle, $schedule, '2026-10-06', '10:00:00');
        $student = $this->yoguini();
        $this->actingAs($student, 'sanctum');

        $this->postJson("/api/booking/cycles/{$cycle->id}/enroll", [
            'horario_ids' => [$schedule->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['clases']);

        $this->assertDatabaseMissing('inscripciones', ['user_id' => $student->id, 'ciclo_id' => $cycle->id]);
        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_cancellation_creates_credit_only_when_minimum_notice_is_met()
    {
        Configuracion::create(['clave' => 'horas_aviso_minimas', 'valor' => '24']);
        $student = $this->yoguini();
        $cycle = $this->cycle('2026-10-01', '2026-10-31');
        $enrollment = $this->enrollment($student, $cycle);
        $schedule = $this->schedule(2);
        $early = $this->makeClass($cycle, $schedule, '2026-10-03', '10:00:00');
        $late = $this->makeClass($cycle, $schedule, '2026-10-01', '20:00:00');
        $earlyReservation = $this->reserve($student, $enrollment, $early);
        $lateReservation = $this->reserve($student, $enrollment, $late);

        app(ReservaService::class)->cancelReservation($student, $earlyReservation);
        app(ReservaService::class)->cancelReservation($student, $lateReservation);

        $this->assertDatabaseHas('reservas', ['id' => $earlyReservation->id, 'estado' => 'cancelada_con_aviso']);
        $this->assertDatabaseHas('reservas', ['id' => $lateReservation->id, 'estado' => 'cancelada']);
        $this->assertDatabaseHas('recuperaciones', [
            'reserva_origen_id' => $earlyReservation->id,
            'vence_en' => '2026-10-31',
            'estado' => 'disponible',
        ]);
        $this->assertDatabaseMissing('recuperaciones', ['reserva_origen_id' => $lateReservation->id]);
    }

    public function test_my_bookings_reports_credit_eligibility_at_the_exact_notice_threshold()
    {
        Configuracion::create(['clave' => 'horas_aviso_minimas', 'valor' => '24']);
        $student = $this->yoguini();
        $cycle = $this->cycle('2026-10-01', '2026-10-31');
        $enrollment = $this->enrollment($student, $cycle);
        $eligibleClass = $this->makeClass($cycle, $this->schedule(2), '2026-10-02', '08:00:00');
        $ineligibleClass = $this->makeClass($cycle, $this->schedule(3), '2026-10-02', '07:59:00');
        $eligibleReservation = $this->reserve($student, $enrollment, $eligibleClass);
        $ineligibleReservation = $this->reserve($student, $enrollment, $ineligibleClass);
        $this->actingAs($student, 'sanctum');

        $reservations = collect($this->getJson('/api/booking/me')->assertOk()->json('inscripciones'))
            ->flatMap(function ($item) {
                return $item['reservas'];
            })
            ->keyBy('id');

        $this->assertTrue($reservations[$eligibleReservation->id]['genera_credito']);
        $this->assertTrue($reservations[$eligibleReservation->id]['puede_cancelar']);
        $this->assertTrue($reservations[$eligibleReservation->id]['es_proxima']);
        $this->assertFalse($reservations[$ineligibleReservation->id]['genera_credito']);
        $this->assertTrue($reservations[$ineligibleReservation->id]['puede_cancelar']);
    }

    public function test_recovery_can_cross_cycles_but_rejects_expired_credits_and_full_classes()
    {
        $student = $this->yoguini();
        $sourceCycle = $this->cycle('2026-10-01', '2026-10-31');
        $sourceEnrollment = $this->enrollment($student, $sourceCycle);
        $schedule = $this->schedule(2);
        $sourceClass = $this->makeClass($sourceCycle, $schedule, '2026-09-29', '10:00:00');
        $sourceReservation = $this->reserve($student, $sourceEnrollment, $sourceClass, 'cancelada_con_aviso');
        $credit = $this->credit($student, $sourceEnrollment, $sourceReservation, '2026-10-31');

        $destinationCycle = $this->cycle('2026-10-15', '2026-10-31');
        $destinationSchedule = $this->schedule(4);
        $destination = $this->makeClass($destinationCycle, $destinationSchedule, '2026-10-20', '12:00:00', ['cupo' => 2]);
        $this->actingAs($student, 'sanctum');
        $response = $this->postJson("/api/booking/recoveries/{$credit->id}/book", ['clase_id' => $destination->id])
            ->assertCreated();
        $this->assertSame('recuperacion', $response->json('tipo'));
        $this->assertSame((string) $sourceEnrollment->id, $response->json('inscripcion_id'));
        $this->assertSame('usada', $credit->fresh()->estado);

        $expiredSourceClass = $this->makeClass($sourceCycle, $schedule, '2026-09-28', '10:00:00');
        $expiredReservation = $this->reserve($student, $sourceEnrollment, $expiredSourceClass, 'cancelada_con_aviso');
        $expiredCredit = $this->credit($student, $sourceEnrollment, $expiredReservation, '2026-09-30');
        $this->postJson("/api/booking/recoveries/{$expiredCredit->id}/book", ['clase_id' => $destination->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['recuperacion']);

        $fullDestination = $this->makeClass(
            $destinationCycle,
            $destinationSchedule,
            '2026-10-22',
            '12:00:00',
            ['cupo' => 1]
        );
        $occupant = $this->yoguini();
        $occupantEnrollment = $this->enrollment($occupant, $destinationCycle);
        $this->reserve($occupant, $occupantEnrollment, $fullDestination);
        $capacitySourceClass = $this->makeClass($sourceCycle, $schedule, '2026-09-27', '10:00:00');
        $capacityReservation = $this->reserve($student, $sourceEnrollment, $capacitySourceClass, 'cancelada_con_aviso');
        $capacityCredit = $this->credit($student, $sourceEnrollment, $capacityReservation, '2026-10-31');
        $this->postJson("/api/booking/recoveries/{$capacityCredit->id}/book", ['clase_id' => $fullDestination->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['clase_id']);
        $this->assertSame('disponible', $capacityCredit->fresh()->estado);
    }

    public function test_recovery_class_options_include_future_classes_from_inactive_cycles()
    {
        $student = $this->yoguini();
        $inactiveCycle = $this->cycle('2026-10-01', '2026-10-31');
        $inactiveCycle->update(['activo' => false]);
        $schedule = $this->schedule(4);
        $class = $this->makeClass($inactiveCycle, $schedule, '2026-10-08', '12:00:00');
        $this->actingAs($student, 'sanctum');

        $this->getJson('/api/booking/recoveries/available-classes')
            ->assertOk()
            ->assertJsonFragment(['id' => $class->id, 'disponibles' => $class->cupo]);
    }

    public function test_recovery_class_options_exclude_classes_already_reserved_by_the_student()
    {
        $student = $this->yoguini();
        $cycle = $this->cycle('2026-10-01', '2026-10-31');
        $enrollment = $this->enrollment($student, $cycle);
        $schedule = $this->schedule(4);
        $class = $this->makeClass($cycle, $schedule, '2026-10-08', '12:00:00');
        $this->reserve($student, $enrollment, $class);
        $this->actingAs($student, 'sanctum');

        $this->getJson('/api/booking/recoveries/available-classes')
            ->assertOk()
            ->assertJsonMissing(['id' => $class->id]);
    }

    public function test_cancelling_a_recovery_reservation_returns_the_same_credit_without_creating_a_new_one()
    {
        $student = $this->yoguini();
        $sourceCycle = $this->cycle('2026-10-01', '2026-10-31');
        $sourceEnrollment = $this->enrollment($student, $sourceCycle);
        $sourceSchedule = $this->schedule(2);
        $sourceClass = $this->makeClass($sourceCycle, $sourceSchedule, '2026-10-06', '10:00:00');
        $sourceReservation = $this->reserve($student, $sourceEnrollment, $sourceClass, 'cancelada_con_aviso');
        $credit = $this->credit($student, $sourceEnrollment, $sourceReservation, '2026-10-31');

        $destinationCycle = $this->cycle('2026-10-01', '2026-10-31');
        $destinationSchedule = $this->schedule(4);
        $destination = $this->makeClass($destinationCycle, $destinationSchedule, '2026-10-08', '12:00:00');
        $this->actingAs($student, 'sanctum');
        $reservation = app(RecuperacionService::class)->recover($student, $credit, $destination);

        $this->postJson("/api/booking/reservations/{$reservation->id}/cancel")
            ->assertOk()
            ->assertJsonPath('credito_devuelto', true);

        $this->assertDatabaseHas('reservas', ['id' => $reservation->id, 'estado' => 'cancelada_con_aviso']);
        $this->assertDatabaseHas('recuperaciones', [
            'id' => $credit->id,
            'estado' => 'disponible',
            'reserva_destino_id' => null,
            'vence_en' => '2026-10-31',
        ]);
        $this->assertDatabaseCount('recuperaciones', 1);
    }

    public function test_students_cannot_cancel_another_users_reservation_or_use_their_credit()
    {
        $owner = $this->yoguini();
        $student = $this->yoguini();
        $cycle = $this->cycle('2026-10-01', '2026-10-31');
        $enrollment = $this->enrollment($owner, $cycle);
        $schedule = $this->schedule(2);
        $sourceClass = $this->makeClass($cycle, $schedule, '2026-09-29', '10:00:00');
        $sourceReservation = $this->reserve($owner, $enrollment, $sourceClass, 'cancelada_con_aviso');
        $credit = $this->credit($owner, $enrollment, $sourceReservation, '2026-10-31');
        $destination = $this->makeClass($cycle, $this->schedule(4), '2026-10-08', '12:00:00');
        $this->actingAs($student, 'sanctum');

        $this->postJson("/api/booking/reservations/{$sourceReservation->id}/cancel")
            ->assertForbidden();
        $this->postJson("/api/booking/recoveries/{$credit->id}/book", ['clase_id' => $destination->id])
            ->assertForbidden();

        $this->assertDatabaseHas('reservas', ['id' => $sourceReservation->id, 'estado' => 'cancelada_con_aviso']);
        $this->assertDatabaseHas('recuperaciones', ['id' => $credit->id, 'estado' => 'disponible']);
    }

    public function test_credits_endpoint_returns_only_the_authenticated_users_credits()
    {
        $student = $this->yoguini();
        $otherStudent = $this->yoguini();
        $cycle = $this->cycle('2026-10-01', '2026-10-31');
        $studentEnrollment = $this->enrollment($student, $cycle);
        $otherEnrollment = $this->enrollment($otherStudent, $cycle);
        $schedule = $this->schedule(2);
        $studentOrigin = $this->reserve($student, $studentEnrollment, $this->makeClass($cycle, $schedule, '2026-09-29', '10:00:00'), 'cancelada_con_aviso');
        $otherOrigin = $this->reserve($otherStudent, $otherEnrollment, $this->makeClass($cycle, $this->schedule(3), '2026-09-30', '11:00:00'), 'cancelada_con_aviso');
        $studentCredit = $this->credit($student, $studentEnrollment, $studentOrigin, '2026-10-31');
        $this->credit($otherStudent, $otherEnrollment, $otherOrigin, '2026-10-31');
        $this->actingAs($student, 'sanctum');

        $this->getJson('/api/booking/credits')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $studentCredit->id)
            ->assertJsonPath('0.vence_en', '2026-10-31')
            ->assertJsonPath('0.reserva_origen.clase.fecha', '2026-09-29');
    }

    public function test_admin_cancels_class_once_and_issues_one_credit_per_reservation()
    {
        $admin = $this->yoguini('Admin');
        $student = $this->yoguini();
        $attendedStudent = $this->yoguini();
        $cycle = $this->cycle('2026-10-01', '2026-10-31');
        $enrollment = $this->enrollment($student, $cycle);
        $attendedEnrollment = $this->enrollment($attendedStudent, $cycle);
        $schedule = $this->schedule(2);
        $class = $this->makeClass($cycle, $schedule, '2026-10-06', '10:00:00');
        $reservation = $this->reserve($student, $enrollment, $class);
        $attendedReservation = $this->reserve($attendedStudent, $attendedEnrollment, $class, 'asistio');
        $this->actingAs($admin, 'sanctum');

        $this->patchJson("/api/booking/admin/classes/{$class->id}/cancel")->assertOk();
        $this->patchJson("/api/booking/admin/classes/{$class->id}/cancel")->assertOk();

        $this->assertDatabaseHas('clases', ['id' => $class->id, 'estado' => 'cancelada']);
        $this->assertDatabaseHas('reservas', ['id' => $reservation->id, 'estado' => 'cancelada_con_aviso']);
        $this->assertDatabaseHas('reservas', ['id' => $attendedReservation->id, 'estado' => 'cancelada_con_aviso']);
        $this->assertDatabaseCount('recuperaciones', 2);
    }

    public function test_admin_can_filter_classes_and_fetch_class_reservations()
    {
        $admin = $this->yoguini('Admin');
        $student = $this->yoguini();
        $cycle = $this->cycle('2026-10-01', '2026-10-31');
        $otherCycle = $this->cycle('2026-10-01', '2026-10-31');
        $enrollment = $this->enrollment($student, $cycle);
        $firstClass = $this->makeClass($cycle, $this->schedule(2), '2026-10-06', '10:00:00');
        $this->makeClass($otherCycle, $this->schedule(3), '2026-10-07', '11:00:00');
        $this->reserve($student, $enrollment, $firstClass);
        $this->actingAs($admin, 'sanctum');

        $this->getJson("/api/booking/admin/classes?fecha=2026-10-06&ciclo_id={$cycle->id}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $firstClass->id)
            ->assertJsonPath('0.disponibles', 9)
            ->assertJsonPath('0.reservas.0.yoguini.id', $student->id);

        $this->getJson("/api/booking/admin/classes/{$firstClass->id}/reservations")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.yoguini.id', $student->id);
    }

    public function test_admin_cancellation_of_recovery_class_returns_original_credit_without_chaining()
    {
        $admin = $this->yoguini('Admin');
        $student = $this->yoguini();
        $sourceCycle = $this->cycle('2026-10-01', '2026-10-31');
        $sourceEnrollment = $this->enrollment($student, $sourceCycle);
        $sourceSchedule = $this->schedule(2);
        $sourceClass = $this->makeClass($sourceCycle, $sourceSchedule, '2026-10-06', '10:00:00');
        $sourceReservation = $this->reserve($student, $sourceEnrollment, $sourceClass, 'cancelada_con_aviso');
        $credit = $this->credit($student, $sourceEnrollment, $sourceReservation, '2026-10-31');

        $destinationCycle = $this->cycle('2026-10-01', '2026-10-31');
        $destinationSchedule = $this->schedule(4);
        $destination = $this->makeClass($destinationCycle, $destinationSchedule, '2026-10-08', '12:00:00');
        $this->actingAs($student, 'sanctum');
        $recovery = app(RecuperacionService::class)->recover($student, $credit, $destination);
        $this->actingAs($admin, 'sanctum');

        $this->patchJson("/api/booking/admin/classes/{$destination->id}/cancel")->assertOk();

        $this->assertDatabaseHas('recuperaciones', [
            'id' => $credit->id,
            'estado' => 'disponible',
            'reserva_destino_id' => null,
        ]);
        $this->assertDatabaseCount('recuperaciones', 1);
        $this->assertDatabaseHas('reservas', ['id' => $recovery->id, 'estado' => 'cancelada_con_aviso']);
    }

    public function test_admin_routes_reject_non_admin_users()
    {
        $this->actingAs($this->yoguini('user'), 'sanctum')
            ->getJson('/api/booking/admin/cycles')
            ->assertForbidden();
        $this->getJson('/api/yoguinis')->assertForbidden();
    }

    public function test_yoguini_with_booking_history_cannot_be_deleted()
    {
        $admin = $this->yoguini('Admin');
        $student = $this->yoguini();
        $cycle = $this->cycle('2026-10-01', '2026-10-31');
        $enrollment = $this->enrollment($student, $cycle);
        $schedule = $this->schedule(2);
        $class = $this->makeClass($cycle, $schedule, '2026-10-06', '10:00:00');
        $this->reserve($student, $enrollment, $class);
        $this->actingAs($admin, 'sanctum');

        $this->deleteJson("/api/yoguinis/{$student->id}")
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'No se puede eliminar esta ficha porque tiene historial de reservas.']);

        $this->assertDatabaseHas('yoguinis', ['id' => $student->id]);
        $this->assertDatabaseCount('reservas', 1);
    }

    public function test_marking_absence_does_not_create_recovery_credit()
    {
        $admin = $this->yoguini('Admin');
        $student = $this->yoguini();
        $cycle = $this->cycle('2026-10-01', '2026-10-31');
        $enrollment = $this->enrollment($student, $cycle);
        $schedule = $this->schedule(2);
        $class = $this->makeClass($cycle, $schedule, '2026-10-06', '10:00:00');
        $reservation = $this->reserve($student, $enrollment, $class);
        $this->actingAs($admin, 'sanctum');
        Carbon::setTestNow(Carbon::parse('2026-10-06 11:00:00', 'UTC'));

        $this->postJson("/api/booking/admin/classes/{$class->id}/complete")->assertOk();

        $this->patchJson("/api/booking/admin/reservations/{$reservation->id}/attendance", ['estado' => 'falto'])
            ->assertOk();
        $this->assertDatabaseHas('reservas', ['id' => $reservation->id, 'estado' => 'falto']);
        $this->assertDatabaseCount('recuperaciones', 0);
    }

    public function test_expiration_command_marks_only_available_credits_past_their_expiry_date()
    {
        $student = $this->yoguini();
        $cycle = $this->cycle('2026-09-01', '2026-10-31');
        $enrollment = $this->enrollment($student, $cycle);
        $schedule = $this->schedule(2);
        $expiredReservation = $this->reserve(
            $student,
            $enrollment,
            $this->makeClass($cycle, $schedule, '2026-09-08', '10:00:00'),
            'cancelada_con_aviso'
        );
        $availableReservation = $this->reserve(
            $student,
            $enrollment,
            $this->makeClass($cycle, $schedule, '2026-09-15', '10:00:00'),
            'cancelada_con_aviso'
        );
        $expired = $this->credit($student, $enrollment, $expiredReservation, '2026-09-30');
        $available = $this->credit($student, $enrollment, $availableReservation, '2026-10-31');

        $this->artisan('booking:expire-credits')->assertExitCode(0);

        $this->assertSame('vencida', $expired->fresh()->estado);
        $this->assertSame('disponible', $available->fresh()->estado);
    }

    private function yoguini($perfil = 'user')
    {
        return Yoguini::factory()->create(['perfil' => $perfil]);
    }

    private function schedule($weekday, array $attributes = [])
    {
        return Horario::create(array_merge([
            'dia_semana' => $weekday,
            'hora_inicio' => '10:00:00',
            'duracion_min' => 60,
            'nivel' => 'General',
            'profesor' => 'Instructor',
            'cupo' => 10,
            'activo' => true,
        ], $attributes));
    }

    private function cycle($start, $end, $weeklyClasses = 1)
    {
        return Ciclo::create([
            'nombre' => 'Cycle',
            'fecha_inicio' => $start,
            'fecha_fin' => $end,
            'clases_por_semana' => $weeklyClasses,
            'precio' => '1000.00',
            'activo' => true,
        ]);
    }

    private function makeClass(Ciclo $cycle, Horario $schedule, $date, $time, array $attributes = [])
    {
        return Clase::create(array_merge([
            'ciclo_id' => $cycle->id,
            'horario_id' => $schedule->id,
            'fecha' => $date,
            'hora_inicio' => $time,
            'duracion_min' => $schedule->duracion_min,
            'nivel' => $schedule->nivel,
            'profesor' => $schedule->profesor,
            'cupo' => $schedule->cupo,
            'estado' => 'programada',
        ], $attributes));
    }

    private function enrollment(Yoguini $yoguini, Ciclo $cycle)
    {
        return Inscripcion::create([
            'user_id' => $yoguini->id,
            'ciclo_id' => $cycle->id,
            'estado' => 'activa',
            'precio' => $cycle->precio,
        ]);
    }

    private function reserve(Yoguini $yoguini, Inscripcion $enrollment, Clase $class, $state = 'reservada')
    {
        return Reserva::create([
            'inscripcion_id' => $enrollment->id,
            'user_id' => $yoguini->id,
            'clase_id' => $class->id,
            'tipo' => 'regular',
            'estado' => $state,
        ]);
    }

    private function credit(Yoguini $yoguini, Inscripcion $enrollment, Reserva $reservation, $expires)
    {
        return Recuperacion::create([
            'reserva_origen_id' => $reservation->id,
            'user_id' => $yoguini->id,
            'inscripcion_id' => $enrollment->id,
            'vence_en' => $expires,
            'estado' => 'disponible',
        ]);
    }
}
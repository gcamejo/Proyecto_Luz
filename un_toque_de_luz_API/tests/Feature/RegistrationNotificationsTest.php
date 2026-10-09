<?php

namespace Tests\Feature;

use App\Models\Yoguini;
use App\Notifications\RegistrationCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class RegistrationNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_notifies_every_admin_but_not_students_and_skips_optional_email_when_unset()
    {
        $firstAdmin = Yoguini::factory()->create(['perfil' => 'Admin']);
        $secondAdmin = Yoguini::factory()->create(['perfil' => 'Admin']);
        $existingStudent = Yoguini::factory()->create(['perfil' => 'user']);
        Notification::fake();
        Mail::fake();
        config(['mail.admin_notification_email' => '']);

        $response = $this->postJson('/api/yoguinis', $this->registrationPayload())->assertCreated();
        $this->assertArrayNotHasKey('password', $response->json());

        Notification::assertSentTo($firstAdmin, RegistrationCreatedNotification::class);
        Notification::assertSentTo($secondAdmin, RegistrationCreatedNotification::class);
        Notification::assertNotSentTo($existingStudent, RegistrationCreatedNotification::class);
        Mail::assertNothingOutgoing();
    }

    public function test_notification_failure_does_not_fail_registration()
    {
        Yoguini::factory()->create(['perfil' => 'Admin']);
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('sensitive transport detail'));
        config(['mail.admin_notification_email' => '']);

        $this->postJson('/api/yoguinis', $this->registrationPayload())
            ->assertCreated();

        $this->assertDatabaseHas('yoguinis', ['email' => 'new-student@example.test']);
    }

    public function test_optional_email_failure_does_not_fail_registration()
    {
        Yoguini::factory()->create(['perfil' => 'Admin']);
        Notification::fake();
        config(['mail.admin_notification_email' => 'admin@example.test']);
        Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('sensitive transport detail'));

        $this->postJson('/api/yoguinis', $this->registrationPayload())
            ->assertCreated();

        $this->assertDatabaseHas('yoguinis', ['email' => 'new-student@example.test']);
    }

    public function test_same_registration_is_not_notified_twice()
    {
        Yoguini::factory()->create(['perfil' => 'Admin']);
        $student = Yoguini::factory()->create(['perfil' => 'user']);
        config(['mail.admin_notification_email' => 'admin@example.test']);
        Mail::shouldReceive('raw')->once();
        $notifier = app(\App\Services\RegistrationNotifier::class);

        $notifier->notifyAdmins($student, '2026-10-09T12:00:00+00:00');
        $notifier->notifyAdmins($student, '2026-10-09T12:00:00+00:00');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_admin_notification_endpoints_require_admin_and_can_mark_items_read()
    {
        $this->getJson('/api/admin/notifications')->assertUnauthorized();
        $this->postJson('/api/admin/notifications/00000000-0000-0000-0000-000000000000/read')->assertUnauthorized();

        $student = Yoguini::factory()->create(['perfil' => 'user']);
        $this->actingAs($student, 'sanctum');
        $this->getJson('/api/admin/notifications')->assertForbidden();
        $this->postJson('/api/admin/notifications/00000000-0000-0000-0000-000000000000/read')->assertForbidden();

        $admin = Yoguini::factory()->create(['perfil' => 'Admin']);
        $admin->notify(new RegistrationCreatedNotification(
            'Ana Ejemplo',
            'ana@example.test',
            '2026-10-09T12:00:00+00:00'
        ));
        $notification = $admin->notifications()->firstOrFail();
        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.nombre', 'Ana Ejemplo')
            ->assertJsonPath('notifications.0.email', 'ana@example.test');

        $this->postJson('/api/admin/notifications/'.$notification->id.'/read')->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    private function registrationPayload()
    {
        return [
            'nombre' => 'Nueva',
            'apellido' => 'Alumna',
            'direccion' => 'Calle 1',
            'numero' => 123,
            'telefono' => '555-0100',
            'fechaNacimiento' => '1990-01-01',
            'email' => 'new-student@example.test',
            'password' => 'secure-password',
        ];
    }
}

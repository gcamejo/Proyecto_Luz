<?php

namespace Tests\Feature;

use App\Models\Yoguini;
use App\Notifications\RegistrationCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_a_read_registration_notification()
    {
        $admin = Yoguini::factory()->create(['perfil' => 'Admin']);
        $admin->notify(new RegistrationCreatedNotification('Ana Ejemplo', 'ana@example.test', '2026-10-14T12:00:00Z'));
        $notification = $admin->notifications()->firstOrFail();
        $notification->markAsRead();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/admin/notifications/'.$notification->id)
            ->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_admin_cannot_delete_an_unread_notification()
    {
        $admin = Yoguini::factory()->create(['perfil' => 'Admin']);
        $admin->notify(new RegistrationCreatedNotification('Ana Ejemplo', 'ana@example.test', '2026-10-14T12:00:00Z'));
        $notification = $admin->notifications()->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/admin/notifications/'.$notification->id)
            ->assertStatus(409);

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    public function test_admin_cannot_delete_another_admins_notification()
    {
        $owner = Yoguini::factory()->create(['perfil' => 'Admin']);
        $otherAdmin = Yoguini::factory()->create(['perfil' => 'Admin']);
        $owner->notify(new RegistrationCreatedNotification('Ana Ejemplo', 'ana@example.test', '2026-10-14T12:00:00Z'));
        $notification = $owner->notifications()->firstOrFail();
        $notification->markAsRead();

        $this->actingAs($otherAdmin, 'sanctum')
            ->deleteJson('/api/admin/notifications/'.$notification->id)
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }
}

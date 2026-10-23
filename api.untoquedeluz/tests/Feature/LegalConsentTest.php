<?php

namespace Tests\Feature;

use App\Models\Consentimiento;
use App\Models\Configuracion;
use App\Models\Yoguini;
use App\Notifications\PrivacyDeletionRequestedNotification;
use App\Services\LegalConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegalConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_acceptance_and_saves_both_current_versions_without_ip(): void
    {
        $payload = $this->registrationPayload();

        $this->postJson('/api/yoguinis', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['acepta_legal']);

        $this->assertDatabaseCount('yoguinis', 0);

        $this->postJson('/api/yoguinis', $payload + ['acepta_legal' => true])
            ->assertCreated();

        $student = Yoguini::where('email', $payload['email'])->firstOrFail();
        $consents = Consentimiento::where('user_id', $student->id)->orderBy('tipo')->get();

        $this->assertSame(['privacidad', 'terminos'], $consents->pluck('tipo')->all());
        $this->assertSame(config('legal.policy_version'), $consents->first()->version);
        $this->assertSame(2, $consents->count());
        $this->assertNotContains('ip', DB::getSchemaBuilder()->getColumnListing('consentimientos'));
        $this->assertContains(strtolower(DB::getSchemaBuilder()->getColumnType('consentimientos', 'user_id')), ['int', 'integer']);
        $userForeignKey = collect(Schema::getForeignKeys('consentimientos'))
            ->first(fn (array $foreignKey) => $foreignKey['columns'] === ['user_id']);
        $this->assertNotNull($userForeignKey);
        $this->assertNotSame('cascade', strtolower((string) ($userForeignKey['on_delete'] ?? '')));
    }

    public function test_public_legal_parameters_use_the_saved_notice_configuration(): void
    {
        Configuracion::create(['clave' => 'horas_aviso_minimas', 'valor' => '18']);

        $this->getJson('/api/public/legal-parameters')
            ->assertOk()
            ->assertJsonPath('horas_aviso_minimas', 18);
    }

    public function test_existing_user_is_blocked_until_accepting_all_current_documents(): void
    {
        $student = Yoguini::factory()->create();
        Consentimiento::where('user_id', $student->id)->delete();
        $this->actingAs($student, 'sanctum');

        $this->getJson('/api/profile')->assertStatus(428)->assertJsonPath('code', 'legal_consent_required');
        $this->getJson('/api/profile/export')->assertOk();
        $this->getJson('/api/legal-consents/status')
            ->assertOk()
            ->assertJsonPath('accepted', false)
            ->assertJsonPath('pending.0', 'privacidad');

        $this->postJson('/api/legal-consents/accept', [
            'acepta' => true,
            'user_id' => $student->id + 100,
        ])->assertOk()->assertJsonPath('accepted', true);

        $this->assertDatabaseCount('consentimientos', 2);
        $this->assertSame(2, Consentimiento::where('user_id', $student->id)->count());
        $this->getJson('/api/profile')->assertOk();
    }

    public function test_policy_version_change_reactivates_gate_and_acceptance_records_the_new_version(): void
    {
        $student = Yoguini::factory()->create();
        $oldVersion = config('legal.policy_version');
        $this->actingAs($student, 'sanctum');
        config(['legal.policy_version' => 'test-next-version']);

        $this->getJson('/api/profile')->assertStatus(428);
        $this->postJson('/api/legal-consents/accept', ['acepta' => true, 'tipos' => ['privacidad']])
            ->assertOk()
            ->assertJsonPath('version', 'test-next-version')
            ->assertJsonPath('accepted', false)
            ->assertJsonPath('pending.0', 'terminos');

        $this->getJson('/api/profile')->assertStatus(428);
        $this->postJson('/api/legal-consents/accept', ['acepta' => true, 'tipos' => ['terminos']])
            ->assertOk()
            ->assertJsonPath('version', 'test-next-version')
            ->assertJsonPath('accepted', true);

        $this->assertDatabaseHas('consentimientos', [
            'user_id' => $student->id,
            'tipo' => 'privacidad',
            'version' => $oldVersion,
        ]);
        $this->assertDatabaseHas('consentimientos', [
            'user_id' => $student->id,
            'tipo' => 'terminos',
            'version' => 'test-next-version',
        ]);
        $this->assertDatabaseCount('consentimientos', 4);
    }

    public function test_admin_consent_history_route_is_read_only_and_restricted(): void
    {
        $student = Yoguini::factory()->create();
        $this->getJson('/api/admin/yoguinis/'.$student->id.'/legal-consents')->assertUnauthorized();

        $regularUser = Yoguini::factory()->create(['perfil' => 'user']);
        $this->actingAs($regularUser, 'sanctum')
            ->getJson('/api/admin/yoguinis/'.$student->id.'/legal-consents')
            ->assertForbidden();

        $admin = Yoguini::factory()->create(['perfil' => 'Admin']);
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/yoguinis/'.$student->id.'/legal-consents')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['tipo' => 'privacidad', 'version' => config('legal.policy_version')]);

        $this->patchJson('/api/admin/yoguinis/'.$student->id.'/legal-consents', [])->assertMethodNotAllowed();
    }

    public function test_personal_data_export_contains_own_records_but_no_password_or_tokens(): void
    {
        $student = Yoguini::factory()->create([
            'nombre' => 'Alumna',
            'email' => 'export@example.test',
            'password' => bcrypt('not-exported-password'),
        ]);
        $student->createToken('export-test-token');
        $this->actingAs($student, 'sanctum');

        $response = $this->getJson('/api/profile/export')
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="mis-datos.json"')
            ->assertJsonPath('profile.nombre', 'Alumna')
            ->assertJsonPath('profile.email', 'export@example.test')
            ->assertJsonCount(2, 'consentimientos');

        $this->assertArrayNotHasKey('password', $response->json('profile'));
        $this->assertArrayNotHasKey('personal_access_tokens', $response->json());
        $this->assertArrayNotHasKey('token', $response->json());
    }

    public function test_deletion_request_notifies_admin_without_deleting_user_data(): void
    {
        $admin = Yoguini::factory()->create(['perfil' => 'Admin']);
        $student = Yoguini::factory()->create([
            'nombre' => 'Solicitud',
            'apellido' => 'Privacidad',
            'email' => 'privacy-request@example.test',
        ]);
        $this->actingAs($student, 'sanctum');

        $this->postJson('/api/profile/deletion-request')
            ->assertAccepted()
            ->assertJsonFragment(['message' => 'Solicitud enviada a administración. Los datos no se eliminaron automáticamente.']);

        $notification = $admin->notifications()->firstOrFail();
        $this->assertSame(PrivacyDeletionRequestedNotification::class, $notification->type);
        $this->assertSame('solicitud_eliminacion', $notification->data['tipo']);
        $this->assertSame($student->email, $notification->data['email']);
        $this->assertDatabaseHas('yoguinis', ['id' => $student->id, 'email' => $student->email]);
            $admin = Yoguini::where('perfil', 'Admin')->firstOrFail();
            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/admin/notifications')
                ->assertOk()
                ->assertJsonPath('notifications.0.tipo', 'solicitud_eliminacion');
    }

    private function registrationPayload(): array
    {
        return [
            'nombre' => 'Nueva',
            'apellido' => 'Alumna',
            'direccion' => 'Calle 1',
            'numero' => 123,
            'telefono' => '555-0100',
            'fechaNacimiento' => '1990-01-01',
            'email' => 'legal-consent@example.test',
            'password' => 'secure-password',
        ];
    }
}
<?php

namespace Tests\Feature;

use App\Models\Yoguini;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LegacyApiSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_creates_a_sanctum_token_that_authenticates_the_profile_request(): void
    {
        $user = Yoguini::factory()->create([
            'email' => 'login@example.test',
            'password' => Hash::make('secure-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.test',
            'password' => 'secure-password',
        ])->assertOk();

        $plainTextToken = $response->json('access_token');
        $tokenSecret = explode('|', $plainTextToken, 2)[1] ?? $plainTextToken;

        $this->assertNotEmpty($plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Yoguini::class,
            'tokenable_id' => $user->id,
            'name' => 'auth_token',
            'token' => hash('sha256', $tokenSecret),
        ]);

        $this->withToken($plainTextToken)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_admin_can_create_edit_and_delete_an_activity_with_empty_fields(): void
    {
        $admin = Yoguini::factory()->create(['perfil' => 'Admin']);
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/actividades', [
            'urlImagen' => '',
            'titulo' => '',
            'descripcion' => '',
            'horarios' => '',
        ])->assertOk();

        $activityId = (int) \DB::table('actividades')->value('id');
        $this->assertDatabaseHas('actividades', [
            'id' => $activityId,
            'urlImagen' => 'logo_principal.jpg',
            'titulo' => null,
            'descripcion' => null,
            'horarios' => null,
        ]);

        $this->patchJson('/api/actividades/'.$activityId, [
            'urlImagen' => '',
            'titulo' => '',
            'descripcion' => '',
            'horarios' => '',
        ])->assertOk();

        $this->assertDatabaseHas('actividades', [
            'id' => $activityId,
            'urlImagen' => null,
            'titulo' => null,
            'descripcion' => null,
            'horarios' => null,
        ]);

        $this->deleteJson('/api/actividades/'.$activityId)->assertOk();
        $this->assertDatabaseMissing('actividades', ['id' => $activityId]);
    }

    public function test_admin_can_list_update_and_delete_a_yoguini_with_empty_legacy_fields(): void
    {
        $admin = Yoguini::factory()->create(['perfil' => 'Admin']);
        $student = Yoguini::factory()->create(['email' => 'student@example.test']);
        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/yoguinis')
            ->assertOk()
            ->assertJsonFragment(['id' => $student->id]);

        $this->patchJson('/api/yoguinis/'.$student->id, [
            'nombre' => '',
            'apellido' => '',
            'direccion' => '',
            'numero' => '',
            'telefono' => '',
            'fechaNacimiento' => '',
            'email' => '',
        ])->assertOk();

        $this->assertDatabaseHas('yoguinis', [
            'id' => $student->id,
            'nombre' => null,
            'apellido' => null,
            'direccion' => null,
            'numero' => null,
            'telefono' => null,
            'fechaNacimiento' => null,
            'email' => null,
        ]);

        $this->deleteJson('/api/yoguinis/'.$student->id)->assertOk();
        $this->assertDatabaseMissing('yoguinis', ['id' => $student->id]);
    }

    public function test_profile_can_save_empty_legacy_fields(): void
    {
        $user = Yoguini::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'nombre' => '',
                'apellido' => '',
                'direccion' => '',
                'numero' => '',
                'telefono' => '',
                'fechaNacimiento' => '',
                'email' => '',
            ])
            ->assertOk();

        $this->assertDatabaseHas('yoguinis', [
            'id' => $user->id,
            'nombre' => null,
            'apellido' => null,
            'direccion' => null,
            'numero' => null,
            'telefono' => null,
            'fechaNacimiento' => null,
            'email' => null,
        ]);
    }
}
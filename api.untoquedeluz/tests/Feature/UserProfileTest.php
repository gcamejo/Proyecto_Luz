<?php

namespace Tests\Feature;

use App\Models\Yoguini;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_profile()
    {
        $this->getJson('/api/profile')->assertUnauthorized();
        $this->patchJson('/api/profile', [])->assertUnauthorized();
    }

    public function test_user_can_view_own_profile()
    {
        $user = Yoguini::factory()->create([
            'nombre' => 'Lucia',
            'apellido' => 'Perez',
            'email' => 'lucia@example.com',
            'perfil' => 'user',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk();

        $response->assertJson([
            'id' => $user->id,
            'nombre' => 'Lucia',
            'apellido' => 'Perez',
            'email' => 'lucia@example.com',
        ]);
        $this->assertArrayNotHasKey('password', $response->json());
    }

    public function test_user_can_update_own_personal_data()
    {
        $user = Yoguini::factory()->create([
            'nombre' => 'Lucia',
            'apellido' => 'Perez',
            'direccion' => 'Calle Vieja',
            'numero' => 100,
            'telefono' => '111111',
            'fechaNacimiento' => '1992-05-10',
            'email' => 'lucia@example.com',
            'perfil' => 'user',
        ]);

        $payload = [
            'nombre' => 'Lucia Elena',
            'apellido' => 'Perez Gomez',
            'direccion' => 'Calle Nueva',
            'numero' => 250,
            'telefono' => '222222',
            'fechaNacimiento' => '1992-05-11',
            'email' => 'lucia.nueva@example.com',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', $payload)
            ->assertOk();

        $response->assertJson([
            'nombre' => 'Lucia Elena',
            'apellido' => 'Perez Gomez',
            'direccion' => 'Calle Nueva',
            'numero' => 250,
            'telefono' => '222222',
            'fechaNacimiento' => '1992-05-11',
            'email' => 'lucia.nueva@example.com',
        ]);
        $this->assertArrayNotHasKey('password', $response->json());

        $this->assertDatabaseHas('yoguinis', [
            'id' => $user->id,
            'nombre' => 'Lucia Elena',
            'email' => 'lucia.nueva@example.com',
        ]);
    }

    public function test_user_can_optionally_update_password()
    {
        $user = Yoguini::factory()->create([
            'password' => Hash::make('old-secret-password'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'direccion' => $user->direccion,
                'numero' => $user->numero,
                'telefono' => $user->telefono,
                'fechaNacimiento' => $user->fechaNacimiento,
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
    }

    public function test_password_change_notice_goes_to_the_previous_email_address()
    {
        $this->configureResendFake();
        $user = Yoguini::factory()->create(['email' => 'previous@example.test']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'direccion' => $user->direccion,
                'numero' => $user->numero,
                'telefono' => $user->telefono,
                'fechaNacimiento' => $user->fechaNacimiento,
                'email' => 'updated@example.test',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk();

        Http::assertSent(function (Request $request) {
            return $request->data()['to'] === ['previous@example.test']
                && $request->data()['subject'] === 'Aviso de cambio de contraseña'
                && strpos($request->data()['text'], 'new-secure-password') === false;
        });
    }

    public function test_password_changed_email_contains_no_password_secret()
    {
        $this->configureResendFake();
        $user = Yoguini::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'direccion' => $user->direccion,
                'numero' => $user->numero,
                'telefono' => $user->telefono,
                'fechaNacimiento' => $user->fechaNacimiento,
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk();

        Http::assertSent(function (Request $request) {
            return strpos($request->data()['text'], 'Se cambió la contraseña') !== false
                && strpos($request->data()['text'], 'new-secure-password') === false;
        });
    }

    public function test_profile_update_without_password_does_not_send_password_notice()
    {
        $this->configureResendFake();
        $user = Yoguini::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'direccion' => $user->direccion,
                'numero' => $user->numero,
                'telefono' => $user->telefono,
                'fechaNacimiento' => $user->fechaNacimiento,
                'email' => $user->email,
            ])
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_mail_failure_does_not_fail_password_change()
    {
        $user = Yoguini::factory()->create(['email' => 'profile@example.test']);
        $this->configureResendFake(Http::response(['name' => 'internal_error'], 500));

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'direccion' => $user->direccion,
                'numero' => $user->numero,
                'telefono' => $user->telefono,
                'fechaNacimiento' => $user->fechaNacimiento,
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    private function configureResendFake($response = null)
    {
        config([
            'services.resend.api_key' => 'test-resend-key',
            'services.resend.from' => 'alerts@example.test',
        ]);
        Http::fake([
            'https://api.resend.com/emails' => $response ?: Http::response(['id' => 'email_test_profile'], 200),
        ]);
    }

    public function test_user_cannot_take_another_users_email()
    {
        Yoguini::factory()->create(['email' => 'other@example.com']);
        $user = Yoguini::factory()->create(['email' => 'user@example.com']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'nombre' => 'Nombre',
                'apellido' => 'Apellido',
                'direccion' => 'Direccion',
                'numero' => 123,
                'telefono' => '123456',
                'fechaNacimiento' => '1990-01-01',
                'email' => 'other@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_cannot_escalate_role_via_profile_update()
    {
        $user = Yoguini::factory()->create(['perfil' => 'user']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'nombre' => 'Nombre',
                'apellido' => 'Apellido',
                'direccion' => 'Direccion',
                'numero' => 123,
                'telefono' => '123456',
                'fechaNacimiento' => '1990-01-01',
                'email' => $user->email,
                'perfil' => 'Admin',
            ])
            ->assertOk();

        $this->assertSame('user', $user->fresh()->perfil);
    }
}

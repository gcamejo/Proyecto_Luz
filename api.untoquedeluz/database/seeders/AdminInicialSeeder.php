<?php

namespace Database\Seeders;

use App\Models\Yoguini;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class AdminInicialSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) config('initial_admin.email'));
        $password = (string) config('initial_admin.password');

        if ($email === '' && isset($this->command)) {
            $email = trim((string) $this->command->ask('Email del administrador inicial'));
        }

        if ($password === '' && isset($this->command)) {
            $password = (string) $this->command->secret('Contraseña inicial (mínimo 12 caracteres)');
            $confirmation = (string) $this->command->secret('Confirmá la contraseña');

            if (!hash_equals($password, $confirmation)) {
                throw new InvalidArgumentException('Las contraseñas ingresadas no coinciden.');
            }
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('INITIAL_ADMIN_EMAIL debe ser un email válido.');
        }

        if (strlen($password) < 12 || strlen($password) > 100) {
            throw new InvalidArgumentException('La contraseña inicial debe tener entre 12 y 100 caracteres.');
        }

        $admin = Yoguini::firstOrNew(['email' => $email]);

        if (!$admin->exists) {
            $admin->nombre = 'Administrador';
            $admin->apellido = 'Inicial';
            $admin->password = Hash::make($password);
        }

        $admin->perfil = 'Admin';
        $admin->save();
    }
}
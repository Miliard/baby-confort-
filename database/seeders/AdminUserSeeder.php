<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario para entrar al panel /admin.
        // CAMBIA la contraseña después de tu primer ingreso.
        User::firstOrCreate(
            ['email' => 'admin@babyconfort.com'],
            [
                'name'     => 'Administrador',
                'password' => Hash::make('babyconfort2026'),
            ]
        );
    }
}

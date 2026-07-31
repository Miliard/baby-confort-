<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Carga la info oficial del Cepillo de Dientes Infantil Divertido (idempotente).
        (new \Database\Seeders\CepilloDientesSeeder())->run();
    }

    public function down(): void
    {
        //
    }
};

<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Ejecuta el seeder de productos extra (idempotente: no duplica).
        (new \Database\Seeders\ProductosExtraSeeder())->run();
    }

    public function down(): void
    {
        //
    }
};

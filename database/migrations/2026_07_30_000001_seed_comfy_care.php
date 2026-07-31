<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Carga la info oficial de los Pañales Comfy Care 50un. (idempotente: no duplica).
        (new \Database\Seeders\ComfyCareSeeder())->run();
    }

    public function down(): void
    {
        //
    }
};

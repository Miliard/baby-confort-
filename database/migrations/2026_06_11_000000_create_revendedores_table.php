<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('revendedores')) {
            Schema::create('revendedores', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('telefono')->nullable();
                $table->string('codigo')->unique();
                $table->unsignedTinyInteger('porcentaje')->default(10);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('revendedores');
    }
};

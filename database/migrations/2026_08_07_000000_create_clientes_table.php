<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clientes')) {
            Schema::create('clientes', function (Blueprint $table) {
                $table->id();
                $table->string('telefono', 20)->unique(); // solo dígitos, es la llave
                $table->string('nombre')->nullable();
                $table->text('direccion')->nullable();
                $table->string('municipio', 80)->nullable();
                $table->string('departamento', 80)->nullable();
                $table->unsignedInteger('veces')->default(1); // cuántos envíos le hemos hecho
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};

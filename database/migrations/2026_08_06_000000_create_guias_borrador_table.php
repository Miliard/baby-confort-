<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guias_borrador')) {
            Schema::create('guias_borrador', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('telefono', 30)->nullable();
                $table->string('telefono_recibe', 30)->nullable();
                $table->text('direccion')->nullable();
                $table->string('municipio', 80)->nullable();
                $table->string('departamento', 80)->nullable();
                $table->text('descripcion')->nullable();
                $table->decimal('cobrar', 8, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guias_borrador');
    }
};

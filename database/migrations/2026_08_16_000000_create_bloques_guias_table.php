<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bloques_guias')) {
            Schema::create('bloques_guias', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');                       // cuándo se compró
                $table->unsignedInteger('cantidad');         // cuántas guías trae (ej: 500)
                $table->decimal('costo', 10, 2);             // lo que se pagó (ej: 1400.00)
                $table->unsignedInteger('usadas_antes')->default(0); // ajuste manual si venías a medias
                $table->string('nota', 190)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bloques_guias');
    }
};

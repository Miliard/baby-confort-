<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('express_entregas')) {
            Schema::create('express_entregas', function (Blueprint $table) {
                $table->id();
                $table->date('fecha')->index();
                $table->string('orden', 40)->index();   // número de Express (puede repetirse: varios bultos)
                $table->string('nombre', 190);
                $table->string('zona', 120)->nullable();
                $table->decimal('monto', 10, 2)->default(0);      // lo que cobró Express al entregar
                $table->decimal('comision', 10, 2)->default(0);   // el 2% que se queda Express
                $table->decimal('total', 10, 2)->default(0);      // lo que deposita
                $table->boolean('aiwibi')->default(false);        // no es plata nuestra: va a Remuneración

                // Para los bultos en $0: qué pasó realmente.
                $table->string('caso', 20)->nullable();           // transferencia | bulto_extra | devolucion
                $table->decimal('transferido', 10, 2)->nullable();

                $table->string('huella', 64)->unique();           // evita duplicar si se pega dos veces
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('express_entregas');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cierres_dia')) {
            Schema::create('cierres_dia', function (Blueprint $table) {
                $table->id();
                $table->date('fecha')->unique();
                $table->decimal('proveedor', 10, 2)->default(0);   // lo que cobró el proveedor ese día
                $table->decimal('gastos', 10, 2)->default(0);      // otros gastos del día
                $table->decimal('costo_bulto', 8, 2)->default(2.80);
                $table->text('nota')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cierres_dia');
    }
};

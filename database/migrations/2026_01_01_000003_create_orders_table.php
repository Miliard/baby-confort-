<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('phone');
            $table->text('address');
            $table->string('municipio');
            $table->string('payment');            // transferencia | efectivo | link
            $table->decimal('total', 10, 2);
            $table->json('items');                // detalle del pedido
            $table->string('status')->default('nuevo'); // nuevo|contactado|entregado
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

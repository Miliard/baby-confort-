<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('size');      // precio de ESTA talla
            $table->string('weight')->nullable()->after('price');            // peso, ej "17-29 kg" o "180 ML"
            $table->unsignedInteger('combo_qty')->nullable()->after('quantity');   // combo opcional por talla
            $table->decimal('combo_price', 10, 2)->nullable()->after('combo_qty');
        });
    }
    public function down(): void
    {
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->dropColumn(['price', 'weight', 'combo_qty', 'combo_price']);
        });
    }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('gallery')->nullable()->after('image');       // fotos extra
            $table->json('features')->nullable()->after('gallery');    // [{icon, text}]
            $table->string('made_in')->nullable()->after('features');  // "AUSTRALIA"
            $table->string('badge')->nullable()->after('made_in');     // "Producto galardonado"
            $table->text('stock_warning')->nullable()->after('badge'); // aviso de pocas unidades
        });
    }
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['gallery', 'features', 'made_in', 'badge', 'stock_warning']);
        });
    }
};

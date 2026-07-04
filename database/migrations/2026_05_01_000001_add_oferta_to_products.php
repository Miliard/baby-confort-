<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('oferta')->nullable()->after('badge'); // texto de la burbuja de oferta
        });
    }
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('oferta');
        });
    }
};

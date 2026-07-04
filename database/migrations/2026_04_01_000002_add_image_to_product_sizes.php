<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->string('image')->nullable()->after('details');         // link de la foto de la talla
            $table->string('image_upload')->nullable()->after('image');    // o foto subida
        });
    }
    public function down(): void
    {
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->dropColumn(['image', 'image_upload']);
        });
    }
};

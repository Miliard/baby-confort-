<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'revendedor')) {
                $table->string('revendedor')->nullable()->after('descuento');
            }
            if (! Schema::hasColumn('orders', 'comision')) {
                $table->decimal('comision', 8, 2)->default(0)->after('revendedor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['revendedor', 'comision']);
        });
    }
};

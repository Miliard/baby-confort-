<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->string('icono')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Migrar las 4 categorías existentes (con el nombre que ya tuvieran configurado).
        $defaults = [
            ['slug' => 'bebe',       'nombre' => \App\Models\Setting::get('cat_bebe', 'Para bebé'),              'icono' => '👶'],
            ['slug' => 'accesorios', 'nombre' => \App\Models\Setting::get('cat_accesorios', 'Accesorios para bebé'), 'icono' => '🍼'],
            ['slug' => 'mujer',      'nombre' => \App\Models\Setting::get('cat_mujer', 'Para mujer'),            'icono' => '🌸'],
            ['slug' => 'adulto',     'nombre' => \App\Models\Setting::get('cat_adulto', 'Para adulto'),          'icono' => '🧑'],
        ];

        foreach ($defaults as $i => $d) {
            \App\Models\Categoria::updateOrCreate(
                ['slug' => $d['slug']],
                ['nombre' => $d['nombre'], 'icono' => $d['icono'], 'orden' => $i + 1, 'activo' => true]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};

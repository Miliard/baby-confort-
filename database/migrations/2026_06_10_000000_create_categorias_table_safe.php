<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Crear la tabla solo si no existe (por si quedó de un intento anterior).
        if (! Schema::hasTable('categorias')) {
            Schema::create('categorias', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('nombre');
                $table->string('icono')->nullable();
                $table->unsignedInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        // Seed de las 4 categorías por defecto. Envuelto para NO romper el deploy si algo falla.
        try {
            $defaults = [
                ['slug' => 'bebe',       'nombre' => 'Para bebé',              'icono' => '👶', 'orden' => 1],
                ['slug' => 'accesorios', 'nombre' => 'Accesorios para bebé',   'icono' => '🍼', 'orden' => 2],
                ['slug' => 'mujer',      'nombre' => 'Para mujer',             'icono' => '🌸', 'orden' => 3],
                ['slug' => 'adulto',     'nombre' => 'Para adulto',            'icono' => '🧑', 'orden' => 4],
            ];

            foreach ($defaults as $d) {
                if (DB::table('categorias')->where('slug', $d['slug'])->exists()) {
                    continue;
                }
                // Conservar el nombre que el usuario hubiera puesto en Configuración, si existe.
                $nombre = DB::table('settings')->where('key', 'cat_' . $d['slug'])->value('value');
                DB::table('categorias')->insert([
                    'slug'       => $d['slug'],
                    'nombre'     => ($nombre !== null && trim($nombre) !== '') ? $nombre : $d['nombre'],
                    'icono'      => $d['icono'],
                    'orden'      => $d['orden'],
                    'activo'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Si el seed falla, la tienda usa las 4 por defecto igual; no rompemos nada.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};

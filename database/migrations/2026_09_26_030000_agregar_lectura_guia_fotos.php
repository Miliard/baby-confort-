<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que la IA leyó de la etiqueta, y si el teléfono lo pusiste vos.
 *
 * lectura     → todos los datos impresos en la etiqueta, leídos de la foto:
 *               teléfono, nombre, dirección, municipio, departamento, monto,
 *               contenido y número de orden. Es lo que permite emparejar una
 *               foto aunque el teléfono se haya leído mal: si coinciden el
 *               municipio, el monto y el nombre, entre veinte chats hay uno
 *               solo así.
 *
 * tel_manual  → el teléfono lo escribiste vos. El emparejador no lo toca.
 *               Antes eso se sabía por un truco —"lo leído igual a lo puesto"—
 *               que dejaría de servir ahora que la IA puede leer el número
 *               bien a la primera: una lectura correcta parecería escrita a
 *               mano y nunca se emparejaría. Una marca propia no se confunde.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $t) {
            if (! Schema::hasColumn('guia_fotos', 'lectura')) {
                $t->text('lectura')->nullable()->after('tel_leido');
            }
            if (! Schema::hasColumn('guia_fotos', 'tel_manual')) {
                $t->boolean('tel_manual')->default(false)->after('lectura');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guia_fotos')) return;

        Schema::table('guia_fotos', function (Blueprint $t) {
            foreach (['lectura', 'tel_manual'] as $c) {
                if (Schema::hasColumn('guia_fotos', $c)) $t->dropColumn($c);
            }
        });
    }
};

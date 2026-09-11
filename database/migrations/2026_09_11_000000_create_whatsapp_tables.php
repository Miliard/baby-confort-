<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel de WhatsApp: conversaciones y mensajes.
 *
 * Una conversación por número de cliente. Los mensajes guardan el id que les da
 * Meta para no duplicarlos: el webhook a veces manda el mismo aviso dos veces y
 * sin esa llave el chat se llenaría de repetidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_conversaciones')) {
            Schema::create('wa_conversaciones', function (Blueprint $table) {
                $table->id();

                // El número tal como lo manda Meta (con código de país) y los
                // últimos 8 dígitos, que es como identificamos a la gente acá.
                $table->string('wa_id', 30)->unique();
                $table->string('telefono', 12)->index();
                $table->string('nombre', 120)->nullable();

                $table->string('ultimo_texto', 300)->nullable();
                $table->timestamp('ultimo_mensaje_at')->nullable()->index();

                // Hora del último mensaje DEL CLIENTE. Marca la ventana de 24
                // horas de Meta: pasada esa hora solo se pueden mandar
                // plantillas aprobadas, no texto libre.
                $table->timestamp('ultimo_del_cliente_at')->nullable();

                // Quién la está atendiendo, para que no contesten dos a la vez.
                $table->foreignId('agente_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('tomada_at')->nullable();

                $table->unsignedInteger('sin_leer')->default(0);
                $table->boolean('archivada')->default(false)->index();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wa_mensajes')) {
            Schema::create('wa_mensajes', function (Blueprint $table) {
                $table->id();

                $table->foreignId('conversacion_id')
                    ->constrained('wa_conversaciones')->cascadeOnDelete();

                // Id de Meta. Único: si el webhook repite el aviso, se ignora.
                $table->string('wa_message_id', 128)->nullable()->unique();

                $table->enum('direccion', ['entrante', 'saliente'])->index();
                $table->string('tipo', 20)->default('text');   // text | image | video | audio | document

                $table->text('texto')->nullable();

                // Para las imágenes: el id que da Meta y la copia ya bajada.
                $table->string('media_id', 128)->nullable();
                $table->string('media_ruta', 255)->nullable();

                // enviando | enviado | entregado | leido | fallido
                $table->string('estado', 20)->default('enviado')->index();
                $table->string('error', 300)->nullable();

                // Qué agente lo mandó (vacío si lo mandó el cliente o el robot).
                $table->foreignId('user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->boolean('automatico')->default(false);

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_mensajes');
        Schema::dropIfExists('wa_conversaciones');
    }
};

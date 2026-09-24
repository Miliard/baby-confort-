<?php

namespace App\Services;

use App\Models\GuiaFoto;
use App\Models\WaConversacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Manda al chat las fotos de los paquetes que ya salieron.
 *
 * La foto de la etiqueta es el comprobante: dice que el paquete existe, que
 * está armado y que va en camino. Hoy el cliente la ve solo si entra a su
 * enlace de rastreo, y la mayoría no entra. Mandándosela, la garantía le llega
 * sola.
 *
 * El emparejado no se adivina: la guía sale del QR de la etiqueta, y con la
 * guía ya viene el teléfono guardado. De ahí a la conversación hay un solo
 * paso, por los últimos ocho dígitos.
 *
 * NADA se manda solo. Esta clase arma la lista y la revisa; mandar lo decide
 * Wil con un botón, después de mirarla. Una foto pegada a la guía equivocada
 * le llega a un cliente real y no hay cómo sacarla.
 */
class FotosAlChat
{
    /** Estados posibles de una foto, y qué significan para quien mira. */
    public const LISTA      = 'lista';        // se puede mandar ahora
    public const ESPERA     = 'espera';       // la ventana de 24 h está cerrada
    public const SIN_CHAT   = 'sin_chat';     // ese número nunca escribió acá
    public const SIN_NUMERO = 'sin_numero';   // la etiqueta no dejó teléfono

    /** ¿La base ya tiene lo que hace falta? */
    public static function disponible(): bool
    {
        try {
            return Schema::hasTable('guia_fotos')
                && Schema::hasColumn('guia_fotos', 'chat_enviada_at')
                && WaConversacion::hayTabla();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Las fotos que todavía no se mandaron, ya emparejadas.
     *
     * Solo las de los últimos días: una etiqueta de hace tres semanas no se
     * manda más, y tenerla ahí solo ensucia la lista de lo que sí importa.
     *
     * @return array<int,array> cada una con ['foto', 'conv', 'estado', 'porque']
     */
    public static function pendientes(int $dias = 7, int $tope = 120, ?string $lote = null): array
    {
        if (! static::disponible()) return [];

        try {
            $fotos = GuiaFoto::whereNotNull('ruta')
                // Un lote a la vez cuando se pide uno. El '' es el grupo de
                // las que subieron sin lote — pasa con las que ya existían por
                // el PDF y se les pegó la foto después.
                ->when($lote !== null, fn ($q) => $lote === ''
                    ? $q->whereNull('lote')
                    : $q->where('lote', $lote))
                ->whereNull('chat_enviada_at')
                // Las fotos se borran solas del disco a los tantos días. Una
                // cuya imagen ya no está no se puede mandar: Meta la va a
                // buscar a nuestro sitio y no la va a encontrar. El registro
                // del pedido se queda; la que se fue es la imagen.
                ->whereNull('foto_borrada_at')
                ->where('created_at', '>=', now()->subDays($dias))
                ->orderByDesc('id')
                ->limit($tope)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Fotos al chat, leyendo pendientes: ' . $e->getMessage());
            return [];
        }

        $lista = [];

        foreach ($fotos as $f) {
            $lista[] = static::revisar($f);
        }

        return $lista;
    }

    /**
     * Lo mismo, pero repartido en lotes: una tanda por subida.
     *
     * Por qué por lotes y no todo junto, que es como estaba: las fotos entran
     * de a montones —subís las quince del día de una vez— y ese montón es una
     * unidad de trabajo real. Un botón solo para todo mezcla la tanda de hoy
     * con lo que quedó colgando de anteayer, y ahí ya no sabés qué mandaste.
     *
     * Es el mismo criterio del Excel: se cierra por tandas, y lo que entra
     * después arranca la siguiente.
     *
     * Del más nuevo al más viejo, porque lo que acabás de subir es lo que vas
     * a mandar ahora.
     *
     * @return array<int,array> ['clave', 'titulo', 'filas', 'listas']
     */
    public static function porLotes(int $dias = 7, int $tope = 120): array
    {
        $grupos = [];

        foreach (static::pendientes($dias, $tope) as $fila) {
            $clave = (string) ($fila['foto']->lote ?? '');

            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'clave'  => $clave,
                    'titulo' => $clave === ''
                        ? 'Sin tanda'
                        : 'Tanda del ' . $fila['foto']->loteBonito(),
                    'filas'  => [],
                    'listas' => 0,
                    // Cuántas hay de cada estado. El botón solo puede decir un
                    // número —"mandar las 8"— y ese número deja la pregunta
                    // abierta: ¿y las otras dos? Con el desglose al lado, la
                    // respuesta está antes de que la pregunta aparezca.
                    'cuenta' => [
                        static::LISTA      => 0,
                        static::ESPERA     => 0,
                        static::SIN_CHAT   => 0,
                        static::SIN_NUMERO => 0,
                    ],
                ];
            }

            $grupos[$clave]['filas'][] = $fila;
            $grupos[$clave]['cuenta'][$fila['estado']]++;

            if ($fila['estado'] === static::LISTA) {
                $grupos[$clave]['listas']++;
            }
        }

        // Las claves son la fecha y hora de la subida, así que ordenarlas al
        // revés como texto ya deja arriba la más nueva. El grupo sin lote, al
        // final: son casos sueltos, no la tanda del día.
        uasort($grupos, function ($a, $b) {
            if ($a['clave'] === '') return 1;
            if ($b['clave'] === '') return -1;

            return strcmp($b['clave'], $a['clave']);
        });

        return array_values($grupos);
    }

    /**
     * En qué estado está una foto: a quién le iría y si se puede mandar.
     *
     * Devuelve también la conversación, para no volver a buscarla al mandar.
     */
    public static function revisar(GuiaFoto $foto): array
    {
        $corto = GuiaFoto::telefonoCorto($foto->telefono);

        if (! $corto || strlen($corto) !== 8) {
            return [
                'foto'   => $foto,
                'conv'   => null,
                'estado' => static::SIN_NUMERO,
                'porque' => 'La etiqueta no dejó un teléfono de 8 dígitos. '
                          . 'Escribilo en la guía y vuelve a aparecer acá.',
            ];
        }

        $conv = static::conversacionDe($corto);

        if (! $conv) {
            return [
                'foto'   => $foto,
                'conv'   => null,
                'estado' => static::SIN_CHAT,
                'porque' => 'Ese número nunca escribió a este WhatsApp, así que no hay '
                          . 'conversación adonde mandarla.',
            ];
        }

        if (! $conv->ventanaAbierta()) {
            return [
                'foto'   => $foto,
                'conv'   => $conv,
                'estado' => static::ESPERA,
                'porque' => 'Pasaron más de 24 horas desde que escribió. En cuanto vuelva a '
                          . 'escribir, esta foto se va a poder mandar.',
            ];
        }

        return [
            'foto'   => $foto,
            'conv'   => $conv,
            'estado' => static::LISTA,
            'porque' => 'Ventana abierta por ' . $conv->ventanaLegible() . '.',
        ];
    }

    /**
     * La conversación de ese número, si existe.
     *
     * Existe a propósito, y no se crea: si ese cliente nunca escribió, no hay
     * a quién mandarle nada. Crear la conversación solo llenaría la lista de
     * chats vacíos que nunca van a tener respuesta.
     */
    public static function conversacionDe(string $telefonoCorto): ?WaConversacion
    {
        try {
            return WaConversacion::where('telefono', $telefonoCorto)
                ->orderByDesc('ultimo_mensaje_at')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * El texto que va de pie de la foto.
     *
     * Corto a propósito. La foto ya dice casi todo; el texto solo tiene que
     * decir de qué es y dejar el enlace para después. Un párrafo largo debajo
     * de una imagen no se lee.
     */
    public static function pie(GuiaFoto $foto): string
    {
        $nombre = trim((string) $foto->nombre);

        $saludo = $nombre !== ''
            ? '¡Hola ' . Str::of($nombre)->trim()->before(' ')->title() . '! '
            : '';

        $t = $saludo . "\u{1F4E6} *Tu paquete ya va en camino.*\n"
           . "Esta es la etiqueta con la que viaja.\n\n";

        if (filled($foto->guia)) {
            $t .= "*Gu\u{ED}a:* {$foto->guia}\n";
        }

        if ((float) $foto->cobrar > 0) {
            $t .= '*A pagar al recibir:* $' . number_format((float) $foto->cobrar, 2) . "\n";
        }

        $t .= "\n*Segu\u{ED} tu paquete ac\u{E1}:*\n" . $foto->enlaceRastreo();

        return $t;
    }

    /**
     * Manda de una sola vez las de UNA tanda.
     *
     * Las que no se pueden ni se tocan: quedan como estaban y vuelven a salir
     * en la lista la próxima vez. Lo que falla se anota en la propia fila con
     * el motivo, no solo en el registro del servidor: el registro no lo lee
     * nadie, y la fila sí.
     *
     * La tanda se vuelve a leer de la base acá dentro, no se recibe hecha
     * desde la pantalla. Entre que se dibujó la lista y que se apretó el
     * botón pueden haber pasado minutos, y la ventana de 24 horas se mueve
     * sola con el reloj: lo que decía "lista" hace rato puede ya no estarlo.
     *
     * @param  string  $lote  '' es el grupo de las que no tienen tanda
     * @return array ['mandadas' => int, 'fallaron' => int, 'saltadas' => int]
     */
    public static function mandarLote(string $lote, ?int $userId = null): array
    {
        $mandadas = 0;
        $fallaron = 0;
        $saltadas = 0;

        foreach (static::pendientes(7, 120, $lote) as $fila) {
            if ($fila['estado'] !== static::LISTA) {
                $saltadas++;
                continue;
            }

            static::mandarUna($fila['foto'], $fila['conv'], $userId)
                ? $mandadas++
                : $fallaron++;
        }

        return compact('mandadas', 'fallaron', 'saltadas');
    }

    /** Una sola, con su conversación ya encontrada. */
    public static function mandarUna(GuiaFoto $foto, WaConversacion $conv, ?int $userId = null): bool
    {
        $url = $foto->url();

        if (! $url) {
            static::anotarError($foto, 'La imagen ya no está en el disco.');
            return false;
        }

        try {
            // Absoluta: Meta va a buscar la foto a nuestro sitio desde afuera,
            // así que una ruta relativa no llegaría a ninguna parte.
            $m = WhatsappApi::enviarImagen($conv, url($url), static::pie($foto), $userId);

            if ($m->estado === 'fallido') {
                static::anotarError($foto, (string) ($m->error ?: 'WhatsApp la rechazó.'));
                return false;
            }

            $foto->forceFill([
                'chat_enviada_at' => now(),
                'chat_error'      => null,
            ])->save();

            return true;
        } catch (\Throwable $e) {
            static::anotarError($foto, $e->getMessage());
            return false;
        }
    }

    /**
     * Darla por mandada sin mandarla.
     *
     * Hace falta para las que no tienen chat: esas se las pasás por otro lado
     * y si no hay cómo sacarlas de la lista, se quedan ahí para siempre y la
     * lista deja de servir para saber qué falta.
     */
    public static function omitir(GuiaFoto $foto): void
    {
        try {
            $foto->forceFill([
                'chat_enviada_at' => now(),
                'chat_error'      => 'Marcada a mano, sin mandar por el chat.',
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Fotos al chat, omitiendo: ' . $e->getMessage());
        }
    }

    private static function anotarError(GuiaFoto $foto, string $motivo): void
    {
        try {
            $foto->forceFill(['chat_error' => mb_substr(trim($motivo), 0, 190)])->save();
        } catch (\Throwable $e) {
            Log::warning('Fotos al chat: ' . $motivo);
        }
    }
}

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

            $r = static::mandarUna($fila['foto'], $fila['conv'], $userId);

            // null = ya había salido y el freno lo paró. No es una falla.
            if ($r === true)      $mandadas++;
            elseif ($r === false) $fallaron++;
            else                  $saltadas++;
        }

        return compact('mandadas', 'fallaron', 'saltadas');
    }

    /**
     * Una sola, con su conversación ya encontrada.
     *
     * Devuelve true si salió, false si falló, y null si NO se mandó porque ya
     * había salido antes. Son tres cosas distintas y se cuentan distinto: un
     * repetido frenado no es un error, y contarlo como "no salió" haría creer
     * que algo anda mal cuando en realidad funcionó el freno.
     */
    public static function mandarUna(GuiaFoto $foto, WaConversacion $conv, ?int $userId = null): ?bool
    {
        $url = $foto->url();

        if (! $url) {
            static::anotarError($foto, 'La imagen ya no está en el disco.');
            return false;
        }

        /*
         * LA VALIDACIÓN CONTRA REPETIDOS. Va acá, en el último paso, y no en
         * la lista: la lista puede estar vieja, esto no.
         *
         * Se "reserva" la foto antes de mandarla, marcándola como mandada en
         * la base, y SOLO si todavía no lo estaba. Es una sola operación: si
         * dos pedidos llegan a la vez —un doble toque, dos personas con la
         * pantalla abierta, una tanda subida dos veces— uno solo gana la
         * reserva. El otro ve que la fila ya no está libre y no manda nada.
         *
         * Preguntar primero "¿ya se mandó?" y después mandar NO alcanza: entre
         * la pregunta y el envío hay un instante, y en ese instante el otro
         * pedido también pregunta, también ve que no, y también manda. Por
         * eso se pregunta y se marca en el mismo movimiento.
         */
        $reservada = GuiaFoto::where('id', $foto->id)
            ->whereNull('chat_enviada_at')
            ->update(['chat_enviada_at' => now()]);

        if ($reservada === 0) {
            // Ya había salido, o la está mandando otro en este momento.
            // Ninguna de las dos cosas es un error: simplemente no se repite.
            return null;
        }

        try {
            // Absoluta: Meta va a buscar la foto a nuestro sitio desde afuera,
            // así que una ruta relativa no llegaría a ninguna parte.
            $m = WhatsappApi::enviarImagen($conv, url($url), static::pie($foto), $userId);

            if ($m->estado === 'fallido') {
                // Se suelta la reserva. Si no, una foto que Meta rechazó
                // quedaría marcada como mandada para siempre y nunca más
                // volvería a la lista para reintentarla.
                static::soltar($foto);
                static::anotarError($foto, (string) ($m->error ?: 'WhatsApp la rechazó.'));
                return false;
            }

            // Salió: la reserva queda como marca definitiva. Se limpia el
            // error por si venía de un intento anterior que había fallado.
            //
            // Y se guarda con QUÉ mensaje salió. WhatsApp contesta en dos
            // tiempos: ahora dijo "recibido", pero todavía tiene que ir a
            // buscar la imagen, y eso puede fallar después. Cuando falle, el
            // aviso va a decir qué mensaje fue — y con esto se sabe qué foto.
            $foto->forceFill([
                'chat_error'      => null,
                'chat_mensaje_id' => $m->id,
            ])->save();

            /*
             * La foto salió: la conversación pasa a Entregados.
             *
             * Es el último paso del recorrido:
             *
             *   orden → Pedidos · enlace → Preparados · foto → Entregados
             *
             * Así, lo que se quede en Preparados es exactamente lo que tiene el
             * enlace mandado y la foto NO. Esa lista es la que dice qué fotos
             * faltan, sin tener que ir a buscarlas a otra pantalla.
             */
            try {
                Etiquetado::marcarEntregada($conv);
            } catch (\Throwable $e) {
                // Etiquetar es comodidad; que falle no puede desmentir que la
                // foto ya salió.
                Log::warning('Etiquetando tras mandar la foto: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            static::soltar($foto);
            static::anotarError($foto, $e->getMessage());
            return false;
        }
    }

    /**
     * Desde qué momento cuenta "hoy", en el formato en que se guarda el lote.
     *
     * El lote lo arma el navegador en UTC ("2026-09-25 21:29:03"). El "hoy"
     * de acá empieza a medianoche de El Salvador, que en UTC son las 6 de la
     * mañana. Se convierte una vez y se compara como texto: con este formato,
     * comparar texto da el mismo orden que comparar fechas.
     */
    public static function inicioDeHoy(): string
    {
        return now()->timezone(config('app.zona_local'))
            ->startOfDay()
            ->utc()
            ->format('Y-m-d H:i:s');
    }

    /**
     * Las fotos que se subieron hoy pero NO están en la lista de por mandar.
     *
     * ACÁ ESTABAN LAS FOTOS QUE "DESAPARECÍAN". No se perdían: se guardaban
     * bien, pero en una guía que ya estaba marcada como mandada — porque
     * salió antes, o porque la quitaste de la lista con la ✕ o el tacho. La
     * lista solo muestra las pendientes, así que esas no aparecían en ningún
     * lado.
     *
     * Y el contador tampoco las contaba: contaba por fecha de creación de la
     * fila, y una foto subida hoy a una guía de ayer tiene fecha de ayer.
     *
     * Ahora se cuenta por el LOTE, que se rehace en cada subida, y las que
     * quedaron fuera se muestran aparte con el motivo y un botón para
     * devolverlas a la lista.
     */
    public static function subidasHoyFueraDeLista(): array
    {
        if (! static::disponible()) return [];

        try {
            $deHoy = GuiaFoto::whereNotNull('ruta')
                ->whereNotNull('chat_enviada_at')
                ->where('lote', '>=', static::inicioDeHoy())
                ->orderByDesc('chat_enviada_at')
                ->limit(60)
                ->get();
        } catch (\Throwable $e) {
            $deHoy = collect();
        }

        // Y además, de cualquier día de la última semana, las que WhatsApp NO
        // entregó. Esas son las que más importan de toda la página: el panel
        // las daba por mandadas y el cliente nunca recibió nada. Limitarlas a
        // "hoy" dejaría afuera justo las que llevan días sin llegar.
        $fallidas = static::fallidasRecientes();

        return $deHoy->merge($fallidas)
            ->unique('id')
            ->sortByDesc('chat_enviada_at')
            ->values()
            ->all();
    }

    /**
     * Fotos marcadas como mandadas cuyo mensaje WhatsApp terminó rechazando.
     *
     * Para las nuevas alcanza con el vínculo al mensaje. Las viejas no lo
     * tienen, pero el mensaje guardó la dirección completa de la imagen que
     * mandó — y la foto guarda su ruta. Se cruzan por ahí.
     */
    public static function fallidasRecientes(int $dias = 7)
    {
        try {
            $direcciones = \App\Models\WaMensaje::where('direccion', 'saliente')
                ->where('tipo', 'image')
                ->where('estado', 'fallido')
                ->where('created_at', '>=', now()->subDays($dias))
                ->pluck('media_ruta')
                ->filter()
                ->all();

            if (! $direcciones) return collect();

            // "https://sitio/storage/paquetes/abc.jpg" → "paquetes/abc.jpg",
            // que es como la guarda la foto.
            $base  = rtrim(url('/storage'), '/') . '/';
            $rutas = [];

            foreach ($direcciones as $d) {
                if (str_starts_with($d, $base)) $rutas[] = substr($d, strlen($base));
            }

            if (! $rutas) return collect();

            return GuiaFoto::whereIn('ruta', $rutas)
                ->whereNotNull('chat_enviada_at')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** Cuántas fotos se subieron hoy, contando también las resubidas. */
    public static function subidasHoy(): int
    {
        if (! static::disponible()) return 0;

        try {
            return GuiaFoto::whereNotNull('ruta')
                ->where('lote', '>=', static::inicioDeHoy())
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Devolver una foto a la lista de por mandar.
     *
     * Para el caso en que se quitó por error, o para mandarla otra vez a
     * propósito. Es una decisión tuya y explícita: la foto no vuelve sola,
     * porque volver sola es justamente lo que causó los repetidos.
     */
    public static function devolverALista(GuiaFoto $foto): void
    {
        try {
            $foto->forceFill([
                'chat_enviada_at' => null,
                'chat_error'      => null,
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Fotos al chat, devolviendo a la lista: ' . $e->getMessage());
        }
    }

    /** Devuelve a la lista una foto que se había reservado y no salió. */
    private static function soltar(GuiaFoto $foto): void
    {
        try {
            GuiaFoto::where('id', $foto->id)->update(['chat_enviada_at' => null]);
        } catch (\Throwable $e) {
            Log::warning('Fotos al chat, soltando reserva: ' . $e->getMessage());
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

    /**
     * Saca una tanda entera de la lista, sin mandar nada.
     *
     * Hace falta para no equivocarse de tanda. Cuando quedan tres tandas
     * viejas arriba de la de hoy —porque tenían la ventana cerrada y nunca
     * salieron— hay que leer el título de cada una antes de apretar, y ahí es
     * donde uno manda la de anteayer creyendo que es la de hoy.
     *
     * NO borra las fotos. Siguen en el rastreo del cliente y siguen contando
     * para el ranking de productos. Lo único que se dice es "de estas ya me
     * ocupé, no me las muestres más".
     *
     * @return int cuántas se quitaron
     */
    public static function omitirLote(string $lote): int
    {
        $n = 0;

        foreach (static::pendientes(7, 120, $lote) as $fila) {
            static::omitir($fila['foto']);
            $n++;
        }

        return $n;
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

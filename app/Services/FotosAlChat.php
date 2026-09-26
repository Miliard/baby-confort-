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
    public const ENVIADA    = 'enviada';      // ya le salió al cliente

    /** ¿La base ya tiene lo que hace falta? */
    public static function disponible(): bool
    {
        try {
            return Schema::hasTable('guia_fotos')
                && Schema::hasColumn('guia_fotos', 'chat_oculta_at')
                && WaConversacion::hayTabla();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Las fotos que se ven en pantalla, ya emparejadas: mandadas y por mandar.
     *
     * LAS MANDADAS SE QUEDAN. Antes, en cuanto una foto salía, desaparecía de
     * su tanda — igual que una que se quitaba con el tacho, porque era la
     * misma marca. Mirando una tanda no había forma de saber qué ya se fue y
     * qué no, y ese fue el origen de todo el "se me desaparecen".
     *
     * Ahora una foto solo se va de la pantalla cuando se limpia su tanda.
     * Mandada, se queda con su marca de enviada.
     *
     * Solo las de los últimos días: una etiqueta de hace tres semanas no se
     * manda más, y tenerla ahí solo ensucia.
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
                // Lo único que saca una foto de la pantalla es limpiarla.
                ->whereNull('chat_oculta_at')
                // Las fotos se borran solas del disco a los tantos días. Una
                // cuya imagen ya no está no se puede mandar: Meta la va a
                // buscar a nuestro sitio y no la va a encontrar. El registro
                // del pedido se queda; la que se fue es la imagen.
                ->whereNull('foto_borrada_at')
                /*
                 * La ventana de días se cuenta desde que SUBISTE la foto, no
                 * desde que entró la guía.
                 *
                 * Antes era por created_at, que es la fecha de la guía. Una
                 * foto subida hoy a una guía de hace ocho días —porque entró
                 * por un PDF viejo, o porque es un cliente que se atrasó—
                 * quedaba fuera de la pantalla sin ningún aviso. Subías 17 y
                 * veías 12.
                 *
                 * El lote es la hora de la subida y se rehace cada vez que se
                 * sube, así que es el reloj correcto. Las que no tienen lote
                 * caen de vuelta a la fecha de la fila.
                 */
                ->where(function ($q) use ($dias) {
                    $desde = now()->subDays($dias)->utc()->format('Y-m-d H:i:s');

                    $q->where('lote', '>=', $desde)
                      ->orWhere(fn ($q2) => $q2->whereNull('lote')
                          ->where('created_at', '>=', now()->subDays($dias)));
                })
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
                        static::ENVIADA    => 0,
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
    /**
     * ¿Esta foto le llegó al cliente DE VERDAD? Se busca en el chat.
     *
     * Hasta acá "enviada" era una marca que ponía el panel al mandar, y la
     * marca podía mentir: WhatsApp puede aceptar el envío y después fallar, y
     * las fotos que se volvían a subir arrastraban la marca de un envío
     * anterior que nunca había llegado. Decía "enviada" y no se había ido.
     *
     * Ahora la respuesta sale de los mensajes del chat, que es lo mismo que
     * ves vos en la conversación. Se busca de tres maneras, de la más segura
     * a la menos:
     *
     *  1. El mensaje con el que salió, si quedó vinculado a la foto.
     *  2. Una imagen mandada a esa conversación cuyo pie diga el número de
     *     guía. Todas las fotos salen con "Guía: 5869736" en el pie, y ese
     *     número no cambia aunque la foto se vuelva a subir.
     *  3. Una imagen mandada con esa misma dirección de archivo.
     *
     * Devuelve el mensaje si lo encuentra en buen estado, o null si en el chat
     * no hay nada que diga que llegó.
     */
    public static function mensajeQueLlego(GuiaFoto $foto): ?\App\Models\WaMensaje
    {
        $buenos = ['enviado', 'entregado', 'leido'];

        try {
            // 1. El vinculado. "enviando" cuenta: es un envío de hace segundos
            //    que todavía no tuvo respuesta, y reintentarlo sería repetirlo.
            if ($foto->chat_mensaje_id) {
                $m = \App\Models\WaMensaje::find($foto->chat_mensaje_id);

                if ($m && in_array($m->estado, [...$buenos, 'enviando'], true)) return $m;
            }

            $conv = static::conversacionDe((string) GuiaFoto::telefonoCorto($foto->telefono));

            // 2. Por el número de guía en el pie.
            if (filled($foto->guia) && $conv) {
                $m = \App\Models\WaMensaje::where('conversacion_id', $conv->id)
                    ->where('direccion', 'saliente')
                    ->where('tipo', 'image')
                    ->whereIn('estado', $buenos)
                    ->where('texto', 'like', '%' . $foto->guia . '%')
                    ->orderByDesc('id')
                    ->first();

                if ($m) return $m;
            }

            // 3. Por la dirección del archivo.
            if ($foto->url()) {
                return \App\Models\WaMensaje::where('direccion', 'saliente')
                    ->where('tipo', 'image')
                    ->whereIn('estado', $buenos)
                    ->where('media_ruta', url($foto->url()))
                    ->orderByDesc('id')
                    ->first();
            }
        } catch (\Throwable $e) {
            Log::warning('Fotos al chat, buscando en el chat: ' . $e->getMessage());
        }

        return null;
    }

    /** Las conversaciones contra las que se empareja, cargadas una vez. */
    private static ?array $candidatos = null;

    /** Los teléfonos que ya tiene asignados alguna foto a la vista. */
    private static ?array $usados = null;

    /**
     * A quiénes les puede corresponder una foto: los de Preparados, y
     * después los de Pedidos.
     *
     * La foto de una etiqueta es de alguien a quien ya le salió el enlace de
     * rastreo, y por eso está en Preparados. Esa lista es corta —quince,
     * veinte conversaciones— y eso es lo que hace que emparejar funcione:
     * entre veinte números de ocho dígitos, uno que se parece a lo leído en
     * seis o siete dígitos no se confunde con otro.
     *
     * Pedidos va después, por si a ese cliente todavía no se le mandó el
     * enlace. Con menos prioridad: si un número se parece a uno de cada
     * lista, gana el de Preparados.
     */
    private static function candidatos(): array
    {
        if (static::$candidatos !== null) return static::$candidatos;

        $lista = [];

        foreach (['procesada' => 0, 'pedido' => 1] as $rol => $prioridad) {
            try {
                $etq = \App\Models\WaEtiqueta::porRol($rol);
                if (! $etq) continue;

                foreach ($etq->conversaciones()->get() as $c) {
                    $tel = WaConversacion::telefonoCorto($c->telefono);
                    if (strlen($tel) !== 8 || isset($lista[$tel])) continue;

                    // Todos los nombres que se le conocen: el que le pusiste
                    // vos, el del perfil y el de la libreta de clientes.
                    $nombres = array_filter([
                        $c->alias ?? null,
                        $c->nombre ?? null,
                        $c->cliente()['nombre'] ?? null,
                    ]);

                    $lista[$tel] = [
                        'conv'      => $c,
                        'tel'       => $tel,
                        'nombres'   => array_map([static::class, 'aplanar'], $nombres),
                        'prioridad' => $prioridad,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Fotos al chat, leyendo candidatos: ' . $e->getMessage());
            }
        }

        return static::$candidatos = $lista;
    }

    /**
     * Un nombre sin tildes, sin mayúsculas y SIN ESPACIOS.
     *
     * Sin espacios a propósito: el lector de etiquetas parte los nombres por
     * la mitad — "Maria hern á ndez" —, y comparado palabra por palabra eso no
     * se parece a nada. Pegado, "mariahernandez" es igual a "mariahernandez".
     */
    public static function aplanar(?string $t): string
    {
        $t = mb_strtolower(trim((string) $t));
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return preg_replace('/[^a-z]/', '', $t);
    }

    /** Qué tanto se parecen dos nombres aplanados, de 0 a 1. */
    private static function parecido(string $a, string $b): float
    {
        if ($a === '' || $b === '') return 0.0;

        similar_text($a, $b, $pct);

        return $pct / 100;
    }

    /**
     * Empareja una foto con una conversación de Preparados (o de Pedidos).
     *
     * Primero por el TELÉFONO leído, aunque tenga dígitos mal: se busca el
     * candidato con menos dígitos distintos, y se acepta si difiere en dos o
     * menos. El lector confunde un 6 con un 5, un 1 con un 7 — errores de a
     * un dígito. Dos es el margen seguro: con tres ya podría ser otra persona.
     *
     * Si no se leyó ningún número, por el NOMBRE, pero solo si se parece
     * mucho y a uno solo.
     *
     * Si hay empate —dos candidatos igual de parecidos— no se elige ninguno.
     * Adivinar entre dos es mandarle la foto a la persona equivocada la mitad
     * de las veces. Ahí queda el campo para que lo escribas vos.
     *
     * @return array{tel:string, conv:WaConversacion}|null
     */
    public static function emparejar(GuiaFoto $foto): ?array
    {
        $candidatos = static::candidatos();
        if (! $candidatos) return null;

        $leido = preg_replace('/\D/', '', (string) ($foto->tel_leido ?: $foto->telefono));
        if (strlen($leido) > 8) $leido = substr($leido, -8);

        $nombre = static::aplanar($foto->nombre);
        $usados = static::usados();

        $mejor = null;
        $mejorClave = null;
        $empate = false;

        foreach ($candidatos as $tel => $c) {
            // Un número que ya tiene otra foto asignada no se reparte dos
            // veces: si no, dos fotos parecidas terminan en el mismo chat.
            if (isset($usados[$tel]) && $usados[$tel] !== $foto->id) continue;

            $difiere = strlen($leido) >= 6 ? levenshtein($leido, $tel) : 99;

            $nom = 0.0;
            foreach ($c['nombres'] as $n) $nom = max($nom, static::parecido($nombre, $n));

            // Orden de preferencia: menos dígitos distintos, después mejor
            // nombre, después Preparados antes que Pedidos.
            $clave = [$difiere, -$nom, $c['prioridad']];

            if ($mejorClave === null || $clave < $mejorClave) {
                $mejor = $c + ['difiere' => $difiere, 'nom' => $nom];
                $mejorClave = $clave;
                $empate = false;
            } elseif ($clave == $mejorClave) {
                $empate = true;
            }
        }

        if (! $mejor || $empate) return null;

        $porTelefono = $mejor['difiere'] <= 2;
        $porNombre   = $mejor['difiere'] === 99 && $mejor['nom'] >= 0.8;

        return ($porTelefono || $porNombre)
            ? ['tel' => $mejor['tel'], 'conv' => $mejor['conv']]
            : null;
    }

    /** Teléfono → id de la foto que ya lo tiene, entre las que están a la vista. */
    private static function usados(): array
    {
        if (static::$usados !== null) return static::$usados;

        $mapa = [];

        try {
            GuiaFoto::whereNotNull('ruta')
                ->whereNull('chat_oculta_at')
                ->whereNotNull('telefono')
                ->where('lote', '>=', now()->subDays(7)->utc()->format('Y-m-d H:i:s'))
                ->get(['id', 'telefono'])
                ->each(function ($f) use (&$mapa) {
                    $t = (string) GuiaFoto::telefonoCorto($f->telefono);
                    if (strlen($t) === 8 && ! isset($mapa[$t])) $mapa[$t] = $f->id;
                });
        } catch (\Throwable $e) {
        }

        return static::$usados = $mapa;
    }

    public static function revisar(GuiaFoto $foto): array
    {
        if ($foto->chat_enviada_at) {
            $llego = static::mensajeQueLlego($foto);

            if ($llego) {
                return [
                    'foto'   => $foto,
                    'conv'   => static::conversacionDe((string) GuiaFoto::telefonoCorto($foto->telefono)),
                    'estado' => static::ENVIADA,
                    'msj'    => $llego,
                    'porque' => 'Enviada el '
                              . $foto->chat_enviada_at->timezone(config('app.zona_local'))->format('d/m g:i a')
                              . '.',
                ];
            }

            /*
             * Marcada como enviada, pero en el chat no está. NO SALIÓ.
             *
             * Se corrige sola, acá mismo: se le saca la marca y sigue abajo
             * como cualquier foto por mandar. Así queda incluida en el botón
             * de la tanda, sin que tengas que ir a buscarla una por una.
             */
            try {
                $foto->forceFill([
                    'chat_enviada_at' => null,
                    'chat_mensaje_id' => null,
                    'chat_error'      => 'Figuraba como enviada, pero en el chat no aparece. Va de nuevo.',
                ])->save();
            } catch (\Throwable $e) {
                Log::warning('Fotos al chat, corrigiendo marca: ' . $e->getMessage());
            }
        }

        /*
         * EMPAREJAR CON PREPARADOS, antes de pedirte nada.
         *
         * El cliente de esta foto ya recibió su enlace de rastreo, así que su
         * conversación está en Preparados. No hace falta leer el número
         * perfecto: alcanza con encontrar cuál de esos se parece.
         *
         * Se intenta en tres casos:
         *  · no se leyó número, o salió incompleto;
         *  · el número leído no tiene chat — casi seguro un dígito mal;
         *  · el número leído SÍ tiene chat, pero no está en Preparados ni en
         *    Pedidos. Este es el peligroso: el lector cambió un 6 por un 5 y
         *    dio justo con otro cliente real. Si hay uno de Preparados a uno
         *    o dos dígitos, ese es el bueno.
         *
         * Si empareja, el número se corrige y se guarda lo que había leído la
         * etiqueta, para que en pantalla veas las dos cosas y decidas. No se
         * te pide escribir nada.
         */
        $corto = (string) GuiaFoto::telefonoCorto($foto->telefono);
        $conv  = strlen($corto) === 8 ? static::conversacionDe($corto) : null;

        $enLista = strlen($corto) === 8 && isset(static::candidatos()[$corto]);

        // Un número escrito a mano no se toca: lo escribiste vos, y vos sabés
        // más que el emparejador. Se reconoce porque al guardarlo a mano
        // "lo leído" queda igual al número puesto.
        $aMano = $foto->tel_leido
            && preg_replace('/\D/', '', (string) $foto->tel_leido) === preg_replace('/\D/', '', (string) $foto->telefono);

        if (! $enLista && ! $aMano) {
            $par = static::emparejar($foto);

            /*
             * Si el número leído YA tiene chat, cambiarlo solo porque se
             * parece a otro sería peligroso: puede venir del PDF de Sistrack y
             * ser el correcto, de un cliente que simplemente no está
             * etiquetado. Ahí se pide que el NOMBRE también lo confirme — que
             * la etiqueta se parezca al candidato y no al dueño del número
             * leído. Sin nombre que desempate, se respeta el número leído.
             */
            if ($par && $conv) {
                $nombreFoto = static::aplanar($foto->nombre);

                $conCandidato = 0.0;
                foreach ([$par['conv']->alias ?? null, $par['conv']->nombre ?? null] as $n) {
                    $conCandidato = max($conCandidato, static::parecido($nombreFoto, static::aplanar($n)));
                }

                $conLeido = 0.0;
                foreach ([$conv->alias ?? null, $conv->nombre ?? null] as $n) {
                    $conLeido = max($conLeido, static::parecido($nombreFoto, static::aplanar($n)));
                }

                if (! ($conCandidato >= 0.7 && $conLeido < 0.5)) $par = null;
            }

            if ($par && $par['tel'] !== $corto) {
                try {
                    $foto->forceFill([
                        // Lo que leyó la etiqueta se guarda una sola vez, la
                        // primera: si se empareja de nuevo más adelante, no
                        // se pisa con un número que ya era corregido.
                        'tel_leido' => $foto->tel_leido ?: ($foto->telefono ?: '—'),
                        'telefono'  => $par['tel'],
                    ])->save();
                } catch (\Throwable $e) {
                    Log::warning('Fotos al chat, emparejando: ' . $e->getMessage());
                }

                $corto = $par['tel'];
                $conv  = $par['conv'];
            }
        }

        if (strlen($corto) !== 8) {
            return [
                'foto'   => $foto,
                'conv'   => null,
                'estado' => static::SIN_NUMERO,
                'porque' => 'No se leyó el teléfono y no se parece a nadie de Preparados. '
                          . 'Escribilo abajo.',
            ];
        }

        if (! $conv) {
            return [
                'foto'   => $foto,
                'conv'   => null,
                'estado' => static::SIN_CHAT,
                'porque' => 'Ese número no tiene chat, y no se parece a nadie de Preparados. '
                          . 'Revisá el número.',
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
        // Solo la saca de la pantalla. NO la marca como enviada: antes sí lo
        // hacía, y así "la quité" y "la mandé" quedaban iguales en la base.
        try {
            $foto->forceFill(['chat_oculta_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('Fotos al chat, limpiando: ' . $e->getMessage());
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

    /**
     * Volver a mandar una foto que ya salió. A mano y a propósito.
     *
     * Para cuando el chat dice que llegó pero vos sabés que no —el cliente no
     * la ve, la borró, cambió de teléfono—. Nunca pasa solo: solo con el
     * botón de ese renglón.
     *
     * @return string|null null si salió, o el motivo por el que no pudo.
     */
    public static function reenviar(GuiaFoto $foto, ?int $userId = null): ?string
    {
        try {
            $foto->forceFill([
                'chat_enviada_at' => null,
                'chat_mensaje_id' => null,
                'chat_error'      => null,
            ])->save();
        } catch (\Throwable $e) {
            return 'No se pudo preparar el reenvío.';
        }

        $fila = static::revisar($foto->fresh());

        if ($fila['estado'] !== static::LISTA) {
            return $fila['porque'];
        }

        return static::mandarUna($fila['foto'], $fila['conv'], $userId) === true
            ? null
            : (string) ($fila['foto']->fresh()->chat_error ?: 'WhatsApp no la aceptó.');
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

<?php

namespace App\Services\Asistente;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * El banco de ejemplos: cómo contestás vos, sacado de tus chats.
 *
 * Arma pares "la clienta dijo → Wil contestó" de las conversaciones reales
 * (sin teléfonos, nombres ni direcciones) y, en cada turno, busca los más
 * parecidos a lo que acaba de escribir la clienta. La IA los lee como
 * ejemplos de tu manera de vender: qué decís, en qué orden, con qué tono.
 *
 * Los precios de los ejemplos pueden ser viejos: la IA tiene orden de usar
 * SOLO los del catálogo de hoy, y el sistema revisa cada precio antes de
 * mandar el mensaje.
 */
class Ejemplos
{
    public static function hayTabla(): bool
    {
        static $hay = null;
        if ($hay !== null) return $hay;

        try {
            return $hay = Schema::hasTable('asistente_ejemplos');
        } catch (\Throwable $e) {
            return $hay = false;
        }
    }

    public static function cuantos(): int
    {
        if (! static::hayTabla()) return 0;

        try {
            return (int) DB::table('asistente_ejemplos')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Si el banco está vacío o tiene más de una semana, se vuelve a armar
     * (así aprende de los chats nuevos sin que nadie se acuerde). Corre
     * después de contestar, nunca antes.
     */
    public static function alDia(): void
    {
        if (! static::hayTabla()) return;

        try {
            $armado = (int) \Illuminate\Support\Facades\Cache::get('asistente:ejemplos:armado', 0);
            if ($armado > now()->subDays(7)->timestamp && static::cuantos() > 0) return;

            $candado = \Illuminate\Support\Facades\Cache::lock('asistente:ejemplos:armando', 600);
            if (! $candado->get()) return;

            try {
                static::reconstruir();
            } finally {
                $candado->release();
            }
        } catch (\Throwable $e) {
            Log::warning('Ejemplos al día: ' . $e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // Armar el banco
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Vuelve a armar el banco con los chats de los últimos días. Devuelve
     * cuántos ejemplos quedaron.
     */
    public static function reconstruir(?int $dias = null): int
    {
        if (! static::hayTabla()) return 0;

        @set_time_limit(300);

        $dias  = $dias ?? (int) config('asistente.ejemplos.dias', 180);
        $desde = now()->subDays(max(7, $dias));
        $tope  = (int) config('asistente.ejemplos.maximo', 8000);

        $filas = [];
        $vistos = [];

        \App\Models\WaConversacion::query()
            ->where('ultimo_mensaje_at', '>=', $desde)
            ->orderByDesc('ultimo_mensaje_at')
            ->chunk(100, function ($convs) use (&$filas, &$vistos, $desde, $tope) {
                foreach ($convs as $conv) {
                    if (count($filas) >= $tope) return false;

                    $msjs = \App\Models\WaMensaje::where('conversacion_id', $conv->id)
                        ->where('created_at', '>=', $desde)
                        ->orderBy('id')
                        ->get(['direccion', 'tipo', 'texto', 'automatico', 'created_at'])
                        ->map(fn ($m) => [
                            'dir'   => (string) $m->direccion,
                            'auto'  => (bool) $m->automatico,
                            'tipo'  => (string) $m->tipo,
                            'texto' => (string) $m->texto,
                            'fecha' => $m->created_at,
                        ])->all();

                    $tapar = [];
                    foreach ([$conv->nombre, $conv->alias] as $n) {
                        $n = trim((string) $n);
                        if (mb_strlen($n) >= 3 && preg_match('/\p{L}/u', $n)) $tapar[] = $n;
                    }

                    foreach (static::armar($msjs, $tapar) as $e) {
                        $clave = md5(static::palabras($e['cliente']) . '|' . mb_strtolower($e['respuesta']));
                        if (isset($vistos[$clave])) continue;
                        $vistos[$clave] = true;

                        $filas[] = [
                            'conversacion_id' => $conv->id,
                            'antes'           => $e['antes'] ?: null,
                            'cliente'         => $e['cliente'],
                            'respuesta'       => $e['respuesta'],
                            'palabras'        => static::palabras(($e['antes'] ? $e['antes'] . ' ' : '') . $e['cliente'] . ' ' . $e['cliente']),
                            'fecha'           => $e['fecha'],
                        ];
                    }
                }
            });

        try {
            DB::transaction(function () use ($filas) {
                DB::table('asistente_ejemplos')->delete();
                foreach (array_chunk($filas, 300) as $lote) {
                    DB::table('asistente_ejemplos')->insert($lote);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Ejemplos al guardar: ' . $e->getMessage());
            return 0;
        }

        try {
            \Illuminate\Support\Facades\Cache::forever('asistente:ejemplos:armado', now()->timestamp);
        } catch (\Throwable $e) {
        }

        return count($filas);
    }

    /**
     * Los pares de una conversación. $msjs en orden, cada uno con
     * dir (entrante|saliente), auto, tipo, texto y fecha.
     *
     * @return array<int, array{antes: string, cliente: string, respuesta: string, fecha: mixed}>
     */
    public static function armar(array $msjs, array $tapar = []): array
    {
        $ejemplos = [];
        $clientes = [];   // lo que escribió la clienta desde la última respuesta
        $antes    = '';   // lo último que dijo la tienda
        $resp     = [];   // la respuesta que se está juntando
        $fecha    = null;

        $cerrar = function () use (&$ejemplos, &$clientes, &$antes, &$resp, &$fecha) {
            if ($clientes && $resp) {
                $cliente   = mb_substr(implode(' / ', $clientes), 0, 400);
                $respuesta = mb_substr(implode("\n", $resp), 0, 600);

                // Una respuesta que es solo una foto sin texto también enseña
                // (en ese momento mandás la foto), pero tiene que haber algo.
                if (trim($respuesta) !== '' && trim($cliente) !== '') {
                    $ejemplos[] = ['antes' => $antes, 'cliente' => $cliente, 'respuesta' => $respuesta, 'fecha' => $fecha];
                }
            }

            if ($resp) {
                $antes = mb_substr(implode(' / ', $resp), -200);
                $clientes = [];
            }
            $resp = [];
        };

        foreach ($msjs as $m) {
            if ($m['auto']) continue;   // lo automático no es tu manera de contestar

            $t = static::limpiar((string) $m['texto'], $tapar);

            if ($m['dir'] === 'entrante') {
                if ($resp) $cerrar();

                $t = match (true) {
                    in_array($m['tipo'], ['image'], true)            => '[foto]' . ($t !== '' && ! str_starts_with($t, '[') ? ' ' . $t : ''),
                    in_array($m['tipo'], ['audio', 'voice'], true)   => $t !== '' && ! str_starts_with($t, '[') ? $t : '[audio]',
                    in_array($m['tipo'], ['sticker', 'reaction'], true) => '',
                    default => str_starts_with($t, '[') ? '' : $t,
                };

                if ($t !== '') {
                    $clientes[] = $t;
                    $clientes = array_slice($clientes, -3);
                }
                continue;
            }

            // Saliente escrito por vos.
            if ($m['tipo'] === 'image') {
                $pie = trim(strtok($t, "\n") ?: '');
                $t = '[foto' . ($pie !== '' && ! str_starts_with($pie, '[') ? ': ' . mb_substr($pie, 0, 80) : ' de producto') . ']';
            } elseif ($t === '' || str_starts_with($t, '[') || static::esDeLaOrden($t)) {
                continue;
            } elseif (preg_match('/\*precio:\*|m[ií]ralo aqu[ií]|contiene:\*/iu', $t)) {
                // La tarjeta de un producto (con su precio de ese día): se
                // guarda solo que en ese momento le mandaste la tarjeta.
                $nombre = trim(str_replace('*', '', strtok($t, "\n") ?: ''));
                $t = '[tarjeta del producto' . ($nombre !== '' ? ': ' . mb_substr($nombre, 0, 60) : '') . ']';
            }

            if (! $resp) $fecha = $m['fecha'] ?? null;
            $resp[] = $t;
            if (count($resp) > 4) $resp = array_slice($resp, 0, 4);
        }

        $cerrar();

        return $ejemplos;
    }

    /** La orden de envío y su revisión las arma el sistema, no se imitan. */
    private static function esDeLaOrden(string $t): bool
    {
        return (bool) preg_match('/orden de env[ií]o|revisar la orden|total a pagar|nombre completo\s*:|tu pedido:\*|segu[ií] tu paquete|a pagar al recibir/iu', $t);
    }

    /** Sin teléfonos, correos, nombres ni datos de las órdenes. */
    public static function limpiar(string $t, array $tapar = []): string
    {
        $t = trim($t);
        if ($t === '') return '';

        $t = preg_replace('/^(\W*\s*(nombre completo|nombre|direcci[oó]n exacta|direcci[oó]n|tel[eé]fono|celular))\s*:.*$/imu', '$1: [tapado]', $t);
        $t = preg_replace('/\+?\d[\d\s().-]{7,}\d/u', '[teléfono]', $t);
        $t = preg_replace('/[\w.+-]+@[\w-]+\.[\w.]+/u', '[correo]', $t);
        $t = preg_replace('~https?://\S+~u', '[enlace]', $t);
        // "¡Hola Pamela!": el nombre de la clienta en el saludo.
        $t = preg_replace('/\b(hola|gracias|buenos d[ií]as|buenas tardes|buenas noches)\s+[A-ZÁÉÍÓÚÑ][\p{L}]+(\s+[A-ZÁÉÍÓÚÑ][\p{L}]+)?(?=\s*(?:[!,.]|💙|$))/um', '$1 [nombre]', $t);

        foreach ($tapar as $n) {
            $t = str_ireplace($n, '[nombre]', $t);
        }

        return trim($t);
    }

    // ════════════════════════════════════════════════════════════════════════
    // Buscar
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Los ejemplos más parecidos a la consulta (lo que escribió la clienta,
     * más lo último que le dijo la tienda).
     *
     * @return array<int, array{antes: ?string, cliente: string, respuesta: string}>
     */
    public static function parecidos(string $consulta, int $k = 8, ?int $sinConversacion = null): array
    {
        if (! static::hayTabla() || trim($consulta) === '') return [];

        try {
            $q = DB::table('asistente_ejemplos')->select(['id', 'palabras']);
            if ($sinConversacion) $q->where(fn ($w) => $w->whereNull('conversacion_id')->orWhere('conversacion_id', '!=', $sinConversacion));

            $docs = [];
            foreach ($q->cursor() as $r) $docs[(int) $r->id] = (string) $r->palabras;

            $ids = static::mejores($docs, $consulta, $k);
            if (! $ids) return [];

            $filas = DB::table('asistente_ejemplos')->whereIn('id', $ids)->get(['id', 'antes', 'cliente', 'respuesta'])->keyBy('id');

            $out = [];
            foreach ($ids as $id) {
                if ($f = $filas[$id] ?? null) {
                    $out[] = ['antes' => $f->antes, 'cliente' => (string) $f->cliente, 'respuesta' => (string) $f->respuesta];
                }
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('Ejemplos al buscar: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * BM25: los $k documentos que mejor calzan con la consulta.
     *
     * @param array<int, string> $docs id => palabras separadas por espacio
     * @return int[]
     */
    public static function mejores(array $docs, string $consulta, int $k = 8): array
    {
        $q = array_unique(array_filter(explode(' ', static::palabras($consulta))));
        if (! $q || ! $docs) return [];

        $N = count($docs);
        $df = array_fill_keys($q, 0);
        $tf = [];
        $len = [];
        $total = 0;

        foreach ($docs as $id => $txt) {
            $ws = $txt === '' ? [] : explode(' ', $txt);
            $len[$id] = count($ws);
            $total += $len[$id];

            $cuenta = array_count_values($ws);
            foreach ($q as $w) {
                if (isset($cuenta[$w])) {
                    $df[$w]++;
                    $tf[$id][$w] = $cuenta[$w];
                }
            }
        }

        if (! $tf) return [];

        $prom = max(1, $total / $N);
        $k1 = 1.2;
        $b = 0.75;

        $puntos = [];
        foreach ($tf as $id => $ws) {
            $s = 0.0;
            foreach ($ws as $w => $f) {
                $idf = log(1 + ($N - $df[$w] + 0.5) / ($df[$w] + 0.5));
                $s += $idf * ($f * ($k1 + 1)) / ($f + $k1 * (1 - $b + $b * $len[$id] / $prom));
            }
            $puntos[$id] = $s;
        }

        arsort($puntos);

        return array_slice(array_keys($puntos), 0, $k);
    }

    /** Las palabras que importan, normalizadas: sin tildes, sin relleno, con sinónimos. */
    public static function palabras(string $t): string
    {
        $t = Entender::normalizar($t);
        $t = preg_replace('/(\d+)\s*(libras|libra|lbs|lb)\b/u', '$1 libras', $t);
        $t = preg_replace('/(\d+)\s*(kilos|kilo|kgs|kg|k)\b/u', '$1 kilos', $t);

        static $relleno = null;
        $relleno ??= array_flip(explode(' ',
            'a al algo algun alguna ante con de del el en es esa ese eso esta este esto la las le les lo los me mi mis muy ni no o para pero por que se si sin su sus te tu un una uno unos unas y ya yo usted ustedes ud nos hay ser son era fue the ok va voy bien pues entonces asi tambien solo hola buenas buenos dias tardes noches gracias favor porfa'
        ));

        static $sinonimos = [
            'pamper' => 'panal', 'pampers' => 'panal', 'panales' => 'panal', 'panalitos' => 'panal', 'panpers' => 'panal', 'pamperes' => 'panal', 'pamers' => 'panal',
            'calzoncitos' => 'calzoncito', 'calzon' => 'calzoncito', 'pants' => 'calzoncito', 'pant' => 'calzoncito', 'calsoncito' => 'calzoncito',
            'cintas' => 'cinta', 'broche' => 'cinta', 'pegar' => 'cinta',
            'cuanto' => 'precio', 'cuesta' => 'precio', 'vale' => 'precio', 'precios' => 'precio', 'cuestan' => 'precio', 'valen' => 'precio', 'sale' => 'precio',
            'envios' => 'envio', 'enviar' => 'envio', 'envian' => 'envio', 'mandan' => 'envio', 'entrega' => 'envio', 'entregan' => 'envio', 'domicilio' => 'envio',
            'paquetes' => 'paquete', 'paquetito' => 'paquete', 'bolsa' => 'paquete', 'bolsas' => 'paquete',
            'tallas' => 'talla', 'g' => 'l', 'xg' => 'xl', 'xxg' => 'xxl', 'xxxg' => 'xxxl', 'p' => 's',
            'pesa' => 'peso', 'pesas' => 'peso', 'kilos' => 'kilos', 'libras' => 'libras',
            'nina' => 'bebe', 'nino' => 'bebe', 'bebes' => 'bebe', 'beba' => 'bebe', 'hijo' => 'bebe', 'hija' => 'bebe',
            'quiero' => 'pedir', 'quisiera' => 'pedir', 'ocupo' => 'pedir', 'necesito' => 'pedir', 'deme' => 'pedir', 'regala' => 'pedir', 'pedido' => 'pedir', 'encargar' => 'pedir',
        ];

        $out = [];
        foreach (preg_split('/[^a-z0-9]+/', $t, -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (isset($relleno[$w])) continue;
            $w = $sinonimos[$w] ?? $w;
            if (mb_strlen($w) === 1 && ! in_array($w, ['s', 'm', 'l'], true) && ! preg_match('/^\d$/', $w)) continue;
            $out[] = $w;
        }

        return implode(' ', $out);
    }
}

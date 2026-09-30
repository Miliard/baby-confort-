<?php

namespace App\Services\Asistente;

use App\Models\AsistenteFicha;
use App\Models\ProductSize;
use App\Models\WaConversacion;
use App\Models\WaMensaje;
use App\Services\Etiquetado;
use App\Services\Municipios;
use App\Services\WhatsappApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * El asistente de ventas por WhatsApp.
 *
 * Sigue el flujograma: talla o peso → tipo → opciones con precio → carrito →
 * municipio y envío → total → datos → confirmación. Con cada mensaje llena la
 * ficha con todo lo que entienda y pregunta solo lo que falta.
 *
 * Reglas que no se rompen:
 *  · Precios, envío, unidades y existencias salen del catálogo y de las
 *    tablas. Nunca de la IA (acá no hay IA).
 *  · La misma pregunta, como mucho dos veces. A la segunda que no entiende,
 *    le pasa el chat a Wil.
 *  · Si Wil escribe en el chat, se apaga ahí al instante.
 *  · Lo que no es de su trabajo (quejas, salud, rebajas…) va directo a Wil.
 */
class Asistente
{
    /**
     * Mientras el asistente manda mensajes. El etiquetado automático lo mira
     * para no reaccionar a lo que dice el asistente como si lo hubiera dicho
     * Wil (un "Total a pagar" a mitad del pedido lo mandaría a Pedidos).
     */
    public static bool $hablando = false;

    private WaConversacion $conv;
    private AsistenteFicha $f;

    /** Si durante la lectura aparece un motivo para pasárselo a Wil. */
    private ?string $paraWil = null;

    /** El id del botón que tocó, por mensaje. */
    private array $botones = [];

    /** Avisos que van antes de la pregunta del paso ("Disculpe, no le entendí"). */
    private array $avisos = [];

    /** Si hubo algo que contestar (una reacción o un "gracias" no cuentan). */
    private bool $leyoAlgo = false;

    private function __construct(WaConversacion $conv, AsistenteFicha $f)
    {
        $this->conv = $conv;
        $this->f = $f;
    }

    // ════════════════════════════════════════════════════════════════════════
    // ¿A quién le contesta?
    // ════════════════════════════════════════════════════════════════════════

    public static function numeroPermitido(WaConversacion $conv): bool
    {
        if (! config('asistente.activo')) return false;

        $lista = (array) config('asistente.solo_numeros', []);

        if ($lista) {
            return in_array(WaConversacion::telefonoCorto((string) $conv->wa_id), $lista, true);
        }

        return (bool) config('asistente.para_todos', false);
    }

    /** ¿Está en modo prueba? (lista de números y todavía no para todos) */
    public static function enPrueba(): bool
    {
        return (bool) config('asistente.solo_numeros') && ! config('asistente.para_todos');
    }

    /**
     * ¿El asistente se hace cargo de este chat? Lo pregunta el etiquetado
     * automático antes de mandar a Pedidos a alguien que dijo "quiero": si lo
     * atiende el asistente, lo manda él al cerrar el pedido.
     */
    public static function loTiene(?WaConversacion $conv): bool
    {
        try {
            if (! $conv || ! static::numeroPermitido($conv) || ! AsistenteFicha::hayTabla()) return false;

            $f = AsistenteFicha::de($conv);
            if ($f && $f->activa()) return true;

            // Sin mirar la ventana: el cliente está escribiendo justo ahora, y
            // la hora de su último mensaje todavía no se actualizó.
            return static::porQueNoEmpieza($conv, $f, false) === null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** null si puede arrancar; si no, por qué no. */
    public static function porQueNoEmpieza(WaConversacion $conv, ?AsistenteFicha $f, bool $mirarVentana = true): ?string
    {
        if ($f && in_array($f->estado, ['wil', 'apagado'], true)) return 'está apagado en este chat';

        if ($f && $f->estado === 'terminado' && $f->updated_at && $f->updated_at->gt(now()->subDay())) {
            return 'acaba de cerrar un pedido';
        }

        try {
            if ($conv->etiquetas()->whereIn('rol', ['pedido', 'procesada', 'entregada', 'sin_cobro'])->exists()) {
                return 'tiene un pedido abierto';
            }
        } catch (\Throwable $e) {
            // Sin etiquetas no hay pedido abierto que cuidar.
        }

        $desde = now()->subHours((int) config('asistente.silencio_si_wil_horas', 12));
        if ($f && $f->desde && $f->desde->gt($desde)) $desde = $f->desde;

        $wilEscribio = WaMensaje::where('conversacion_id', $conv->id)
            ->where('direccion', 'saliente')
            ->where('automatico', false)
            ->where('created_at', '>=', $desde)
            ->exists();

        if ($wilEscribio) return 'Wil está atendiendo';

        if ($mirarVentana && ! $conv->ventanaAbierta()) return 'la ventana de 24 horas está cerrada';

        return null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // Entrada
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Lo llama el webhook apenas guarda el mensaje del cliente. No contesta
     * acá: deja anotado que hay que contestar DESPUÉS de devolverle el 200 a
     * Meta. Devuelve true si el asistente se hace cargo.
     */
    public static function alLlegar(WaConversacion $conv, WaMensaje $m, ?string $idBoton = null): bool
    {
        if (! static::numeroPermitido($conv) || ! AsistenteFicha::hayTabla()) return false;

        $f = AsistenteFicha::de($conv);

        if (! ($f && $f->activa()) && static::porQueNoEmpieza($conv, $f) !== null) return false;

        if ($idBoton) {
            Cache::put('asistente:boton:' . $m->id, $idBoton, now()->addHour());
        }

        $convId = $conv->id;
        $msgId  = $m->id;

        app()->terminating(function () use ($convId, $msgId) {
            static::trabajar($convId, $msgId);
        });

        return true;
    }

    /** Wil escribió en el chat (desde el panel o desde el teléfono): se apaga. */
    public static function wilEscribio(?WaConversacion $conv): void
    {
        if (! $conv || static::$hablando || ! AsistenteFicha::hayTabla()) return;

        try {
            AsistenteFicha::where('conversacion_id', $conv->id)
                ->whereIn('estado', ['activo', 'listo'])
                ->update(['estado' => 'apagado', 'motivo' => 'escribiste en el chat', 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Asistente al escribir Wil: ' . $e->getMessage());
        }
    }

    /** Corre después de responderle a Meta. */
    public static function trabajar(int $convId, int $msgId): void
    {
        @ignore_user_abort(true);
        @set_time_limit(120);

        try {
            // Si el cliente manda tres mensajes seguidos, se contestan juntos.
            $espera = (int) config('asistente.espera_segundos', 3);
            if ($espera > 0) sleep($espera);

            $hayOtro = WaMensaje::where('conversacion_id', $convId)
                ->where('direccion', 'entrante')
                ->where('id', '>', $msgId)
                ->exists();

            if ($hayOtro) return;   // el más nuevo contesta por todos

            $candado = Cache::lock('asistente:conv:' . $convId, 90);
            if (! $candado->get()) return;   // ya hay otro contestando este chat

            try {
                $conv = WaConversacion::find($convId);
                if (! $conv) return;

                static::$hablando = true;
                static::turno($conv);
            } finally {
                static::$hablando = false;
                $candado->release();
            }
        } catch (\Throwable $e) {
            static::$hablando = false;
            Log::error('Asistente: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
        }
    }

    private static function turno(WaConversacion $conv): void
    {
        $f = AsistenteFicha::firstOrNew(['conversacion_id' => $conv->id]);

        if (! $f->exists) {
            $f->estado = 'listo';
            $f->datos = [];
        }

        $arranca = ! $f->activa();

        if ($arranca && static::porQueNoEmpieza($conv, $f) !== null) return;

        // Los mensajes del cliente que todavía no se leyeron.
        $q = WaMensaje::where('conversacion_id', $conv->id)
            ->where('direccion', 'entrante')
            ->orderBy('id');

        if (! $arranca) {
            $q->where('id', '>', (int) $f->ultimo_mensaje_id);
        } else {
            // Al arrancar: solo lo que escribió después de lo último que se le
            // dijo, y de la última media hora. Nunca el historial entero.
            $ultimoNuestro = (int) WaMensaje::where('conversacion_id', $conv->id)
                ->where('direccion', 'saliente')->max('id');

            $q->where('id', '>', max($ultimoNuestro, (int) $f->ultimo_mensaje_id))
              ->where('created_at', '>=', now()->subMinutes(30));
        }

        $nuevos = $q->limit(10)->get();
        if ($nuevos->isEmpty()) return;

        if ($arranca) {
            $f->estado = 'activo';
            $f->paso = null;
            $f->motivo = null;
            $f->datos = [];
        }

        $a = new static($conv, $f);

        foreach ($nuevos as $m) {
            $a->botones[$m->id] = Cache::pull('asistente:boton:' . $m->id);
        }

        $a->correr($nuevos);
    }

    // ════════════════════════════════════════════════════════════════════════
    // Un turno: leer todo lo nuevo y contestar una vez
    // ════════════════════════════════════════════════════════════════════════

    private function correr($mensajes): void
    {
        // Freno de mano: demasiadas respuestas en una hora es señal de que algo
        // se trabó (o de que alguien está jugando con el bot).
        $turnos = array_values(array_filter(
            (array) $this->f->dato('turnos', []),
            fn ($t) => $t >= now()->subHour()->timestamp
        ));

        foreach ($mensajes as $m) {
            $this->leer($m);
            $this->f->ultimo_mensaje_id = $m->id;
            if ($this->paraWil) break;
        }

        if (! $this->paraWil && count($turnos) >= (int) config('asistente.max_turnos_hora', 40)) {
            $this->paraWil = 'demasiados mensajes seguidos';
        }

        if ($this->paraWil) {
            $this->pasarAWil($this->paraWil);
            return;
        }

        // Solo reacciones o un "gracias": se anota que se leyó y nada más.
        if (! $this->leyoAlgo) {
            $this->f->save();
            return;
        }

        $turnos[] = now()->timestamp;
        $this->f->poner('turnos', $turnos);

        $this->responder();

        $this->f->save();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Leer
    // ────────────────────────────────────────────────────────────────────────

    private function leer(WaMensaje $m): void
    {
        $tipo  = (string) $m->tipo;
        $texto = trim((string) $m->texto);

        // Reacciones, stickers y avisos del sistema: no dicen nada.
        if (in_array($tipo, ['reaction', 'sticker', 'unsupported', 'system', 'ephemeral'], true)
            || preg_match('/^\[(reaction|sticker|unsupported|system|ephemeral)\]$/', $texto)) {
            return;
        }

        // Foto, video, documento, ubicación, contacto: lo mira Wil.
        if (in_array($tipo, ['image', 'video', 'document', 'location', 'contacts'], true)
            || preg_match('/^\[(location|contacts|order|image|video|document)\]$/', $texto)) {
            $this->paraWil = 'mandó ' . ([
                'image' => 'una foto', 'video' => 'un video', 'document' => 'un documento',
                'location' => 'una ubicación', 'contacts' => 'un contacto',
            ][$tipo] ?? 'algo que no es texto');
            return;
        }

        // Nota de voz: si se pudo pasar a texto, sigue como texto.
        if (in_array($tipo, ['audio', 'voice'], true) && ($texto === '' || str_starts_with($texto, '['))) {
            $this->paraWil = 'mandó un audio que no se pudo leer';
            return;
        }

        if ($texto === '') return;

        if ($motivo = Entender::motivoParaWil($texto)) {
            $this->paraWil = $motivo;
            return;
        }

        $boton = $this->botones[$m->id] ?? null;

        if ($boton === 'wil') {
            $this->paraWil = 'pidió hablar con un asesor';
            return;
        }

        $paso = $this->siguientePaso();
        $entendio = $boton ? $this->leerBoton($boton) : $this->leerTexto($paso, $texto);

        if ($entendio) {
            $this->leyoAlgo = true;
            $this->f->poner('fallos', []);
            return;
        }

        // "Gracias", "ok", un emoji: no hace falta contestar nada.
        if (Entender::esCortesia($texto)) return;

        $this->leyoAlgo = true;

        // El primer mensaje ("Buenas, quisiera información") o un saludo a
        // mitad de camino no son errores: se le hace la pregunta del paso.
        if (! $this->f->dato('saludado') || Entender::esSaludo($texto)) return;

        $this->fallo($paso);
    }

    private function fallo(string $paso): void
    {
        $fallos = (array) $this->f->dato('fallos', []);
        $fallos[$paso] = ($fallos[$paso] ?? 0) + 1;
        $this->f->poner('fallos', $fallos);

        if ($fallos[$paso] >= 2) {
            $this->paraWil = 'no le entendí en el paso ' . $paso;
        }
    }

    private function fallosDe(string $paso): int
    {
        return (int) ($this->f->dato('fallos', [])[$paso] ?? 0);
    }

    /** Tocó un botón o eligió de una lista. */
    private function leerBoton(string $id): bool
    {
        [$clave, $valor] = array_pad(explode(':', $id, 2), 2, '');

        switch ($clave) {
            case 'talla':
                if ($valor === 'peso') { $this->f->poner('pide_peso', true); return true; }
                if ($valor === 'ambas') { $this->elegirTallas((array) $this->f->dato('sugeridas', [])); return true; }
                $this->elegirTallas([$valor]);
                return true;

            case 'unidad':
                return $this->ponerPeso((float) $this->f->dato('peso_valor'), $valor);

            case 'tipo':
                if ($valor === 'diferencia') { $this->f->poner('explicar', true); return true; }
                $this->ponerTipo($valor);
                return true;

            case 'p':
                return $this->elegirPresentacion($valor, null);

            case 'cant':
                return $this->ponerCantidad((int) $valor);

            case 'carrito':
                return $this->accionCarrito($valor);

            case 'muni':
                if ($valor === 'si') return $this->ponerMunicipio((string) $this->f->dato('muni_propuesto'));
                $this->f->quitar('muni_propuesto');
                $this->avisos[] = 'Entendido. ¿Me lo puede escribir de nuevo? 🙏';
                return true;

            case 'depto':
                return $this->ponerDepartamento($valor);

            case 'total':
                if ($valor === 'si') { $this->f->poner('total_ok', true); return true; }
                $this->f->poner('cambiando', true);
                return true;

            case 'cambiar':
            case 'corr':
                return $this->corregir($valor);

            case 'tel':
                if ($valor === 'este') { $this->f->poner('telefono', $this->conv->telefonoLegible()); return true; }
                $this->f->poner('pide_otro_tel', true);
                return true;

            case 'conf':
                if ($valor === 'si') { $this->f->poner('confirmado', true); return true; }
                $this->f->poner('corrigiendo', true);
                return true;
        }

        return false;
    }

    /** Escribió. Se lee según el paso en que va, más lo que se entienda suelto. */
    private function leerTexto(string $paso, string $texto): bool
    {
        switch ($paso) {
            case 'talla':
            case 'unidad':
            case 'elegir_talla':
                return $this->leerTalla($paso, $texto);

            case 'tipo':
                if ($this->preguntaPorOtras($texto)) return true;
                $t = Entender::tipo($texto);
                if ($t === 'diferencia') { $this->f->poner('explicar', true); return true; }
                if ($t) { $this->ponerTipo($t); $this->municipioDePaso($texto); return true; }
                return $this->cambioDeTalla($texto);

            case 'opciones':
                return $this->leerOpcion($texto);

            case 'cantidad':
                $n = Entender::cantidad($texto);
                if ($n !== null) return $this->ponerCantidad($n);
                // Quizás eligió otra opción en vez de decir cuántos.
                return $this->leerOpcion($texto);

            case 'carrito':
                return $this->leerCarrito($texto);

            case 'municipio':
                return $this->leerMunicipio($texto);

            case 'confirmar_muni':
                $s = Entender::siNo($texto);
                if ($s === true) return $this->ponerMunicipio((string) $this->f->dato('muni_propuesto'));
                if ($s === false) { $this->f->quitar('muni_propuesto'); $this->avisos[] = 'Entendido. ¿Me lo puede escribir de nuevo? 🙏'; return true; }
                // Escribió otro municipio directamente.
                $this->f->quitar('muni_propuesto');
                return $this->leerMunicipio($texto);

            case 'depto':
                foreach ((array) $this->f->dato('deptos', []) as $d) {
                    if (str_contains(Entender::normalizar($texto), Entender::normalizar($d))) return $this->ponerDepartamento($d);
                }
                return false;

            case 'total':
                $s = Entender::siNo($texto);
                if ($s === true) { $this->f->poner('total_ok', true); return true; }
                if ($s === false || preg_match('/\b(cambiar|cambio|otro|otra|quitar|agregar)\b/', Entender::normalizar($texto))) {
                    $this->f->poner('cambiando', true);
                    return true;
                }
                return $this->cambioDeTalla($texto);

            case 'cambiar':
            case 'corregir':
                return $this->corregir($this->queCorregir($texto) ?? '');

            case 'nombre':
                return $this->leerDatos($texto, 'nombre');

            case 'direccion':
                return $this->leerDatos($texto, 'direccion');

            case 'telefono':
                $tel = Entender::telefonoSV($texto);
                if ($tel) { $this->f->poner('telefono', $tel); return true; }
                if (! $this->conv->esExtranjero() && Entender::siNo($texto) === true) {
                    $this->f->poner('telefono', $this->conv->telefonoLegible());
                    return true;
                }
                if (Entender::siNo($texto) === false) { $this->f->poner('pide_otro_tel', true); return true; }
                return false;

            case 'confirmar':
                $s = Entender::siNo($texto);
                if ($s === true) { $this->f->poner('confirmado', true); return true; }
                if ($s === false || preg_match('/\b(corregir|cambiar|esta mal|no es)\b/', Entender::normalizar($texto))) {
                    $this->f->poner('corrigiendo', true);
                    return true;
                }
                $que = $this->queCorregir($texto);
                return $que ? $this->corregir($que) : false;
        }

        return false;
    }

    // ── Paso 1: talla o peso ─────────────────────────────────────────────────

    private function leerTalla(string $paso, string $texto): bool
    {
        $disponibles = $this->tallasDisponibles();

        $tallas = array_values(array_intersect(Entender::tallas($texto), $disponibles));

        if ($paso === 'elegir_talla') {
            $sug = (array) $this->f->dato('sugeridas', []);

            if (preg_match('/\b(las dos|ambas|los dos|las 2|de las dos|de ambas)\b/', Entender::normalizar($texto))) {
                $this->elegirTallas($sug);
                return true;
            }

            $en = array_values(array_intersect($tallas, $sug));
            if (count($en) === 1) { $this->elegirTallas($en); return true; }
        }

        // Además puede venir el tipo en el mismo mensaje: "calzoncito xl". Solo
        // si lo nombra: un "no sé" acá es de la talla, no del tipo.
        $tipo = Entender::tipo($texto);
        if (in_array($tipo, ['cinta', 'calzoncito'], true)) $this->ponerTipo($tipo);

        $this->municipioDePaso($texto);

        if (count($tallas) === 1) {
            $this->elegirTallas($tallas);
            return true;
        }

        if (count($tallas) >= 2) {
            $this->f->poner('sugeridas', array_slice($tallas, 0, 2));
            $this->f->quitar('peso_dicho');
            return true;
        }

        // Una talla que no tenemos.
        $pedidas = Entender::tallas($texto);
        if ($pedidas) {
            $this->avisos[] = 'En este momento no tenemos talla ' . implode(' ni ', $pedidas) . ' 😔';
            $this->marcarAgotado();
            return true;
        }

        if ($paso === 'unidad') {
            $u = Entender::unidad($texto);
            if ($u) return $this->ponerPeso((float) $this->f->dato('peso_valor'), $u);
        }

        $peso = Entender::peso($texto);

        if ($peso) {
            if ($peso['kg'] === null) {
                $this->f->poner('peso_valor', $peso['valor']);
                return true;
            }

            return $this->ponerPeso($peso['kg'], 'kg', $peso['dicho'] ?? null);
        }

        if (Entender::diceEdad($texto)) {
            $this->f->poner('pide_peso', true);
            $this->f->poner('dijo_edad', true);
            return true;
        }

        // "Quiero calzoncito" sin talla todavía: se entendió algo.
        return in_array($tipo, ['cinta', 'calzoncito'], true) || (bool) $this->f->dato('municipio');
    }

    /**
     * "Quiero calzoncito XL para San Miguel": el municipio se anota de una,
     * aunque todavía no sea su turno. Solo si todavía no hay uno: después, el
     * municipio se cambia a propósito, no de pasada.
     */
    private function municipioDePaso(string $texto): void
    {
        if ($this->f->dato('municipio')) return;

        $m = Municipios::buscarEn($texto);
        if ($m) $this->ponerMunicipio($m);
    }

    private function ponerPeso(float $valor, string $unidad, ?string $dicho = null): bool
    {
        if ($valor <= 0) return false;

        $kg = $unidad === 'lb' ? round($valor * 0.4536, 2) : $valor;
        $dicho = $dicho ?: (rtrim(rtrim(number_format($valor, 1), '0'), '.') . ($unidad === 'lb' ? ' libras' : ' kilos'));

        $this->f->quitar('peso_valor', 'pide_peso', 'dijo_edad');
        $this->f->poner('peso_kg', $kg);
        $this->f->poner('peso_dicho', $dicho);

        $r = Tallas::porPeso(
            $kg,
            Tallas::rangos((array) config('tallas_peso', [])),
            $this->tallasDisponibles(),
            (array) config('asistente.tallas_nino', [])
        );

        if ($r['caso'] === 'fuera') {
            $this->paraWil = "el peso ({$dicho}) no está en la tabla de tallas";
            return true;
        }

        if ($r['caso'] === 'una') {
            $this->avisos[] = "Con {$dicho} le queda la talla *{$r['tallas'][0]}* 👍";
            $this->elegirTallas($r['tallas']);
            return true;
        }

        $this->f->poner('sugeridas', $r['tallas']);
        return true;
    }

    private function elegirTallas(array $tallas): void
    {
        $tallas = array_values(array_filter($tallas));
        if (! $tallas) return;

        $this->f->poner('tallas', $tallas);
        $this->f->quitar('sugeridas', 'peso_valor', 'pide_peso', 'mostradas', 'mostrado_clave', 'elegido');

        // Un tipo que se puso solo (porque la talla anterior tenía uno nada
        // más) no se arrastra: el cliente nunca lo eligió. Acá estaba el error
        // de "y en XL" mostrando solo un calzoncito: venía de la L.
        if ($this->f->dato('tipo_auto')) {
            $this->f->quitar('tipo', 'tipo_auto');
        }

        // Si en esas tallas hay un solo tipo, no se pregunta.
        $tipos = $this->tiposEn($tallas);
        $tipo  = $this->f->dato('tipo');

        if (count($tipos) === 1 && $tipo !== $tipos[0]) {
            if ($tipo && $tipo !== 'ambos') {
                $this->avisos[] = 'En talla ' . implode(' y ', $tallas) . ' por ahora solo tenemos ' . $this->nombreTipo($tipos[0]) . ':';
            }
            $this->f->poner('tipo', $tipos[0]);
            $this->f->poner('tipo_auto', true);
        }
    }

    private function nombreTipo(string $tipo): string
    {
        return $tipo === 'cinta' ? 'de cinta' : 'calzoncito';
    }

    /**
     * "¿Solo en calzoncito tienen?", "¿tiene de cinta?", "¿qué otras hay?".
     *
     * Son preguntas sobre lo que hay, no una elección. Se contestan con el
     * catálogo: si hay del otro tipo se muestra todo; si no, se dice que no.
     */
    private function preguntaPorOtras(string $texto): bool
    {
        $n = Entender::normalizar($texto);
        $tallas = (array) $this->f->dato('tallas', []);
        if (! $tallas) return false;

        $enTalla = 'talla ' . implode(' y ', $tallas);
        $tipos   = $this->tiposEn($tallas);
        $actual  = (string) $this->f->dato('tipo');
        $t       = Entender::tipo($texto);

        $pregunta = (bool) preg_match('/\b(solo|solamente|unicamente|nada mas|tiene|tienen|tienes|hay|habra|manejan|otr[oa]s?|mas opciones|mas modelos|que mas|no hay)\b/', $n);

        // "¿Solo en calzoncito tienen?": pregunta si hay del otro, no está
        // eligiendo. Si hay de los dos, se le muestran todas.
        $solo = (bool) preg_match('/\b(solo|solamente|unicamente|nada mas)\b/', $n);

        if (in_array($t, ['cinta', 'calzoncito'], true) && $solo && in_array($t, $tipos, true) && count($tipos) > 1) {
            $otro = $t === 'cinta' ? 'calzoncito' : 'cinta';
            $this->avisos[] = "No, también tenemos " . $this->nombreTipo($otro) . " en {$enTalla}. Aquí están todas las opciones:";
            $this->ponerTipo('ambos');
            return true;
        }

        if (in_array($t, ['cinta', 'calzoncito'], true)) {
            if (! in_array($t, $tipos, true)) {
                $this->avisos[] = "En {$enTalla} por ahora no tenemos " . $this->nombreTipo($t) . ' 😔'
                    . ($tipos ? ' Tenemos ' . $this->nombreTipo($tipos[0]) . ':' : '');
                if ($actual !== ($tipos[0] ?? $actual)) $this->ponerTipo($tipos[0]);
                return true;
            }

            if ($t !== $actual) {
                $this->ponerTipo($t);
                $this->f->quitar('tipo_auto');
                return true;
            }

            if (! $pregunta) return false;

            // Preguntó por el mismo que está viendo: "¿solo en calzoncito tienen?"
            if (count($tipos) > 1) {
                $otro = $t === 'cinta' ? 'calzoncito' : 'cinta';
                $this->avisos[] = "También tenemos " . $this->nombreTipo($otro) . " en {$enTalla}. Aquí están todas las opciones:";
                $this->ponerTipo('ambos');
                $this->f->quitar('tipo_auto');
            } else {
                $this->avisos[] = "Sí, en {$enTalla} por ahora solo tenemos " . $this->nombreTipo($t) . ' 🙏';
            }
            return true;
        }

        if ($pregunta && preg_match('/\b(otr[oa]s?|mas opciones|mas modelos|que mas|no hay mas|solo esa|solo ese|solo eso|nada mas esa|nada mas ese)\b/', $n)) {
            if ($actual !== 'ambos' && count($tipos) > 1) {
                $otro = $actual === 'cinta' ? 'calzoncito' : 'cinta';
                $this->avisos[] = "También tenemos " . $this->nombreTipo($otro) . " en {$enTalla}. Aquí están todas las opciones:";
                $this->ponerTipo('ambos');
                $this->f->quitar('tipo_auto');
            } else {
                $this->avisos[] = "Esas son todas las opciones que tenemos en {$enTalla} por ahora 🙏";
            }
            return true;
        }

        return false;
    }

    /** A mitad del camino dijo otra talla con todas las letras: "mejor XXL". */
    private function cambioDeTalla(string $texto): bool
    {
        $t = array_values(array_intersect(Entender::tallas($texto, true), $this->tallasDisponibles()));
        if (! $t) return false;

        $this->elegirTallas(array_slice($t, 0, 2));
        $this->f->quitar('carrito_ok', 'total_ok', 'confirmado');
        $this->f->poner('agregando', true);
        return true;
    }

    // ── Paso 2: tipo ─────────────────────────────────────────────────────────

    private function ponerTipo(string $tipo): void
    {
        if (! in_array($tipo, ['cinta', 'calzoncito', 'ambos'], true)) return;
        if ($this->f->dato('tipo') === $tipo) { $this->f->quitar('tipo_auto'); return; }

        $this->f->poner('tipo', $tipo);
        $this->f->quitar('tipo_auto');
        $this->f->quitar('explicar', 'mostradas', 'mostrado_clave', 'elegido');
    }

    // ── Paso 3: opciones ─────────────────────────────────────────────────────

    private function leerOpcion(string $texto): bool
    {
        $mostradas = (array) $this->f->dato('mostradas', []);

        $id = Entender::opcion($texto, $mostradas);

        if ($id !== null) {
            return $this->elegirPresentacion($id, Entender::cantidad($texto, true));
        }

        if ($this->cambioDeTalla($texto)) return true;

        if ($this->preguntaPorOtras($texto)) return true;

        $t = Entender::tipo($texto);
        if ($t === 'ambos' && $t !== $this->f->dato('tipo')) {
            $this->ponerTipo($t);
            return true;
        }

        if ($t === 'diferencia') { $this->f->poner('explicar', true); return true; }

        if (Entender::siNo($texto) === false
            || preg_match('/\b(ninguna|ninguno|no me (gusta|gustan|convence|convencen|sirve|sirven))\b/', Entender::normalizar($texto))) {
            $this->paraWil = 'no le convenció ninguna opción';
            return true;
        }

        $antes = $this->f->dato('municipio');
        $this->municipioDePaso($texto);

        return ! $antes && (bool) $this->f->dato('municipio');
    }

    private function elegirPresentacion(string $id, ?int $cantidad): bool
    {
        $ids = array_map(fn ($o) => (string) $o['id'], (array) $this->f->dato('mostradas', []));
        if (! in_array((string) $id, $ids, true)) return false;

        $this->f->poner('elegido', (string) $id);

        if ($cantidad !== null) return $this->ponerCantidad($cantidad);

        return true;
    }

    private function ponerCantidad(int $n): bool
    {
        $id = $this->f->dato('elegido');
        if (! $id || $n <= 0) return false;

        if ($n > (int) config('asistente.max_paquetes', 10)) {
            $this->paraWil = "quiere {$n} paquetes (posible mayoreo)";
            return true;
        }

        $carrito = (array) $this->f->dato('carrito', []);
        $puesto = false;

        foreach ($carrito as &$c) {
            if ((string) $c['id'] === (string) $id) { $c['cant'] = $n; $puesto = true; }
        }
        unset($c);

        if (! $puesto) $carrito[] = ['id' => (string) $id, 'cant' => $n];

        $this->f->poner('carrito', $carrito);
        $this->f->quitar('elegido', 'carrito_ok', 'total_ok', 'confirmado', 'agregando');
        return true;
    }

    // ── Paso 4: carrito ──────────────────────────────────────────────────────

    private function leerCarrito(string $texto): bool
    {
        $n = Entender::normalizar($texto);

        if (Entender::siNo($texto) === true || preg_match('/\b(continuar|seguir|nada mas|solo eso|eso es todo|asi esta bien|ya)\b/', $n)) {
            return $this->accionCarrito('seguir');
        }

        if ($this->cambioDeTalla($texto)) return true;

        // "¿Tiene de cinta?" con el carrito armado: quiere ver más, sin perder
        // lo que ya eligió.
        if (in_array(Entender::tipo($texto), ['cinta', 'calzoncito', 'ambos'], true) || preg_match('/\b(otras opciones|que mas hay|mas opciones)\b/', $n)) {
            $this->f->quitar('carrito_ok', 'mostrado_clave');
            $this->f->poner('agregando', true);
            if (! $this->preguntaPorOtras($texto)) $this->ponerTipo((string) Entender::tipo($texto));
            return true;
        }

        if (preg_match('/\b(agregar|otro|otra|tambien|ademas|mas)\b/', $n)) return $this->accionCarrito('otro');
        if (preg_match('/\b(cambiar|quitar|borrar|mejor no|otro producto)\b/', $n)) return $this->accionCarrito('cambiar');

        return false;
    }

    private function accionCarrito(string $que): bool
    {
        if ($que === 'seguir') {
            $this->f->poner('carrito_ok', true);
            return true;
        }

        if ($que === 'otro') {
            // Vuelve a empezar la elección, sin tocar lo que ya lleva.
            $this->f->quitar('tallas', 'tipo', 'sugeridas', 'mostradas', 'mostrado_clave', 'elegido', 'carrito_ok', 'peso_kg', 'peso_dicho');
            $this->f->poner('agregando', true);
            return true;
        }

        if ($que === 'cambiar') {
            $this->f->quitar('carrito', 'carrito_ok', 'elegido', 'mostrado_clave', 'total_ok', 'confirmado');
            return true;
        }

        return false;
    }

    // ── Paso 5: municipio ────────────────────────────────────────────────────

    private function leerMunicipio(string $texto): bool
    {
        $m = Municipios::buscarEn($texto);
        if ($m) return $this->ponerMunicipio($m);

        // Mal escrito: el más parecido, si se parece de verdad.
        $n = Entender::normalizar($texto);
        $n = preg_replace('/^(soy de|somos de|es para|para|en|de|vivo en|desde)\s+/', '', $n);

        if ($n !== '' && count(explode(' ', $n)) <= 4) {
            $mejor = null; $dist = 99;

            foreach (Municipios::todos() as $mun) {
                $d = levenshtein($n, Municipios::normalizar($mun));
                if ($d < $dist) { $dist = $d; $mejor = $mun; }
            }

            $tope = mb_strlen($n) >= 8 ? 2 : 1;

            if ($mejor && $dist <= $tope) {
                $this->f->poner('muni_propuesto', $mejor);
                return true;
            }
        }

        // Solo el departamento: se le pide el municipio.
        foreach (Municipios::departamentos() as $d) {
            if (preg_match('/\b' . preg_quote(Municipios::normalizar($d), '/') . '\b/', Municipios::normalizar($texto))) {
                $this->avisos[] = "¿De qué municipio de {$d}? 🙏";
                return true;
            }
        }

        return false;
    }

    private function ponerMunicipio(string $municipio): bool
    {
        $nombre = Municipios::nombreBueno($municipio) ?: $municipio;
        if (! Municipios::existe($nombre)) return false;

        $deps = array_values(array_unique(Municipios::departamentosDe($nombre)));

        $this->f->poner('municipio', $nombre);
        $this->f->quitar('muni_propuesto', 'total_ok', 'confirmado');

        if (count($deps) === 1) {
            $this->f->poner('departamento', $deps[0]);
            $this->f->quitar('deptos');
        } else {
            $this->f->quitar('departamento');
            $this->f->poner('deptos', array_slice($deps, 0, 3));
        }

        return true;
    }

    private function ponerDepartamento(string $d): bool
    {
        if (! in_array($d, (array) $this->f->dato('deptos', []), true)) return false;

        $this->f->poner('departamento', $d);
        $this->f->quitar('deptos');
        return true;
    }

    /** El envío a ese municipio, o null si no se puede cotizar solo. */
    private function envio(float $subtotal): ?float
    {
        $tabla = (array) config('asistente.envio.municipios', []);
        $mun = Municipios::normalizar((string) $this->f->dato('municipio'));

        foreach ($tabla as $nombre => $precio) {
            if (Municipios::normalizar($nombre) === $mun) return (float) $precio;
        }

        if (config('asistente.envio.solo_tabla')) return null;

        return (float) \App\Models\Setting::envioPara($subtotal);
    }

    // ── Paso 6 y 8: cambiar / corregir ───────────────────────────────────────

    private function queCorregir(string $texto): ?string
    {
        $n = Entender::normalizar($texto);

        return match (true) {
            (bool) preg_match('/\b(producto|productos|panal|panales|talla|paquete|paquetes|cantidad|pedido)\b/', $n) => 'productos',
            (bool) preg_match('/\b(municipio|departamento|ciudad|lugar)\b/', $n) => 'municipio',
            (bool) preg_match('/\b(direccion|casa|colonia|calle|referencia)\b/', $n) => 'direccion',
            (bool) preg_match('/\b(nombre|apellido)\b/', $n) => 'nombre',
            (bool) preg_match('/\b(telefono|numero|celular|cel)\b/', $n) => 'telefono',
            (bool) preg_match('/\b(nada|esta bien|todo bien)\b/', $n) => 'nada',
            default => null,
        };
    }

    private function corregir(string $que): bool
    {
        $this->f->quitar('cambiando', 'corrigiendo', 'confirmado');

        switch ($que) {
            case 'productos':
                $this->f->quitar('carrito', 'carrito_ok', 'tallas', 'tipo', 'sugeridas', 'mostradas', 'mostrado_clave', 'elegido', 'total_ok');
                return true;
            case 'municipio':
                $this->f->quitar('municipio', 'departamento', 'deptos', 'total_ok');
                return true;
            case 'nombre':
            case 'direccion':
                $this->f->quitar($que);
                return true;
            case 'telefono':
                $this->f->quitar('telefono');
                $this->f->poner('pide_otro_tel', true);
                return true;
            case 'nada':
                if ($this->f->dato('total_ok')) return true;
                $this->f->poner('total_ok', true);
                return true;
            case 'wil':
                $this->paraWil = 'pidió hablar con un asesor';
                return true;
        }

        return false;
    }

    // ── Paso 7: datos ────────────────────────────────────────────────────────

    private function leerDatos(string $texto, string $paso): bool
    {
        $leido = false;

        // Si mandó todo junto y con etiquetas ("Nombre: … Dirección: …"), se
        // aprovecha todo de una.
        if (substr_count($texto, "\n") >= 1 || preg_match('/\b(nombre|direcci[oó]n|tel[eé]fono)\s*:/iu', $texto)) {
            $p = \App\Services\OrdenWhatsappParser::parsear($texto);

            if (filled($p['nombre'] ?? null) && ! $this->f->dato('nombre')) {
                $nom = Entender::pareceNombre($p['nombre']);
                if ($nom) { $this->f->poner('nombre', $nom); $leido = true; }
            }
            if (filled($p['direccion'] ?? null) && ! $this->f->dato('direccion') && Entender::pareceDireccion($p['direccion'])) {
                $this->f->poner('direccion', mb_substr(trim($p['direccion']), 0, 250));
                $leido = true;
            }
            if (filled($p['telefono'] ?? null) && ! $this->f->dato('telefono')) {
                $tel = Entender::telefonoSV($p['telefono']);
                if ($tel) { $this->f->poner('telefono', $tel); $leido = true; }
            }

            if ($leido) return true;
        }

        $tel = Entender::telefonoSV($texto);

        if ($paso === 'nombre') {
            $sinTel = $tel ? trim(preg_replace('/(?:\+?503[\s-]*)?[267]\d{3}[\s.-]?\d{4}/u', ' ', $texto)) : $texto;
            $nom = Entender::pareceNombre($sinTel);

            if ($nom) {
                $this->f->poner('nombre', $nom);
                if ($tel && ! $this->f->dato('telefono')) $this->f->poner('telefono', $tel);
                return true;
            }

            // Mandó la dirección en vez del nombre: se guarda igual.
            if (Entender::pareceDireccion($texto) && ! $this->f->dato('direccion')) {
                $this->f->poner('direccion', mb_substr(trim($texto), 0, 250));
                $this->avisos[] = 'Anoté la dirección 👍';
                return true;
            }

            return false;
        }

        if ($paso === 'direccion') {
            if (! Entender::pareceDireccion($texto)) return false;

            $this->f->poner('direccion', mb_substr(trim($texto), 0, 250));
            if ($tel && ! $this->f->dato('telefono')) $this->f->poner('telefono', $tel);
            return true;
        }

        return false;
    }

    // ════════════════════════════════════════════════════════════════════════
    // Qué falta
    // ════════════════════════════════════════════════════════════════════════

    private function siguientePaso(): string
    {
        $d = fn ($k, $def = null) => $this->f->dato($k, $def);

        if ($d('cambiando')) return 'cambiar';
        if ($d('corrigiendo')) return 'corregir';

        if (! $d('tallas')) {
            if ($d('peso_valor') !== null) return 'unidad';
            if ($d('sugeridas')) return 'elegir_talla';
            return 'talla';
        }

        if (! $d('tipo')) return 'tipo';

        if (! $d('carrito_ok')) {
            if ($d('elegido')) return 'cantidad';
            if (! $d('carrito') || $d('agregando')) return 'opciones';
            return 'carrito';
        }

        if (! $d('municipio')) return $d('muni_propuesto') ? 'confirmar_muni' : 'municipio';
        if (! $d('departamento')) return 'depto';

        if (! $d('total_ok')) return 'total';

        if (! $d('nombre')) return 'nombre';
        if (! $d('direccion')) return 'direccion';
        if (! $d('telefono')) return 'telefono';

        if (! $d('confirmado')) return 'confirmar';

        return 'fin';
    }

    // ════════════════════════════════════════════════════════════════════════
    // Contestar
    // ════════════════════════════════════════════════════════════════════════

    private function responder(): void
    {
        $paso = $this->siguientePaso();

        // Cambió de paso: los fallos del anterior ya no cuentan.
        if ($paso !== $this->f->paso) {
            $this->f->poner('fallos', []);
        }
        $this->f->paso = $paso;

        $saludo = $this->f->dato('saludado') ? '' : trim((string) config('asistente.textos.saludo')) . "\n\n";
        $this->f->poner('saludado', true);

        $antes = $saludo . ($this->avisos ? implode("\n", array_unique($this->avisos)) . "\n\n" : '');
        $disculpa = $this->fallosDe($paso) > 0 ? "Disculpe, no le entendí 🙏\n" : '';

        switch ($paso) {
            case 'talla':          $this->pedirTalla($antes . $disculpa); break;
            case 'unidad':         $this->pedirUnidad($antes . $disculpa); break;
            case 'elegir_talla':   $this->pedirEntreDos($antes . $disculpa); break;
            case 'tipo':           $this->pedirTipo($antes . $disculpa); break;
            case 'opciones':       $this->mostrarOpciones($antes, $disculpa); break;
            case 'cantidad':       $this->pedirCantidad($antes . $disculpa); break;
            case 'carrito':        $this->mostrarCarrito($antes . $disculpa); break;
            case 'municipio':      $this->pedirMunicipio($antes . $disculpa); break;
            case 'confirmar_muni': $this->confirmarMunicipio($antes . $disculpa); break;
            case 'depto':          $this->pedirDepartamento($antes . $disculpa); break;
            case 'total':          $this->mostrarTotal($antes . $disculpa); break;
            case 'cambiar':        $this->preguntarQueCambiar($antes . $disculpa); break;
            case 'nombre':         $this->texto($antes . $disculpa . "¡Perfecto! Para enviarlo necesito unos datos 📝\n\n¿A nombre de quién va el paquete? (nombre y apellido)"); break;
            case 'direccion':      $this->texto($antes . $disculpa . '¿Cuál es la dirección exacta? Colonia, calle o pasaje, número de casa y un punto de referencia 🏠'); break;
            case 'telefono':       $this->pedirTelefono($antes . $disculpa); break;
            case 'confirmar':      $this->mostrarOrden($antes . $disculpa); break;
            case 'corregir':       $this->preguntarQueCorregir($antes . $disculpa); break;
            case 'fin':            $this->terminar(); break;
        }
    }

    private function pedirTalla(string $antes): void
    {
        if ($this->f->dato('pide_peso')) {
            $pre = $this->f->dato('dijo_edad') ? 'Para recomendarle bien la talla, ' : '';
            $this->texto($antes . $pre . '¿Cuánto pesa su bebé? Por ejemplo: *22 libras* o *10 kilos* ⚖️');
            return;
        }

        $pesos = (array) config('tallas_peso', []);
        $filas = [];

        foreach ($this->tallasDisponibles() as $t) {
            $filas[] = ['id' => 'talla:' . $t, 'titulo' => 'Talla ' . $t, 'detalle' => (string) ($pesos[$t] ?? '')];
        }

        $filas = array_slice($filas, 0, 9);
        $filas[] = ['id' => 'talla:peso', 'titulo' => 'No sé, le digo el peso', 'detalle' => 'Le recomendamos la talla'];

        $this->lista(
            $antes . '¿Qué talla usa su bebé? Si no está segura, dígame cuánto pesa y le recomiendo la talla 😊',
            'Ver tallas',
            $filas
        );
    }

    private function pedirUnidad(string $antes): void
    {
        $v = rtrim(rtrim(number_format((float) $this->f->dato('peso_valor'), 1), '0'), '.');

        $this->botones($antes . "¿Son {$v} libras o {$v} kilos?", [
            'unidad:lb' => 'Libras',
            'unidad:kg' => 'Kilos',
        ]);
    }

    private function pedirEntreDos(string $antes): void
    {
        [$a, $b] = array_pad((array) $this->f->dato('sugeridas', []), 2, null);
        if (! $b) { $this->pedirTalla($antes); return; }

        $dicho = $this->f->dato('peso_dicho');

        $cuerpo = $dicho
            ? "Con {$dicho} le quedan bien la talla *{$a}* y la *{$b}*. Si ya está por pasar de talla, conviene la *{$b}* porque le va a durar más 😉\n\n¿Cuál prefiere?"
            : "¿Cuál prefiere, la talla *{$a}* o la *{$b}*?";

        $this->botones($antes . $cuerpo, [
            'talla:' . $a => 'Talla ' . $a,
            'talla:' . $b => 'Talla ' . $b,
            'talla:ambas' => 'Las dos',
        ]);
    }

    private function pedirTipo(string $antes): void
    {
        if ($this->f->dato('explicar')) {
            $antes .= trim((string) config('asistente.textos.diferencia')) . "\n\n";
            $this->f->quitar('explicar');
        }

        $this->botones($antes . '¿Lo prefiere de cinta o calzoncito?', [
            'tipo:cinta'      => 'Cinta',
            'tipo:calzoncito' => 'Calzoncito',
            'tipo:diferencia' => '¿Diferencia?',
        ]);
    }

    private function mostrarOpciones(string $antes, string $disculpa): void
    {
        if ($this->f->dato('explicar')) {
            $antes .= trim((string) config('asistente.textos.diferencia')) . "\n\n";
            $this->f->quitar('explicar');
        }

        $tallas = (array) $this->f->dato('tallas', []);
        $tipo   = (string) $this->f->dato('tipo');
        $clave  = $this->claveMostrado();

        // Ya se le mostraron estas mismas: no se repiten las tarjetas, solo la
        // lista para elegir.
        if ($this->f->dato('mostrado_clave') === $clave && $this->f->dato('mostradas')) {
            $this->listaDeOpciones($antes . $disculpa . 'Toque *Ver opciones* y elija la que le guste 🙏', (array) $this->f->dato('mostradas'));
            return;
        }

        $filas = $this->presentaciones($tallas, $tipo);

        // No hay de ese tipo pero sí del otro: se muestra lo que hay.
        if ($filas->isEmpty() && $tipo !== 'ambos') {
            $otras = $this->presentaciones($tallas, 'ambos');
            if ($otras->isNotEmpty()) {
                $antes .= 'En talla ' . implode(' y ', $tallas) . ' por ahora solo tenemos ' . ($tipo === 'cinta' ? 'calzoncito' : 'de cinta') . ":\n\n";
                $this->f->poner('tipo', 'ambos');
                $filas = $otras;
                $clave = $this->claveMostrado();
            }
        }

        if ($filas->isEmpty()) {
            $this->texto($antes . 'En este momento no tenemos disponible talla ' . implode(' ni ', $tallas) . ' 😔');
            $this->marcarAgotado();
            $this->pasarAWil('no hay existencias en talla ' . implode(' y ', $tallas), false);
            return;
        }

        $filas = $filas->take(9)->values();

        if (trim($antes) !== '') $this->texto(trim($antes));

        $mostradas = [];
        foreach ($filas as $i => $s) {
            $p = $s->product;
            $mostradas[] = [
                'id'       => (string) $s->id,
                'nombre'   => trim((string) $p->name),
                'talla'    => trim((string) $s->size),
                'unidades' => (int) ($s->unidades ?? 0),
                'precio'   => (float) $s->price,
            ];

            $this->tarjeta($s, $i + 1);
        }

        $this->f->poner('mostradas', $mostradas);
        $this->f->poner('mostrado_clave', $clave);

        $pie = $this->sugerenciaMixta($filas) ?? '';

        // Si está viendo un solo tipo y en esa talla también hay del otro, se
        // le dice: que no se quede creyendo que es lo único.
        $tipoVisto = (string) $this->f->dato('tipo');
        if (in_array($tipoVisto, ['cinta', 'calzoncito'], true)) {
            $otro = $tipoVisto === 'cinta' ? 'calzoncito' : 'cinta';
            if (in_array($otro, $this->tiposEn($tallas), true)) {
                $pie = trim($pie . "\n\nTambién tenemos " . $this->nombreTipo($otro) . ' en talla ' . implode(' y ', $tallas) . ', si quiere se lo muestro.');
            }
        }

        $this->listaDeOpciones(($pie !== '' ? $pie . "\n\n" : '') . '¿Cuál le gustaría? 😊', $mostradas);
    }

    private function listaDeOpciones(string $cuerpo, array $mostradas): void
    {
        $filas = [];

        foreach (array_values($mostradas) as $i => $o) {
            $filas[] = [
                'id'      => 'p:' . $o['id'],
                'titulo'  => ($i + 1) . '. ' . $o['nombre'],
                'detalle' => 'Talla ' . $o['talla']
                    . ($o['unidades'] > 0 ? ' · ' . $o['unidades'] . ' u' : '')
                    . ' · $' . number_format($o['precio'], 2),
            ];
        }

        $filas = array_slice($filas, 0, 9);
        $filas[] = ['id' => 'wil', 'titulo' => 'Hablar con un asesor', 'detalle' => ''];

        $this->lista($cuerpo, 'Ver opciones', $filas);
    }

    /** "Para un mes: 1 de M para terminar y 3 de L para seguir." */
    private function sugerenciaMixta($filas): ?string
    {
        $tallas = (array) $this->f->dato('tallas', []);
        if (count($tallas) !== 2) return null;

        [$chica, $grande] = $tallas;
        $consumo = (array) config('asistente.consumo_diario', []);

        $primera = fn ($t) => $filas->first(fn ($s) => mb_strtoupper(trim((string) $s->size)) === mb_strtoupper($t) && (int) $s->unidades > 0);

        $a = $primera($chica);
        $b = $primera($grande);
        if (! $a || ! $b) return null;

        $nA = Tallas::paquetes($consumo[$chica] ?? null, (int) $a->unidades, 7);
        $nB = Tallas::paquetes($consumo[$grande] ?? null, (int) $b->unidades, 23);
        if (! $nA || ! $nB) return null;

        return "💡 Como está entre dos tallas, para un mes le recomendamos más o menos *{$nA} de {$chica}* para terminar y *{$nB} de {$grande}* para seguir.";
    }

    private function pedirCantidad(string $antes): void
    {
        $o = $this->mostrada((string) $this->f->dato('elegido'));
        $que = $o ? "*{$o['nombre']}* talla {$o['talla']}" : 'ese';

        $this->botones($antes . "¿Cuántos paquetes de {$que} le enviamos? Si son más de 3, escríbame el número 😊", [
            'cant:1' => '1',
            'cant:2' => '2',
            'cant:3' => '3',
        ]);
    }

    private function mostrarCarrito(string $antes): void
    {
        [$lineas, $subtotal] = $this->resumenCarrito();

        $this->botones($antes . "🛒 Le quedaría así:\n" . implode("\n", $lineas) . "\n\n*Subtotal: $" . number_format($subtotal, 2) . '*', [
            'carrito:seguir'  => 'Continuar',
            'carrito:otro'    => 'Agregar otro',
            'carrito:cambiar' => 'Cambiar',
        ]);
    }

    private function pedirMunicipio(string $antes): void
    {
        $txt = $this->fallosDe('municipio') > 0
            ? 'No encontré ese municipio. ¿Me lo escribe como aparece? Por ejemplo: *Soyapango*, *Santa Tecla*, *San Miguel* 🙏'
            : '¿Para qué municipio es el envío? 📍';

        // La disculpa general no hace falta: el texto ya lo dice.
        $antes = str_replace("Disculpe, no le entendí 🙏\n", '', $antes);

        $this->texto($antes . $txt);
    }

    private function confirmarMunicipio(string $antes): void
    {
        $m = (string) $this->f->dato('muni_propuesto');

        $this->botones($antes . "¿Quiso decir *{$m}*?", [
            'muni:si' => 'Sí',
            'muni:no' => 'No',
        ]);
    }

    private function pedirDepartamento(string $antes): void
    {
        $m = (string) $this->f->dato('municipio');
        $b = [];
        foreach ((array) $this->f->dato('deptos', []) as $d) $b['depto:' . $d] = $d;

        $this->botones($antes . "Hay un {$m} en varios departamentos. ¿Cuál es el suyo?", $b);
    }

    private function mostrarTotal(string $antes): void
    {
        [$lineas, $subtotal] = $this->resumenCarrito();
        $envio = $this->envio($subtotal);

        if ($envio === null) {
            $this->pasarAWil('no hay precio de envío para ' . $this->f->dato('municipio'));
            return;
        }

        $this->f->poner('envio', $envio);

        $txt = "🧾 Su pedido:\n" . implode("\n", $lineas)
            . "\n\nEnvío a {$this->f->dato('municipio')}: " . ($envio > 0 ? '$' . number_format($envio, 2) : '*gratis* 🎉')
            . "\n*Total: $" . number_format($subtotal + $envio, 2) . "*"
            . "\n\nSe paga al recibir 💵";

        $this->botones($antes . $txt, [
            'total:si'      => 'Sí, lo quiero',
            'total:cambiar' => 'Cambiar algo',
        ]);
    }

    private function preguntarQueCambiar(string $antes): void
    {
        $this->botones($antes . '¿Qué le gustaría cambiar?', [
            'cambiar:productos' => 'Productos',
            'cambiar:municipio' => 'Municipio',
            'cambiar:wil'       => 'Hablar con asesor',
        ]);
    }

    private function pedirTelefono(string $antes): void
    {
        if ($this->conv->esExtranjero() || $this->f->dato('pide_otro_tel')) {
            $this->texto($antes . '¿A qué número de El Salvador le llamamos cuando llegue el paquete? (8 dígitos) 📞');
            return;
        }

        $this->botones($antes . '¿Le llamamos a este mismo número (' . $this->conv->telefonoLegible() . ') cuando llegue el paquete? 📞', [
            'tel:este' => 'Sí, a este',
            'tel:otro' => 'Otro número',
        ]);
    }

    private function mostrarOrden(string $antes): void
    {
        if (trim($antes) !== '') $this->texto(trim($antes));

        $this->texto($this->textoOrden());

        $this->botones('¿Está todo correcto?', [
            'conf:si'       => 'Confirmar pedido',
            'conf:corregir' => 'Corregir',
        ]);
    }

    private function preguntarQueCorregir(string $antes): void
    {
        $this->lista($antes . '¿Qué dato hay que corregir?', 'Elegir', [
            ['id' => 'corr:productos', 'titulo' => 'Productos',  'detalle' => ''],
            ['id' => 'corr:municipio', 'titulo' => 'Municipio',  'detalle' => (string) $this->f->dato('municipio')],
            ['id' => 'corr:nombre',    'titulo' => 'Nombre',     'detalle' => (string) $this->f->dato('nombre')],
            ['id' => 'corr:direccion', 'titulo' => 'Dirección',  'detalle' => (string) $this->f->dato('direccion')],
            ['id' => 'corr:telefono',  'titulo' => 'Teléfono',   'detalle' => (string) $this->f->dato('telefono')],
            ['id' => 'corr:nada',      'titulo' => 'Nada, está bien', 'detalle' => ''],
            ['id' => 'wil',            'titulo' => 'Hablar con un asesor', 'detalle' => ''],
        ]);
    }

    /** La orden en el mismo formato de siempre: "Procesar orden" la lee igual. */
    private function textoOrden(): string
    {
        [, $subtotal, $renglones] = $this->resumenCarrito();
        $envio = (float) ($this->f->dato('envio') ?? $this->envio($subtotal) ?? 0);

        $mun = (string) $this->f->dato('municipio');
        $dep = (string) $this->f->dato('departamento');
        $lugar = $dep && Municipios::normalizar($dep) !== Municipios::normalizar($mun) ? "{$mun}, {$dep}" : $mun;

        $t = "\u{1F4E6} Orden de Env\u{ED}o: \u{1F69A}\n"
            . "\u{2705} Nombre completo: " . $this->f->dato('nombre') . "\n"
            . "\u{2705} Tel\u{E9}fono: " . $this->f->dato('telefono') . "\n"
            . "\u{2705} Municipio: {$lugar}\n"
            . "\u{2705} Direcci\u{F3}n exacta: " . $this->f->dato('direccion') . "\n"
            . "\u{2705} Producto(s):\n" . implode("\n", $renglones) . "\n"
            . "\u{2705} Costo de env\u{ED}o: $" . number_format($envio, 2) . "\n"
            . "\u{1F4B0} Total a pagar: $" . number_format($subtotal + $envio, 2);

        if (static::enPrueba()) {
            $t .= "\n\n" . config('asistente.textos.prueba');
        }

        return $t;
    }

    private function terminar(): void
    {
        $this->texto(trim((string) config('asistente.textos.gracias')));

        $this->f->estado = 'terminado';
        $this->f->motivo = null;

        static::$hablando = false;
        try {
            Etiquetado::marcarPedido($this->conv);
        } catch (\Throwable $e) {
            Log::warning('Asistente al marcar pedido: ' . $e->getMessage());
        } finally {
            static::$hablando = true;
        }

        $this->avisarAlPanel();
    }

    // ════════════════════════════════════════════════════════════════════════
    // Pasar a Wil
    // ════════════════════════════════════════════════════════════════════════

    private function pasarAWil(string $motivo, bool $avisarAlCliente = true): void
    {
        if ($avisarAlCliente && $this->conv->ventanaAbierta()) {
            $this->texto(trim((string) config('asistente.textos.asesor')));
        }

        $this->f->estado = 'wil';
        $this->f->motivo = mb_substr($motivo, 0, 190);
        $this->f->save();

        // Si ya había elegido algo, va a Pedidos para que no se pierda.
        if ($this->f->dato('carrito') || $this->f->dato('tallas')) {
            static::$hablando = false;
            try {
                Etiquetado::marcarIntencion($this->conv);
            } catch (\Throwable $e) {
                // No importa: el aviso llega igual.
            } finally {
                static::$hablando = true;
            }
        }

        $this->avisarAlPanel();
    }

    /** Que suene y quede como sin leer. */
    private function avisarAlPanel(): void
    {
        try {
            $this->conv->refresh();
            $this->conv->sin_leer = max(1, (int) $this->conv->sin_leer);
            $this->conv->save();
        } catch (\Throwable $e) {
        }

        try {
            \App\Services\WebPush::avisarATodos();
        } catch (\Throwable $e) {
        }
    }

    private function marcarAgotado(): void
    {
        static::$hablando = false;
        try {
            Etiquetado::marcarAgotado($this->conv);
        } catch (\Throwable $e) {
        } finally {
            static::$hablando = true;
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // Catálogo
    // ════════════════════════════════════════════════════════════════════════

    /** Las tallas de la lista que hoy tienen existencia, en su orden. */
    private function tallasDisponibles(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $conStock = $this->consultaBase()->get()
            ->map(fn ($s) => mb_strtoupper(trim((string) $s->size)))
            ->unique()->all();

        return $cache = array_values(array_filter(
            (array) config('asistente.tallas', []),
            fn ($t) => in_array(mb_strtoupper($t), $conStock, true)
        ));
    }

    private function consultaBase()
    {
        $fuera = (array) config('asistente.excluir_categorias', []);

        return ProductSize::with('product')
            ->where('price', '>', 0)
            ->where('quantity', '>', 0)
            ->whereHas('product', function ($q) use ($fuera) {
                $q->where('active', true);
                if ($fuera) {
                    $q->where(fn ($w) => $w->whereNotIn('categoria', $fuera)->orWhereNull('categoria'));
                }
            });
    }

    private function esCalzoncito(string $nombre): bool
    {
        $n = Entender::normalizar($nombre);
        foreach ((array) config('asistente.palabras_calzoncito', []) as $p) {
            if (preg_match('/\b' . preg_quote(Entender::normalizar($p), '/') . 's?\b/', $n)) return true;
        }
        return false;
    }

    /** Los tipos que hay en esas tallas: ['calzoncito'], ['cinta'] o los dos. */
    private function tiposEn(array $tallas): array
    {
        $tipos = [];
        foreach ($this->presentaciones($tallas, 'ambos') as $s) {
            $tipos[$this->esCalzoncito((string) $s->product->name) ? 'calzoncito' : 'cinta'] = true;
        }
        return array_keys($tipos);
    }

    private function presentaciones(array $tallas, string $tipo)
    {
        try {
            $up = array_map(fn ($t) => mb_strtoupper($t), $tallas);

            return $this->consultaBase()->get()
                ->filter(fn ($s) => in_array(mb_strtoupper(trim((string) $s->size)), $up, true))
                ->filter(function ($s) use ($tipo) {
                    if ($tipo === 'ambos' || $tipo === '') return true;
                    $es = $this->esCalzoncito((string) $s->product->name);
                    return $tipo === 'calzoncito' ? $es : ! $es;
                })
                ->sortBy(fn ($s) => [array_search(mb_strtoupper(trim((string) $s->size)), $up), $s->product->orden ?? 0, (float) $s->price])
                ->values();
        } catch (\Throwable $e) {
            Log::warning('Asistente, presentaciones: ' . $e->getMessage());
            return collect();
        }
    }

    private function claveMostrado(): string
    {
        return implode('+', (array) $this->f->dato('tallas', [])) . '|' . $this->f->dato('tipo');
    }

    private function mostrada(string $id): ?array
    {
        foreach ((array) $this->f->dato('mostradas', []) as $o) {
            if ((string) $o['id'] === $id) return $o;
        }

        // Puede estar en el carrito de una vuelta anterior.
        $s = ProductSize::with('product')->find($id);

        return $s ? [
            'id' => (string) $s->id, 'nombre' => trim((string) $s->product?->name), 'talla' => trim((string) $s->size),
            'unidades' => (int) $s->unidades, 'precio' => (float) $s->price,
        ] : null;
    }

    /**
     * [renglones para el cliente, subtotal, renglones para la orden]. Los
     * precios se leen de nuevo de la base: si cambió un precio mientras
     * conversaban, vale el de ahora.
     */
    private function resumenCarrito(): array
    {
        $lineas = [];
        $orden = [];
        $subtotal = 0.0;

        foreach ((array) $this->f->dato('carrito', []) as $c) {
            $s = ProductSize::with('product')->find($c['id']);
            if (! $s || ! $s->product) continue;

            $cant = (int) $c['cant'];
            $sub  = (float) $s->subtotalPara($cant);
            $unit = (float) $s->price;
            $nombre = trim((string) $s->product->name);
            $talla  = trim((string) $s->size);

            $subtotal += $sub;

            $lineas[] = "• {$cant} × {$nombre} talla {$talla} — $" . number_format($sub, 2);

            $orden[] = abs($sub - $unit * $cant) < 0.01
                ? "{$cant} {$nombre} talla {$talla} $" . number_format($unit, 2) . ($cant > 1 ? ' ($' . number_format($sub, 2) . ')' : '')
                : "{$cant} {$nombre} talla {$talla} $" . number_format($sub, 2) . ' (oferta ' . (int) $s->combo_qty . ' por $' . number_format((float) $s->combo_price, 2) . ')';
        }

        return [$lineas, round($subtotal, 2), $orden];
    }

    /** La tarjeta de un producto: el mensaje con su enlace (WhatsApp arma la foto). */
    private function tarjeta(ProductSize $s, int $n): void
    {
        $p = $s->product;

        $pie = "*Opción {$n}*\n"
            . '*' . trim((string) $p->name) . "*\n\n"
            . '*Talla:* ' . trim((string) $s->size) . "\n";

        if ((int) ($s->unidades ?? 0) > 0) $pie .= '*Contiene:* ' . (int) $s->unidades . " unidades\n";

        $pie .= '*Precio:* $' . number_format((float) $s->price, 2) . "\n";

        if ($s->combo_qty > 0 && $s->combo_price > 0) {
            $pie .= '*Oferta:* ' . (int) $s->combo_qty . ' por $' . number_format((float) $s->combo_price, 2) . "\n";
        }

        try {
            $pie .= "\n*Mírelo aquí:*\n" . route('store.show', $p) . '?t=' . urlencode(trim((string) $s->size));
        } catch (\Throwable $e) {
        }

        $this->texto($pie);

        if (config('asistente.con_fotos_uso')) {
            foreach (array_slice($s->fotosUsoUrls(), 0, 3) as $u) {
                WhatsappApi::enviarImagen($this->conv, $u, null, null, true);
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // Mandar
    // ════════════════════════════════════════════════════════════════════════

    private function texto(string $t): void
    {
        $t = trim($t);
        if ($t === '') return;

        WhatsappApi::enviarTexto($this->conv, $t, null, true);
    }

    private function botones(string $cuerpo, array $botones): void
    {
        WhatsappApi::enviarBotones($this->conv, trim($cuerpo), $botones, true);
    }

    private function lista(string $cuerpo, string $boton, array $filas): void
    {
        WhatsappApi::enviarLista($this->conv, trim($cuerpo), $boton, $filas, true);
    }
}

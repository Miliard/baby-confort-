<?php

namespace App\Services\Asistente;

use App\Models\ProductSize;
use App\Models\WaMensaje;
use App\Services\Etiquetado;
use App\Services\Municipios;

/**
 * El diagrama de Wil (Lucidchart), tal cual: un if y un else por paso.
 *
 *   talla     ¿Dijo talla o peso?            NO → pedir talla o peso
 *             ¿Hay esa talla?                NO → pedir el peso (si falta) y sugerir la que hay
 *                                                  ¿Acepta?  NO → agradecer y avisar cuando entre
 *   opciones  Mandar las presentaciones (fotos)
 *             ¿Tiene dudas?                  SÍ → explicar el propósito de cada una
 *             Elige una presentación
 *   municipio "Tenemos cobertura nacional… ¿desde cuál municipio nos escribe?"
 *             ¿San Miguel?                   SÍ → preguntar la colonia y pasarle el chat a Wil
 *   envio     "El envío… vale $2.50 ¿le parece bien?"   NO → no hacer nada
 *   datos     cuántos paquetes, nombre y dirección → la orden de envío
 *   orden     "¿Toda la información es correcta?"      SÍ → listo (a Pedidos)
 *
 * Fuera del diagrama: fotos, audios, rebajas, reclamos, etc. se le pasan a Wil
 * (las mismas reglas de siempre). Las preguntas sueltas se contestan con los
 * textos de config/asistente.php y nada más.
 */
trait GuionDiagrama
{
    private function correrConDiagrama($mensajes, array $turnos): void
    {
        $textos = [];
        $boton  = null;

        foreach ($mensajes as $m) {
            $this->f->ultimo_mensaje_id = $m->id;
            $t = $this->textoParaEjemplos($m);
            if ($this->paraWil) break;
            if ($b = $this->botones[$m->id] ?? null) $boton = $b;
            if ($t !== null && $t !== '') $textos[] = $t;
            if ($m->responde_a && ($sid = ((array) $this->f->dato('tarjetas', []))[$m->responde_a] ?? null)) {
                $this->f->poner('citada', (string) $sid);
            }
        }

        if (! $this->paraWil && count($turnos) >= (int) config('asistente.max_turnos_hora', 40)) {
            $this->paraWil = 'demasiados mensajes seguidos';
        }
        if ($this->paraWil) {
            $this->pasarAWil($this->paraWil, ! $this->wilEnSilencio);
            return;
        }
        if (! $textos && ! $boton) { $this->f->save(); return; }

        $primero = ! $this->f->dato('turnos');
        $turnos[] = now()->timestamp;
        $this->f->poner('turnos', $turnos);

        // Dijo que quiere comprar ("¿me podría mandar 2 paquetes…?"): a la
        // pestaña Pedidos desde ya, aunque lo siga atendiendo el asistente.
        if (! $this->f->dato('en_pedidos')) {
            foreach ($textos as $t) {
                if (\App\Services\Etiquetado::quierePedir($t)) {
                    $this->f->poner('en_pedidos', true);
                    $this->dgAPedidos();
                    break;
                }
            }
        }

        $this->dg(implode("\n", $textos), $boton, $primero);
        $this->f->save();
    }

    private function dg(string $texto, ?string $boton, bool $primero): void
    {
        $f = $this->f;
        $paso = (string) ($f->dato('dpaso') ?: 'talla');
        $n = Entender::normalizar($texto);
        $hola = $primero ? '¡Hola! 😊 ' : '';

        // ── ¿Quién es? Si ya compró, se cargan sus datos (no se le piden de nuevo).
        $this->dgCargarConocido();

        // Pega una orden de envío (la de un pedido anterior, con sus datos) o
        // pide "lo de siempre": es un pedido; te lo pasa a vos sin contestar,
        // y el chat va a la pestaña Pedidos.
        if (! in_array($paso, ['datos', 'telefono', 'orden'], true)) {
            if (preg_match('/orden de env[ií]o|✅\s*nombre completo|✅\s*direcci[oó]n/iu', $texto)) {
                $this->dgAPedidos();
                $this->pasarAWil('pegó una orden de envío (pedido repetido)', false);
                return;
            }
            if (preg_match('/\b(l[oa]s? de siempre|el de siempre|lo de siempre|(el )?mismo pedido|pedido de siempre|similar al (pasado|anterior)|(de )?l[oa]s mism[oa]s (de|que|k)|de l[oa]s mism[oa]s( entonces)?$|otro paquete (de este|igual|de los mismos)|como la (otra|ultima) vez|lo mismo de la (otra|ultima) vez|lo que me (manda|envia|trae) siempre|la que me manda siempre|que siempre me (envian|mandan|traen)|quiero lo mismo|lo mismo( por favor| porfa)?$|igual que la (vez|ultima))\b/', $n)) {
                // Cliente activo y se sabe qué llevó: se le atiende con eso.
                if (($u = $f->dato('conocido.ultima')) && $this->dgRecompra($u, $texto)) return;
                $this->dgAPedidos();
                $this->pasarAWil('pide lo mismo de antes (recompra)', false);
                return;
            }

            // "Una talla más grande", "una talla antes", "la que sigue": desde la
            // talla que llevó la vez pasada.
            if (($u = $f->dato('conocido.ultima')) && ($nueva = $this->dgTallaRelativa($n, (string) $u['talla']))) {
                $this->dgFiltroDeLoQueLlevo($u);
                $f->poner('tallas_vistas', true);
                $this->dgConTalla($nueva, 'Con gusto 😊 Le paso la talla *' . $this->bonita($nueva) . "* 👇\n\n");
                return;
            }
        }

        // "¿Cuándo me llegaría?", "¿me lo traen mañana?", "¿a qué hora?": el
        // día y la hora los decís vos.
        if (preg_match('/\b(cuando|que dia|para que dia|a que hora|que hora|hoy|manana|pasado manana|lunes|martes|miercoles|jueves|viernes|sabado|domingo|fin de semana)\b/', $n)
            && preg_match('/\b(llega|llegan|llegaria|llegarian|llegara|llegaran|entrega|entregan|entregaria|entregarian|entregaran|traen|traeria|traerian|traer|traerme|traermelo|traermelos|vendria|vendrian|envian|enviarian|mandan|mandarian|recibiria|recibir|dejar|pasarian|lo tendria|los tendria)\b/', $n)
            && ! preg_match('/\b(cuando (tendran|van a tener|les llega|vuelven|entran|ingresan|hay))\b/', $n)) {
            $this->pasarAWil('pregunta el día u hora de entrega', false);
            return;
        }

        // Lo primero que dice es que no ("no gracias", "ya no lo ocupo", "ya
        // compré"), típico al contestar el aviso de "ya llegó": no se le
        // insiste con la lista de tallas. Queda para vos, sin contestarle.
        if ($primero && ! str_contains($texto, '?') && ! $this->dgTallas($texto)
            && (Entender::siNo($texto) === false
                || preg_match('/\b(no me interesa|ya no (lo |los |las )?(necesito|ocupo|quiero|usa|usamos)|ya (compre|consegui)|no por (ahora|el momento)|despues (le )?(escribo|aviso))\b/', $n))) {
            $this->pasarAWil('dijo que no (al aviso o al empezar)', false);
            return;
        }

        // ── Una pregunta suelta con respuesta escrita por vos (config) ──────
        // Se contesta eso y nada más. El envío, las promos y "¿llega
        // mañana?" no van por acá.
        $faq = Entender::respuesta($texto, (array) config('asistente.respuestas', []));
        // "¿Cuándo tendrán?", "¿ya ingresaron?" sin decir de qué talla: se le pide la talla.
        if ($faq !== null && $this->dgEsFaqIngreso($faq) && in_array($paso, ['talla', 'peso'], true)
            && ! $this->dgTallas($texto) && ! $f->dato('sin_talla')) {
            $this->dgPedirTallaIngreso($hola);
            return;
        }
        if ($faq !== null && ! str_contains($faq, '{envio}') && ! str_contains($faq, '{promos}')
            && ! $this->dgDiceAlgoDelPaso($paso, $texto)) {
            $this->dgDecir($hola . $this->rellenar($faq), $paso);
            return;
        }

        // ── Solo asesora: "¿cuánto el envío?", "¿cuánto sería todo?" ────────
        // Lo contestás vos: a unos se les cobra envío y a otros no.
        if (static::soloAsesora()) {
            $faqE = Entender::respuesta($texto, (array) config('asistente.respuestas', []));
            if ($this->dgPideTotal($n) || ($faqE !== null && str_contains($faqE, '{envio}'))) {
                $this->pasarAWil('pregunta por el envío o el total', false);
                return;
            }
        }

        // ── "¿Cuánto sería ya con el envío?": el total ─────────────────────
        if ($this->dgPideTotal($n) &&! in_array($paso, ['datos', 'telefono', 'orden', 'colonia'], true)) {
            if (! $f->dato('municipio') && ($muni = Municipios::buscarEn($texto))) $this->ponerMunicipio($muni);
            if ($this->dgTotal($paso, $hola)) return;
        }

        // ── "¿Cuánto vale el envío a Soyapango?" (antes de elegir) ──────────
        // Ya dio el municipio: se le contesta la leyenda del envío y el
        // municipio queda guardado (no se le vuelve a preguntar).
        if (in_array($paso, ['talla', 'peso', 'unidad', 'sugerencia', 'opciones', 'cantidad_item'], true)
            && ($faq = Entender::respuesta($texto, (array) config('asistente.respuestas', []))) !== null
            && str_contains($faq, '{envio}')
            && ($muni = Municipios::buscarEn($texto)) && $this->ponerMunicipio($muni)) {
            if ($this->entregaPropia()) {
                $this->dgDecir($hola . '¿De qué colonia nos escribe, para confirmarle el costo del envío? 🚚', 'colonia');
                return;
            }
            $f->poner('envio', (float) \App\Models\Setting::envio());
            $f->poner('envio_dicho', true);
            $this->texto(trim($hola . $this->rellenar($faq)));
            if ($paso !== 'talla') return;   // ya está eligiendo: solo eso
            // Sigue con la talla solo si la dijo, si es el primer mensaje o si
            // contesta al aviso; si no, la leyenda y nada más.
            if (! $this->dgTallas($texto) && ! Entender::peso($texto) && $f->dato('tallas_vistas')) return;
            $hola = '';
        }

        // ── Dice de dónde es sin que se lo pregunten ("Suchitoto, Cuscatlán") ─
        // Se guarda y se le manda la leyenda del envío; sigue donde iba.
        elseif (! static::soloAsesora()
            && in_array($paso, ['talla', 'peso', 'opciones', 'cantidad_item', 'sugerencia'], true)
            && ! $f->dato('municipio')
            && (count(preg_split('/\s+/', trim($n))) <= 6 || preg_match('/\b(vivo en|soy de|somos de|desde|municipio|departamento|depto|colonia|canton|barrio|residencial|para (el|la)? ?(municipio|colonia))\b/', $n))
            && ($muni = Municipios::buscarEn($texto)) && $this->ponerMunicipio($muni)) {
            if (! $this->entregaPropia() && $f->dato('departamento')) {
                $f->poner('envio', (float) \App\Models\Setting::envio());
                $f->poner('envio_dicho', true);
                $faqEnvio = '';
                foreach ((array) config('asistente.respuestas', []) as $r) {
                    if (str_contains((string) ($r['texto'] ?? ''), '{envio}')) { $faqEnvio = (string) $r['texto']; break; }
                }
                if ($faqEnvio !== '') $this->texto(trim($hola . $this->rellenar($faqEnvio)));
                $hola = '';
            }
            if ($paso !== 'talla') { $f->poner('dpaso', $paso); return; }
            if (! $this->dgTallas($texto) && ! Entender::peso($texto) && $f->dato('tallas_vistas')) return;
        }

        // ── "¿Cuánto vale el envío?" y ya se sabe de dónde es (cliente conocido) ─
        elseif (in_array($paso, ['talla', 'peso', 'unidad', 'sugerencia', 'opciones', 'cantidad_item'], true)
            && $f->dato('municipio') && $f->dato('departamento')
            && ($faq = Entender::respuesta($texto, (array) config('asistente.respuestas', []))) !== null
            && str_contains($faq, '{envio}')) {
            if ($this->entregaPropia()) {
                $this->dgDecir($hola . '¿De qué colonia nos escribe, para confirmarle el costo del envío? 🚚', 'colonia');
                return;
            }
            $f->poner('envio', (float) \App\Models\Setting::envio());
            $f->poner('envio_dicho', true);
            $this->texto(trim($hola . $this->rellenar($faq)));
            if ($paso !== 'talla') return;
            if (! $this->dgTallas($texto) && ! Entender::peso($texto) && $f->dato('tallas_vistas')) return;
            $hola = '';
        }

        // ── "¿Cuánto vale el envío?" sin decir de dónde ─────────────────────
        // La leyenda y, como el municipio es imprescindible, se le pregunta.
        // Con la respuesta se sigue donde iba.
        elseif (in_array($paso, ['talla', 'peso', 'unidad', 'sugerencia', 'opciones', 'cantidad_item'], true)
            && ! $f->dato('municipio')
            && ($faq = Entender::respuesta($texto, (array) config('asistente.respuestas', []))) !== null
            && str_contains($faq, '{envio}')) {
            $f->poner('volver_a', $paso);
            $f->poner('pend_texto', mb_substr($texto, 0, 300));   // por si también pidió talla
            // Primero el municipio (es imprescindible); la leyenda va después,
            // cuando contesta de dónde es.
            $this->dgDecir(trim($hola . 'Tenemos cobertura nacional con los envíos. ¿Desde cuál municipio nos escribe, disculpe?'), 'muni_envio');
            return;
        }

        // ── Contesta al aviso de "ya llegó": ya se sabe qué había pedido ────
        // (la talla y el tipo de cuando se le dijo "agotado"). Se le mandan
        // esas fotos directo, y si dice "mándeme 2" se toma como pedido.
        if ($paso === 'talla' && ! $boton && ! $f->dato('aviso_visto')
            && ! $this->dgTallas($texto) && ! Entender::peso($texto)) {
            $f->poner('aviso_visto', true);
            if (($ctx = $this->dgContextoDelAviso()) && $this->dgDesdeAviso($ctx, $texto, $hola)) return;
        }

        // ── Tocó un botón o eligió de una lista ─────────────────────────────
        if ($boton && ! str_starts_with($boton, 'conf:')) {
            [$k, $v] = explode(':', $boton, 2) + [1 => ''];
            if ($k === 'talla' && $v === 'peso') { $this->dgDecir('¿Cuánto pesa su bebé? Por ejemplo: *25 libras* 😊', 'peso'); return; }
            if ($k === 'talla') { $this->dgConTalla($v, $hola); return; }
            if ($k === 'tipo')  { $this->dgOpciones($v); return; }
            if ($k === 'op' && ($s = ProductSize::with('product')->find($v))) { $this->dgElegir($s, null); return; }
            if ($k === 'envio') { $this->dgEnvio($v === 'si' ? 'si' : 'no'); return; }
        }

        // "No sería, cuando tenga", "que no sea calzoncito", "mejor no": cambió
        // de idea o quiere otra cosa que no hay. No se insiste: a una persona
        // (y si quiere algo que no hay, queda en Agotados para avisarle).
        if (in_array($paso, ['opciones', 'cantidad_item', 'municipio', 'muni_envio', 'envio', 'datos', 'datos_conf', 'telefono', 'orden'], true)
            && $this->dgSeArrepiente($n)) {
            if (preg_match('/\b(cuando (tenga|haya|tengan|les? (llegue|entre))|que no sea|no sea de|de los otros|otro tipo)\b/', $n)) $this->marcarAgotado();
            $this->pasarAWil('cambió de idea o quiere otra cosa: "' . mb_substr(trim($texto), 0, 80) . '"', false);
            return;
        }

        // Ya había quedado una presentación y ahora dice el otro tipo ("De
        // cinta" cuando se le anotó calzoncito): cambia; se vuelve a elegir.
        if (in_array($paso, ['cantidad_item', 'municipio', 'muni_envio', 'envio', 'datos', 'datos_conf'], true)
            && in_array($t = Entender::tipo($texto), ['cinta', 'calzoncito'], true)
            && ($carrito = (array) $f->dato('carrito', []))) {
            $ult = end($carrito);
            $s = ProductSize::with('product')->find($ult['id'] ?? null);
            if ($s && mb_strtolower($this->tipoDe((string) $s->product->name)) !== $t) {
                array_pop($carrito);
                $f->poner('carrito', array_values($carrito));
                $f->poner('dpaso', 'opciones');
                $this->dgOpciones($texto);
                return;
            }
        }

        if (in_array($paso, ['municipio', 'muni_envio', 'envio', 'cantidad_item', 'sugerencia'], true) && $this->dgPideMasUnidades($n)) {
            $this->dgMasUnidades();
            return;
        }

        switch ($paso) {
            case 'talla':     $this->dgTalla($texto, $hola); return;
            case 'peso':      $this->dgPeso($texto, $hola); return;
            case 'unidad':    $this->dgUnidad($texto); return;
            case 'sugerencia':$this->dgSugerencia($texto); return;
            case 'opciones':  $this->dgOpciones($texto); return;
            case 'municipio': $this->dgMunicipio($texto); return;
            case 'depto':     $this->dgDepto($texto); return;
            case 'colonia':   $this->dgColonia($texto); return;
            case 'envio':     $this->dgEnvio($texto); return;
            case 'datos':     $this->dgDatos($texto); return;
            case 'telefono':  $this->dgTelefono($texto); return;
            case 'orden':     $this->dgOrden($texto, $boton); return;
            case 'cantidad_item': $this->dgCantidadItem($texto); return;
            case 'muni_envio':    $this->dgMuniEnvio($texto); return;
            case 'datos_conf':    $this->dgDatosConf($texto); return;
        }
    }

    /** ¿El mensaje trae lo que se le está pidiendo en este paso? (para no tomarlo como pregunta suelta) */
    private function dgDiceAlgoDelPaso(string $paso, string $texto): bool
    {
        return match ($paso) {
            'talla', 'peso' => (bool) ($this->dgTallas($texto) || Entender::peso($texto)),
            'municipio', 'muni_envio' => (bool) Municipios::buscarEn($texto),
            'envio', 'orden', 'sugerencia' => Entender::siNo($texto) !== null,
            'datos'         => Entender::pareceDireccion($texto) || Entender::cantidad($texto) !== null,
            'datos_conf'    => Entender::siNo($texto) !== null || Entender::pareceDireccion($texto) || Entender::cantidad($texto) !== null,
            default         => false,
        };
    }

    /** Manda el mensaje y anota en qué paso queda. */
    /** Cuántos paquetes dijo al elegir: "2 paquetes", "quiero 2 de la 1", "deme dos de cinta". */
    private function dgCantidadEn(string $texto): ?int
    {
        if (($c = Entender::cantidad($texto)) !== null) return $c;
        // Varios mensajes juntos ("De este" / "Dos"): cada uno por separado.
        if (str_contains($texto, "\n")) {
            foreach (preg_split('/\n+/', $texto) as $l) if (($c = $this->dgCantidadEn($l)) !== null) return $c;
        }
        $n = Entender::normalizar($texto);
        $verbo = '(quiero|deme|mandeme|me manda|me envia|envieme|mande|necesito|ocupo|seria|serian|regaleme|me regala)';
        if (preg_match('/^\s*' . $verbo . '\s+(\S+)\s+(de|del)\b/u', $n, $m)) {
            return Entender::cantidad($m[2]);
        }
        // "envíeme dos por favor", "si, mándeme 2"
        if (preg_match('/^\s*(si\s+|ok\s+|bueno\s+)?' . $verbo . '\s+(.+)$/u', $n, $m)) {
            return Entender::cantidad(trim($m[3]));
        }
        return null;
    }

    /**
     * Si se le mandó el aviso de "ya llegó" (en los últimos 30 días), lo que
     * había pedido cuando se le dijo que estaba agotado: la talla, el tipo y
     * si era de noche. Null si no hay aviso o no se entiende qué pidió.
     */
    private function dgContextoDelAviso(): ?array
    {
        try {
            $msjs = WaMensaje::where('conversacion_id', $this->conv->id)
                ->where('created_at', '>=', now()->subDays(150))
                ->orderByDesc('id')->limit(150)
                ->get(['id', 'direccion', 'texto', 'created_at'])
                ->sortBy('id')->values();
        } catch (\Throwable $e) {
            return null;
        }

        $aviso = $msjs->filter(fn ($m) => $m->direccion === 'saliente' && Asistente::esAviso((string) $m->texto))->last();
        if (! $aviso || ($aviso->created_at && $aviso->created_at->lt(now()->subDays(30)))) return null;

        $agotado = $msjs->filter(fn ($m) => $m->direccion === 'saliente' && $m->id < $aviso->id
            && Etiquetado::diceAgotado((string) $m->texto))->last();
        if (! $agotado) return null;

        // Lo que escribió el cliente antes del "agotado" (hasta 2 días antes).
        $desde = $agotado->created_at ? $agotado->created_at->copy()->subHours(48) : null;
        $suyos = $msjs->filter(fn ($m) => $m->direccion === 'entrante' && $m->id < $agotado->id
                && (! $desde || ! $m->created_at || $m->created_at >= $desde)
                && filled($m->texto) && ! str_starts_with(trim((string) $m->texto), '['))
            ->slice(-5)->pluck('texto')->implode(' ');

        // Primero lo que dijo él; si ahí no está, lo que se le contestó.
        $leer = function (string $t) {
            $tallas = $this->dgTallas($t);
            if (! $tallas && ($n = Entender::numeroDeTalla($t)) !== null) {
                $x = config('asistente.tallas_numericas', [])[$n] ?? null;
                if ($x) $tallas = [$x];
            }
            return array_values(array_unique(array_map('mb_strtoupper', $tallas)));
        };
        $tallas = $leer($suyos) ?: $leer((string) $agotado->texto);
        if (! $tallas) return null;

        $tipo = Entender::tipo($suyos);
        if (! in_array($tipo, ['cinta', 'calzoncito'], true)) $tipo = Entender::tipo((string) $agotado->texto);

        return [
            'tallas' => $tallas,
            'tipo'   => in_array($tipo, ['cinta', 'calzoncito'], true) ? $tipo : null,
            'noche'  => (bool) preg_match('/\bnoche|nocturn/', Entender::normalizar($suyos . ' ' . $agotado->texto)),
        ];
    }

    /**
     * Contesta al aviso: se le mandan las fotos de lo que había pedido. Si
     * con eso queda una sola y dice que la quiere ("mándeme 2", "sí"), ya
     * queda elegida. Devuelve false si lo que pidió todavía no hay (sigue el
     * flujo normal).
     */
    private function dgDesdeAviso(array $ctx, string $texto, string $hola): bool
    {
        $f = $this->f;
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());
        $hay = array_values(array_filter($ctx['tallas'], fn ($t) => in_array($t, $disp, true)));
        if (! $hay) {
            // Lo que pidió todavía no llegó: se le dice y se le sugiere por el peso.
            if (count($ctx['tallas']) === 1) { $this->dgConTalla($ctx['tallas'][0], $hola); return true; }
            return false;
        }

        // Había pedido varias tallas: como siempre, te lo pasa a vos.
        if (count($hay) > 1) {
            $this->pasarAWil('respondió al aviso; había pedido varias tallas (' . implode(', ', $hay) . ')', false);
            return true;
        }

        $t = $hay[0];
        $f->poner('tallas', [$t]);
        $todas = $this->presentaciones([$t], 'ambos');
        $suyas = $todas->filter(function ($s) use ($ctx) {
            $nombre = (string) $s->product->name;
            if ($ctx['tipo'] && mb_strtolower($this->tipoDe($nombre)) !== $ctx['tipo']) return false;
            if ($ctx['noche'] && ! preg_match('/noche|nocturn/iu', $nombre)) return false;
            return true;
        })->values();
        if ($suyas->isEmpty()) $suyas = $todas;   // lo que pidió no llegó: lo que hay de su talla
        $suyas = $suyas->take(max(1, (int) config('asistente.max_fotos', 4)))->values();

        $enviadas = [];
        $pre = trim($hola . 'Le paso la talla *' . $this->bonita($t) . '* que nos había consultado 👇');
        foreach ($suyas as $s) {
            $nro = count($enviadas) + 1;
            $this->tarjeta($s, $nro, $pre);
            $pre = '';
            $enviadas[] = ['opcion' => $nro, 'id' => (string) $s->id];
        }
        $f->poner('fotos_enviadas', $enviadas);
        $f->poner('dpaso', 'opciones');
        $f->paso = 'diagrama · opciones';

        $cant = $this->dgCantidadEn($texto);
        $quiere = $cant !== null || Entender::siNo($texto) === true || Entender::quiereComprar($texto)
            || preg_match('/^\s*(quiero|deme|mandeme|me manda|me envia|envieme|mande|necesito|ocupo|regaleme)\b/u', Entender::normalizar($texto));

        if (count($enviadas) === 1 && $quiere) {
            $this->dgElegir($suyas[0], $cant);
            return true;
        }
        if ($cant !== null) $f->poner('cant_pend', $cant);   // "mándeme 2" y hay varias: el 2 se guarda

        $this->dgPreguntarDespuesDeFotos($this->guionOpcionesVistas());
        return true;
    }

    private function dgDecir(string $msg, string $paso): void
    {
        // Solo asesora: de acá en adelante (municipio, envío, datos) es tuyo.
        if ($this->cortarAntesDeLaOrden($paso, $msg)) return;

        $this->f->poner('dpaso', $paso);
        $this->f->paso = 'diagrama · ' . $paso;
        $this->texto($msg);
    }

    /** No entendió: una vez se le repite, la segunda te lo pasa. */
    /**
     * No entendió lo que escribió: NO se le dice "no le entendí" (suena
     * ilógico). No se le contesta nada y el chat queda para una persona, en
     * 🤖 Revisar, con lo que escribió como motivo.
     */
    private function dgNoEntendi(string $paso, string $repetir): void
    {
        $this->pasarAWil('no entendió (' . $paso . ')', false);
    }

    // ════════════════════════════════════════════════════════════════════════
    // 1 · Talla o peso
    // ════════════════════════════════════════════════════════════════════════

    private function dgTallas(string $texto): array
    {
        // "XLyM", "xl/m", "M-XL": tallas pegadas.
        $t = '(xxxl|xxl|2xl|3xl|xl|rn|l|m|s|g|xg|xxg)';
        $texto = preg_replace('/\b' . $t . '(y|\/|-|\+)' . $t . '\b/iu', '$1 y $3', $texto);
        // "letra M" = talla M; "XG4", "L3" (la talla con el número de otra marca).
        $texto = preg_replace('/\bletra\s+([a-z]{1,4})\b/iu', 'talla $1', $texto);
        $texto = preg_replace('/\b(xxxl|xxl|xl|xxxg|xxg|xg|l|m|g)\s?\d\b/iu', '$1', $texto);
        $tallas = Entender::tallas($texto);
        // Cada mensaje por separado: "Tienen en M" es corto aunque venga
        // junto con otros mensajes.
        $lineas = preg_split('/\n+/', $texto);
        if (count($lineas) > 1) {
            foreach ($lineas as $l) {
                foreach (Entender::tallas($l) as $x) if (! in_array($x, $tallas, true)) $tallas[] = $x;
            }
        }
        if (! $tallas && ($num = Entender::numeroDeTalla($texto)) !== null) {
            $t = config('asistente.tallas_numericas', [])[$num] ?? null;
            if ($t) $tallas = [$t];
        }
        return array_values(array_intersect(array_map('mb_strtoupper', $tallas), array_map('mb_strtoupper', (array) config('asistente.tallas', []))));
    }

    /** Si dice el tipo o "de noche" ("calzoncito de noche"), se guarda para mostrarle solo esas. */
    private function dgAnotarTipo(string $texto): void
    {
        $tipo = Entender::tipo($texto);
        if (in_array($tipo, ['cinta', 'calzoncito'], true)) $this->f->poner('tipo_pedido', $tipo);
        if (preg_match('/\bnoche|nocturn/', Entender::normalizar($texto))) $this->f->poner('noche_pedido', true);
        if ($p = $this->dgProductoNombrado($texto)) $this->f->poner('producto_pedido', $p);
    }

    /** "Jumbo", "Magic", "Comfy": la palabra que distingue a un producto, si la nombró. */
    private function dgProductoNombrado(string $texto): ?string
    {
        $n = ' ' . Entender::normalizar($texto) . ' ';
        foreach ((array) config('asistente.productos_por_nombre', ['jumbo', 'magic', 'comfy']) as $w) {
            if (preg_match('/\b' . preg_quote(Entender::normalizar($w), '/') . '\b/', $n)) return Entender::normalizar($w);
        }
        return null;
    }

    /** "Tiene 2 años", "6 meses", "un año con 3 meses": en meses. */
    private function dgEdadEnMeses(string $n): ?int
    {
        if (preg_match('/\b\d+\s*(a|-)\s*\d+\s*anos\b/', $n)) return null;   // talla de niño
        $num = ['un' => 1, 'una' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10, 'once' => 11];
        $meses = null;
        if (preg_match('/\b(\d{1,2}|un|una|dos|tres|cuatro|cinco|seis|siete|ocho)\s*(ano|anos|anio|anios|anito|anitos|anitoa|anyos)\b/', $n, $m)) {
            $meses = (($num[$m[1]] ?? (int) $m[1])) * 12;
        }
        if (preg_match('/\b(\d{1,2}|un|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|once)\s*(mes|meses|mese|mece|meces|mesesitos|mesecito|mesecitos|mesesito|m)\b/', $n, $m)
            && ($m[2] !== 'm' || preg_match('/\b(tiene|bebe|nino|nina|edad)\b/', $n))) {
            $meses = ($meses ?? 0) + ($num[$m[1]] ?? (int) $m[1]);
        }
        if ($meses === null && preg_match('/\b(recien nacid[oa]s?|no ha nacido|no a nacido|todavia no nace|aun no nace|no ha nacido|no nace|va a nacer|por nacer|cuando nazca|esta por nacer|embarazada|estoy embarazada|para (mi|un|el) bebe que viene|nacimiento)\b/', $n)) $meses = 0;
        return $meses;
    }

    /**
     * Lo que dice fuera de lo que se le preguntó, pero que tiene respuesta
     * clara (sacado de los chats reales). True si lo atendió.
     */
    private function dgSuelto(string $texto, string $paso, string $hola = ''): bool
    {
        $f = $this->f;
        $n = Entender::normalizar($texto);
        $pregunta = $paso === 'peso' ? '¿Cuánto pesa su bebé? Así le digo la talla que le queda 😊' : '¿Qué talla usa su bebé o cuánto pesa? 😊';

        // "sí", "ok", "claro", "si está bien": nada que contestar.
        if (preg_match('/^(si+|claro|ok|okay|oki|si\s+(esta bien|claro|gracias|por favor|porfavor|perfecto|exacto|asi es|creo que si|creo q si))[\s!.]*$/u', $n)) return true;

        // La edad: lo que suele usar a esa edad, y el peso (que es lo que manda).
        if (in_array($paso, ['talla', 'peso'], true) && ($meses = $this->dgEdadEnMeses($n)) !== null) {
            $edad = $meses === 0 ? 'recién nacido' : ($meses >= 12
                ? (intdiv($meses, 12) === 1 ? '1 año' : intdiv($meses, 12) . ' años') . ($meses % 12 ? ' y ' . ($meses % 12) . ' meses' : '')
                : ($meses === 1 ? '1 mes' : $meses . ' meses'));
            $pista = null;
            foreach ((array) config('asistente.tallas_por_edad', []) as [$d, $h, $txt]) {
                if ($meses >= $d && $meses <= $h) { $pista = $txt; break; }
            }
            if ($meses === 0) {
                // Recién nacido: siempre la talla RN, y solo esa (la S le queda
                // grande). Si hay, sus fotos. Si no hay, no se le dice "no hay"
                // ni se le ofrece otra: te lo pasa a vos sin contestar.
                $disp = array_map('mb_strtoupper', $this->tallasDisponibles());
                if (in_array('RN', $disp, true)) {
                    $this->dgConTalla('RN', $hola . "¡Felicidades! 🎉 Para recién nacido es la talla *RN* 👇\n\n");
                    return true;
                }
                $this->marcarAgotado();
                $this->pasarAWil('recién nacido: no hay talla RN', false);
                return true;
            }
            $this->dgDecir($hola . ($pista ? "Con {$edad} la mayoría usa talla {$pista}, pero lo que manda es el peso 😊 " : 'Lo que manda es el peso 😊 ')
                . '¿Sabe más o menos cuánto pesa?', 'peso');
            return true;
        }

        if (! in_array($paso, ['talla', 'peso'], true)) return false;

        // "¿Ya ingresaron?", "¿fecha estimada?", "¿aún no tiene del otro?": ¿para qué talla?
        if (preg_match('/\b(ingres|ya (les )?(llegaron|llego|entraron|entro|tiene|tienen|hay)|aun no (tiene|tienen|hay|les)|todavia no (tiene|tienen|hay)|fecha estimada|cuando (tendran|van a tener|les llega|ingresan|entran)|no han llegado)/', $n)) {
            $this->dgPedirTallaIngreso('');
            return true;
        }

        // Pregunta el precio, por promos, dice el tipo o cuántos quiere, o
        // que quiere pedir, pero sin decir la talla: con gusto, ¿qué talla?
        $tipo = Entender::tipo($texto);
        $cant = $this->dgCantidadEn($texto) ?? Entender::cantidad($texto, true);
        if ($cant !== null) $f->poner('cant_pend', $cant);
        // "¿Cuántos trae?" (también mal escrito: "cusntos trae", "cuatos vienen").
        if (preg_match('/\bc\w{0,4}t[oa]s?\s+(trae|traen|vienen|viene|unidades|panales|pampers?)\b|\bcuantas unidades\b|\bde cuant[oa]s?\b/', $n)) {
            $this->dgDecir('Con gusto 😊 La cantidad y el precio dependen de la talla. ' . $pregunta, $paso);
            return true;
        }
        if (Entender::preguntaPrecio($texto) || preg_match('/\b(precio|promo|promocion|oferta|3 x|3x)\b/', $n)) {
            $this->dgDecir('Con gusto 😊 El precio depende de la talla. ' . $pregunta, $paso);
            return true;
        }
        if (in_array($tipo, ['cinta', 'calzoncito'], true) || $cant !== null || Entender::quiereComprar($texto) || $this->dgProductoNombrado($texto)
            || preg_match('/\b(pedido|pedir|encargar|necesito|ocupo|quiero|me manda|mandeme|envieme|una bolsa|un paquete|pampers?|panales)\b/', $n)) {
            $this->dgDecir('Con gusto 😊 ' . $pregunta, $paso);
            return true;
        }
        return false;
    }

    /** "¿Cuánto sería ya con el envío?", "con envío incluido", "total con el envío". */
    private function dgPideTotal(string $n): bool
    {
        return (bool) preg_match('/\benvio\b/', $n)
            && (bool) preg_match('/\b(ya con|con el envio|con envio|incluido|incluyendo|en total|total|todo junto|mas el envio|con todo)\b/', $n);
    }

    /**
     * El total con el envío. Si no se sabe de dónde es, primero se pregunta el
     * municipio (y el total se le da después). En San Miguel el envío lo
     * confirmás vos. True si contestó.
     */
    private function dgTotal(string $paso, string $hola): bool
    {
        $f = $this->f;

        // Sin talla todavía: depende de la talla.
        $carrito = (array) $f->dato('carrito', []);
        $ops = $carrito ? [] : $this->guionOpcionesVistas();
        if (! $carrito && ! $ops) return false;

        if (! $f->dato('municipio')) {
            $f->poner('volver_a', $paso);
            $f->poner('pend_total', true);
            $this->dgDecir(trim($hola . 'Con gusto 😊 Tenemos cobertura nacional con los envíos. ¿Desde cuál municipio nos escribe, disculpe?'), 'muni_envio');
            return true;
        }
        if ($this->entregaPropia()) {
            $this->dgDecir(trim($hola . '¿De qué colonia nos escribe, para confirmarle el costo del envío? 🚚'), 'colonia');
            return true;
        }

        $envio = (float) \App\Models\Setting::envio();
        $f->poner('envio', $envio);
        $f->poner('envio_dicho', true);
        $m = fn ($x) => '$' . number_format($x, 2);

        if ($carrito) {
            $lineas = []; $sub = 0;
            foreach ($carrito as $c) {
                $s = ProductSize::with('product')->find($c['id']);
                if (! $s) continue;
                $cant = max(1, (int) ($c['cant'] ?? 1));
                $sub += $cant * (float) $s->price;
                $lineas[] = '• ' . $cant . ' × *' . trim(preg_replace('/\s*\(.*$/u', '', (string) $s->product->name)) . '* talla ' . trim((string) $s->size) . ': ' . $m($cant * (float) $s->price);
            }
            $nota = array_filter($carrito, fn ($c) => empty($c['dicha'])) ? "\n(por 1 paquete; si lleva más, me dice cuántos)" : '';
            $txt = trim($hola !== '' ? $hola : 'Con gusto 😊') . "\n\n" . implode("\n", $lineas) . "\n• Envío: " . $m($envio) . "\n\n*Total: " . $m($sub + $envio) . '*' . $nota;
            // Ya eligió: después del total, lo del envío ("¿le parece bien?").
            if (in_array($paso, ['envio', 'municipio', 'muni_envio', 'depto'], true)) {
                $txt .= "\n\n¿Le parece bien? 😊";
                $paso = 'envio';
            }
            $this->dgDecir($txt, $paso);
            return true;
        }

        $l = array_map(fn ($o) => '• *Opción ' . $o['opcion'] . '* (' . trim(preg_replace('/\s*\(.*$/u', '', $o['nombre'])) . '): '
            . $m($o['precio']) . ' + ' . $m($envio) . ' de envío = *' . $m($o['precio'] + $envio) . '*', $ops);
        $this->dgDecir(trim($hola !== '' ? $hola : 'Con gusto 😊') . " Con el envío, por un paquete:\n\n" . implode("\n", $l), $paso);
        return true;
    }

    /** "Los otros": las presentaciones de su talla que todavía no vio, o a una persona. */
    private function dgLosOtros(): void
    {
        $f = $this->f;
        $vistos = array_column((array) $f->dato('fotos_enviadas', []), 'id');
        $tallas = $this->dgTallasSinElegir() ?: (array) $f->dato('tallas', []);
        $otras = $tallas ? $this->presentaciones([$tallas[0]], 'ambos')
            ->filter(fn ($s) => ! in_array((string) $s->id, $vistos, true))->values() : collect();

        if ($otras->isEmpty()) {
            $this->pasarAWil('quiere "los otros" (otro producto: uno que no hay o que llevó antes)', false);
            return;
        }

        $enviadas = [];
        $pre = 'Con gusto 😊 Estas son las otras de talla *' . $this->bonita($tallas[0]) . '* 👇';
        foreach ($this->dgVariadas($otras, max(1, (int) config('asistente.max_fotos', 4))) as $s) {
            $nro = count($enviadas) + 1;
            $this->tarjeta($s, $nro, $pre);
            $pre = '';
            $enviadas[] = ['opcion' => $nro, 'id' => (string) $s->id];
        }
        $f->poner('fotos_enviadas', $enviadas);
        $this->dgPreguntarDespuesDeFotos($this->guionOpcionesVistas());
    }

    /**
     * ¿Ya compró antes? Con su número se buscan sus datos: la última orden de
     * envío del chat y, si no hay, la libreta (de las guías). Se guardan para
     * no volver a preguntarle el municipio, el nombre ni la dirección.
     */
    private function dgCargarConocido(): void
    {
        $f = $this->f;
        if ($f->dato('conocido_visto')) return;
        $f->poner('conocido_visto', true);
        if ($this->conv->esExtranjero()) return;

        $o = null; $c = null;
        try { $o = $this->guionUltimaOrden(); } catch (\Throwable $e) {}
        try { $c = \App\Models\Cliente::buscar((string) $this->conv->telefono); } catch (\Throwable $e) {}

        $nombre = trim((string) ($o['nombre'] ?? '')) ?: trim((string) ($c['nombre'] ?? ''));
        $dir    = trim((string) ($o['direccion'] ?? '')) ?: trim((string) ($c['direccion'] ?? ''));
        $muniTx = trim((string) ($o['municipio'] ?? '')) ?: trim((string) ($c['municipio'] ?? ''));
        $depto  = trim((string) ($c['departamento'] ?? ''));

        $nombre = trim(preg_replace('/(?<!\d)[267]\d{3}[\s.\-]?\d{4}(?!\d)/u', '', $nombre), " ,.-");
        if ($nombre === '' || mb_strlen($dir) < 6) return;

        $muni = $muniTx !== '' ? (Municipios::buscarEn($muniTx) ?: Municipios::buscarEn($dir)) : Municipios::buscarEn($dir);
        $f->poner('conocido', [
            'nombre' => $nombre, 'direccion' => $dir, 'municipio' => (string) $muni,
            // Lo que llevó la vez pasada (de la última orden del chat o de la guía).
            'ultima' => $this->dgUltimaCompra((string) ($o['productos'] ?? '') ?: $this->dgProductosDeLaGuia()),
        ]);

        // El municipio ya se sabe: no se le pregunta.
        if ($muni && ! $f->dato('municipio') && $this->ponerMunicipio($muni)) {
            if (! $f->dato('departamento') && $depto !== '') {
                foreach ((array) $f->dato('deptos', []) as $d) {
                    if (Municipios::normalizar($d) === Municipios::normalizar($depto)) { $this->ponerDepartamento($d); break; }
                }
            }
            // Cliente activo: el envío no se le vuelve a explicar. En San Miguel
            // vale el que se le cobró la vez pasada (si se sabe).
            $envAntes = (float) ($o['envio'] ?? 0);
            if (! $this->entregaPropia()) {
                $f->poner('envio', (float) \App\Models\Setting::envio());
                $f->poner('envio_dicho', true);
            } elseif ($envAntes > 0 && $envAntes <= 20) {
                $f->poner('envio', $envAntes);
                $f->poner('envio_dicho', true);
            }
        }
    }

    /** Lo que dice la última guía de este número ("2 Calzoncito de noche talla M"). */
    private function dgProductosDeLaGuia(): string
    {
        try {
            $d = substr(preg_replace('/\D/', '', (string) $this->conv->telefono), -8);
            if (strlen($d) < 8) return '';
            $g = \App\Models\GuiaBorrador::whereRaw("REPLACE(REPLACE(REPLACE(COALESCE(telefono,''),' ',''),'-',''),'+','') LIKE ?", ['%' . $d])
                ->orderByDesc('id')->first();
            return $g ? (string) $g->descripcion : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * De "2 Calzoncito de noche talla M $20.00": la talla, la presentación
     * (si hoy hay), el tipo, si era de noche y cuántos. Null si no se entiende.
     */
    private function dgUltimaCompra(string $productos): ?array
    {
        foreach (preg_split('/\n+|;/', trim($productos)) as $l) {
            $l = trim($l);
            if ($l === '' || ! ($tallas = $this->dgTallas($l))) continue;
            $nl = Entender::normalizar($l);

            $mejor = null; $pts = 0;
            foreach ($this->presentaciones([$tallas[0]], 'ambos') as $s) {
                $nom = Entender::normalizar(preg_replace('/\(.*$/u', '', (string) $s->product->name));
                $hit = count(array_filter(explode(' ', $nom), fn ($w) => mb_strlen($w) >= 4 && str_contains($nl, $w)));
                if ($hit > $pts) { $pts = $hit; $mejor = $s; }
            }
            $tipo = Entender::tipo($l);
            return [
                'talla'   => $tallas[0],
                'size_id' => $mejor ? (string) $mejor->id : null,
                'texto'   => mb_substr($l, 0, 120),
                'tipo'    => in_array($tipo, ['cinta', 'calzoncito'], true) ? $tipo : null,
                'noche'   => (bool) preg_match('/\bnoche|nocturn/', $nl),
                'cant'    => preg_match('/^\s*(\d{1,2})\b/', $l, $m) ? (int) $m[1] : null,
            ];
        }
        return null;
    }

    /** "Quiero lo mismo": la misma presentación y talla, si hay; si no, lo que hay de esa talla. */
    private function dgRecompra(array $u, string $texto): bool
    {
        $f = $this->f;
        $f->poner('tallas', [mb_strtoupper((string) $u['talla'])]);
        $f->poner('tallas_vistas', true);

        if (! empty($u['size_id']) && ($s = ProductSize::with('product')->find($u['size_id']))) {
            $f->poner('fotos_enviadas', [['opcion' => 1, 'id' => (string) $s->id]]);
            $this->dgElegir($s, $this->dgCantidadEn($texto));
            return true;
        }

        // Esa presentación hoy no está: lo que hay en su talla, del mismo tipo.
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());
        if (! in_array(mb_strtoupper((string) $u['talla']), $disp, true)) return false;   // ni la talla: a una persona
        $this->dgFiltroDeLoQueLlevo($u);
        $this->dgConTalla((string) $u['talla'], 'Con gusto 😊 ');
        return true;
    }

    /** Para mostrarle primero lo del mismo tipo que llevó (cinta o calzoncito, de noche). */
    private function dgFiltroDeLoQueLlevo(array $u): void
    {
        if (! empty($u['tipo'])) $this->f->poner('tipo_pedido', $u['tipo']);
        if (! empty($u['noche'])) $this->f->poner('noche_pedido', true);
    }

    /** "Una talla más grande", "una talla antes", "la que sigue": la talla vecina de la que llevó. */
    private function dgTallaRelativa(string $n, string $talla): ?string
    {
        $mas   = preg_match('/\b(una talla (mas|despues|arriba|mayor|siguiente)|talla mas grande|la (talla )?(que sigue|siguiente)|la otra talla mas grande|subir(le)? (de|una) talla|una mas grande)\b/', $n);
        $menos = preg_match('/\b(una talla (antes|menos|abajo|menor|anterior|mas pequena|mas chica)|talla mas (pequena|chica)|la (talla )?anterior|bajar(le)? (de|una) talla|una mas (pequena|chica))\b/', $n);
        if (! $mas && ! $menos) return null;

        $orden = array_map('mb_strtoupper', (array) config('asistente.tallas', []));
        $i = array_search(mb_strtoupper($talla), $orden, true);
        if ($i === false) return null;
        $j = $mas ? $i + 1 : $i - 1;
        return $orden[$j] ?? null;
    }

    /** Contesta a "¿Se lo enviamos a nombre de…, en…?". */
    private function dgDatosConf(string $texto): void
    {
        $f = $this->f;
        $c = (array) $f->dato('conocido', []);
        $n = Entender::normalizar($texto);

        // Cuántos, si lo dijo ("sí, 2").
        $cant = $this->dgCantidadEn($texto) ?? (preg_match('/\b(\d{1,2})\b/', $n, $m) ? (int) $m[1] : null);
        if ($cant && ($sin = $this->dgSinCantidad())) {
            if ($cant > (int) config('asistente.max_paquetes', 10)) { $this->pasarAWil("pide {$cant} paquetes"); return; }
            $this->dgPonerCantidad($sin['i'], $cant);
        }

        $si = Entender::siNo($texto);
        $igual = (bool) preg_match('/\b(la misma|el mismo|misma direccion|mismos datos|igual|correcto|asi es|exacto|ahi mismo|alli mismo|si a esa|a esa misma)\b/', $n);
        $cambia = (bool) preg_match('/\b(otra direccion|otro lugar|otra persona|a nombre de|ya no vivo|me cambie|cambie de|es otra|no es ahi|no ahi)\b/', $n);

        // Cambió: los datos nuevos (si ya los mandó, se leen; si no, se piden).
        if ($si === false || $cambia || (Entender::pareceDireccion($texto) && ! $igual)) {
            $f->poner('conocido_respondido', true);
            // Cambió solo la dirección: el nombre sigue siendo el mismo.
            if (! preg_match('/\b(persona|a nombre|nombre|recibe|mi (mama|hermana|esposo|esposa|suegra|hija|hijo))\b/', $n)) {
                $f->poner('nombre', $c['nombre']);
            }
            // ¿Ya mandó los datos nuevos? (lo que queda sin el "no, a otra dirección")
            $resto = trim(preg_replace('/\b(no|si|es|para|a|otra direccion|otro lugar|otra persona|a nombre de|ya no vivo ahi|me cambie|cambie de casa|es otra|no es ahi|no ahi|mi (mama|papa|hermana|hermano|esposo|esposa|suegra|hija|hijo))\b[\s,.:]*/u', ' ', $n));
            $primero = trim((string) strtok(preg_replace('/^.*?(?:para mi \w+|a nombre de)\s*,?\s*/iu', '', $texto), "\n,"));
            $nom = Entender::pareceNombre($primero);
            if ((mb_strlen($resto) >= 12 && Entender::pareceDireccion($resto)) || ($nom && count(preg_split('/\s+/', trim($nom))) >= 2)) {
                $this->dgDatos(preg_replace('/^.*?(?:para mi \w+|a nombre de)\s*,?\s*/iu', '', $texto));
                return;
            }
            $this->dgPedirDatos('Con gusto 😊 ');
            return;
        }

        // Sí: los de la vez pasada.
        if ($si === true || $igual || $cant) {
            if (! $si && ! $igual && $cant) {   // solo dijo cuántos: falta que confirme los datos
                $this->dgDecir('¿Se lo enviamos a nombre de *' . $c['nombre'] . '*, en *' . $c['direccion'] . '*? 😊', 'datos_conf');
                return;
            }
            $f->poner('conocido_respondido', true);
            $f->poner('nombre', $c['nombre']);
            $f->poner('direccion', $c['direccion']);
            $this->dgPedirDatos('');
            return;
        }

        if (Entender::esCortesia($texto)) return;
        $this->dgNoEntendi('datos_conf', '');
    }

    /**
     * El día de entrega, como lo decís vos. Antes de las 2 p.m. sale hoy;
     * después, mañana. El domingo no se envía; lo del sábado llega el lunes.
     * (Los asuetos no los sabe: ahí lo ajustás vos.)
     */
    private function dgTextoEntrega(): string
    {
        $t = (array) config('asistente.entrega_textos', []);
        if (! $t) return '';
        try {
            $ahora = now('America/El_Salvador');
        } catch (\Throwable $e) {
            return '';
        }
        $corte = (int) config('asistente.entrega_corte_hora', 14);
        $dia = (int) $ahora->dayOfWeek;   // 0 domingo … 6 sábado
        $temprano = (int) $ahora->hour < $corte;

        if ($dia === 0) return (string) ($t['domingo'] ?? '');
        if ($dia === 6) return (string) ($temprano ? ($t['sabado_temprano'] ?? '') : ($t['sabado_tarde'] ?? ''));
        if ($dia === 5 && ! $temprano) return (string) ($t['viernes_tarde'] ?? '');
        return (string) ($temprano ? ($t['temprano'] ?? '') : ($t['tarde'] ?? ''));
    }

    /** ¿Se echó para atrás o pide algo distinto a lo que se le está armando? */
    private function dgSeArrepiente(string $n): bool
    {
        return (bool) preg_match('/\b(cuando (tenga|haya|tengan|les? (llegue|entre))|que no sea|no sea de|mejor no|ya no (lo |los |la |las )?(quiero|ocupo|necesito)|no por ahora|por ahora no|ahorita no|despues (le )?(escribo|aviso|confirmo)|lo voy a pensar|lo pienso|no seria|no lo quiero|no los quiero|cancel)\b/', $n);
    }

    /**
     * La imagen de la tabla de tallas: una dirección (https://…) o un archivo
     * dentro de public/ (por defecto public/img/tabla-tallas.jpg). Null si no está.
     */
    private function dgImagenTallas(): ?string
    {
        $img = trim((string) config('asistente.imagen_tallas', ''));
        if ($img === '') return null;
        if (preg_match('#^https?://#i', $img)) return $img;
        try {
            if (function_exists('public_path') && is_file(public_path(ltrim($img, '/')))) return url('/' . ltrim($img, '/'));
        } catch (\Throwable $e) {
        }
        return null;
    }

    /** "¿Más unidades?", "¿paquete más grande?", "¿vienen con más pañales?" */
    private function dgPideMasUnidades(string $n): bool
    {
        return (bool) preg_match('/\b(mas unidades|mas panales|mas pampers|mas cantidad de panales|(paquete|paquetes|bolsa|bolsas|presentacion|presentaciones) (mas grande|de mas)|traen? mas|vengan? con mas|venga con mas|con mas unidades|de mas unidades|mas de \d+ (unidades|panales))\b/', $n);
    }

    /** Las presentaciones de su talla con más unidades que las que vio; si no hay, se le dice cuál trae más. */
    private function dgMasUnidades(): void
    {
        $f = $this->f;
        $tallas = $this->dgTallasSinElegir() ?: (array) $f->dato('tallas', []);
        if (! $tallas) { $this->pasarAWil('pregunta por paquetes con más unidades', false); return; }
        $t = $tallas[0];

        $todas = $this->presentaciones([$t], 'ambos');
        $vistos = array_column((array) $f->dato('fotos_enviadas', []), 'id');
        $maxVisto = (int) $todas->filter(fn ($s) => in_array((string) $s->id, $vistos, true))->max('unidades');
        $mas = $todas->filter(fn ($s) => (int) $s->unidades > $maxVisto)->sortByDesc('unidades')->values();

        if ($mas->isNotEmpty()) {
            $enviadas = [];
            $pre = 'Con gusto 😊 Estas traen más unidades en talla *' . $this->bonita($t) . '* 👇';
            foreach ($mas->take(max(1, (int) config('asistente.max_fotos', 4))) as $s) {
                $nro = count($enviadas) + 1;
                $this->tarjeta($s, $nro, $pre);
                $pre = '';
                $enviadas[] = ['opcion' => $nro, 'id' => (string) $s->id];
            }
            $f->poner('fotos_enviadas', $enviadas);
            $f->poner('dpaso', 'opciones');
            $this->dgPreguntarDespuesDeFotos($this->guionOpcionesVistas());
            return;
        }

        $mayor = $todas->sortByDesc('unidades')->first();
        if (! $mayor) { $this->pasarAWil('pregunta por paquetes con más unidades', false); return; }
        $paso = (string) ($f->dato('dpaso') ?: 'opciones');
        $nro = null;
        foreach ((array) $f->dato('fotos_enviadas', []) as $e) if ((string) $e['id'] === (string) $mayor->id) $nro = $e['opcion'];
        $cual = $todas->count() > 1 && $nro
            ? 'la *opción ' . $nro . '* (' . trim(preg_replace('/\s*\(.*$/u', '', (string) $mayor->product->name)) . '), de *' . (int) $mayor->unidades . ' unidades*'
            : 'la de *' . (int) $mayor->unidades . ' unidades*';
        $this->dgDecir('En talla *' . $this->bonita($t) . '* por el momento la presentación que trae más es ' . $cual . ' 😊', $paso);
    }

    /**
     * Tu texto de "Día vs Noche" (config/asistente.php), el de calzoncito o el
     * de cinta según lo que está viendo. Null si no aplica (no hay de día y de
     * noche del mismo tipo entre lo que vio).
     */
    private function dgTextoDiaNoche(array $ops): ?string
    {
        $textos = (array) config('asistente.dia_noche_textos', []);
        if (! $textos || ! $ops) return null;
        $tipos = array_values(array_unique(array_map(fn ($o) => mb_strtolower($o['tipo']), $ops)));
        $hayNoche = (bool) array_filter($ops, fn ($o) => $o['noche']);
        $hayDia   = (bool) array_filter($ops, fn ($o) => ! $o['noche']);
        if (count($tipos) !== 1 || ! $hayNoche || ! $hayDia) return null;
        $t = trim((string) ($textos[$tipos[0]] ?? ''));
        return $t !== '' ? $t : null;
    }

    /**
     * Hasta $max presentaciones, sin dejar afuera ningún tipo: primero una de
     * cada combinación (cinta / calzoncito, de día / de noche), después las
     * demás. Siempre en el orden del catálogo.
     */
    private function dgVariadas($lista, int $max)
    {
        $lista = collect($lista)->values();
        if ($lista->count() <= $max) return $lista;

        $clave = fn ($s) => mb_strtolower($this->tipoDe((string) $s->product->name)) . '|'
            . (preg_match('/noche|nocturn/iu', (string) $s->product->name) ? 'noche' : 'dia');

        $elegidos = [];
        $vistos = [];
        foreach ($lista as $i => $s) {
            $k = $clave($s);
            if (! isset($vistos[$k])) { $vistos[$k] = true; $elegidos[$i] = true; }
        }
        foreach ($lista as $i => $s) {
            if (count($elegidos) >= $max) break;
            $elegidos[$i] = true;
        }
        ksort($elegidos);
        return $lista->only(array_slice(array_keys($elegidos), 0, max($max, count($vistos))))->values();
    }

    /** Al chat le pone la etiqueta de pedido (pestaña Pedidos). */
    private function dgAPedidos(): void
    {
        static::$hablando = false;
        try {
            Etiquetado::marcarIntencion($this->conv);
        } catch (\Throwable $e) {
        } finally {
            static::$hablando = true;
        }
    }

    /** ¿Es tu respuesta de "¿cuándo tendrán?" (la del barco)? */
    private function dgEsFaqIngreso(string $faq): bool
    {
        foreach ((array) config('asistente.respuestas', []) as $r) {
            if (in_array('cuando tendran', (array) ($r['palabras'] ?? []), true)) return trim((string) $r['texto']) === trim($faq);
        }
        return false;
    }

    /** "¿Ya ingresaron?" sin talla: ¿para qué talla?, con la lista de las que hay. */
    private function dgPedirTallaIngreso(string $hola): void
    {
        $this->f->poner('tallas_vistas', true);
        $this->dgDecir($hola . "¿Para qué talla necesita? 😊 Estas son las tallas que tenemos:\n\n" . implode("\n", $this->guionLineasTallas()), 'talla');
    }

    /** Las tallas que hay, con sus libras, escritas (sin botones). */
    private function dgPedirTalla(string $hola): void
    {
        // Primera vez: la imagen con la tabla de tallas (si está puesta), con la
        // pregunta de pie. Si no hay imagen, la lista escrita de siempre.
        if (! $this->f->dato('tallas_vistas') && ($img = $this->dgImagenTallas())) {
            $this->f->poner('tallas_vistas', true);
            try {
                \App\Services\WhatsappApi::enviarImagen($this->conv, $img, trim($hola . '¿Qué talla usa su bebé o cuánto pesa? 😊'), null, true);
                $this->f->poner('dpaso', 'talla');
                $this->f->paso = 'diagrama · talla';
                return;
            } catch (\Throwable $e) {
                // Si la imagen no sale, va la lista escrita.
            }
        }

        $lista = '';
        if (! $this->f->dato('tallas_vistas')) {
            $this->f->poner('tallas_vistas', true);
            $lista = "Estas son las tallas que tenemos:\n\n" . implode("\n", $this->guionLineasTallas()) . "\n\n";
        }
        $this->dgDecir($hola . $lista . '¿Qué talla usa su bebé o cuánto pesa? 😊', 'talla');
    }

    private function dgTalla(string $texto, string $hola): void
    {
        $this->dgAnotarTipo($texto);
        // "Mándeme 2 paquetes de … talla XL": los 2 se guardan para cuando elija.
        if (($c = $this->dgCantidadEn($texto) ?? Entender::cantidad($texto, true)) !== null && $this->dgTallas($texto)) $this->f->poner('cant_pend', $c);
        $tallas = $this->dgTallas($texto);
        if ($tallas) {
            // Pide dos o más tallas: no se contesta nada, te lo pasa a vos.
            if (count($tallas) > 1) { $this->pasarAWil('pide varias tallas (' . implode(', ', $tallas) . ')', false); return; }
            $this->dgConTalla($tallas[0], $hola);
            return;
        }

        if ($p = Entender::peso($texto)) { $this->dgConPeso($p, $hola); return; }

        // Dice la edad (o que no ha nacido), aunque sea el primer mensaje.
        if ($this->dgEdadEnMeses(Entender::normalizar($texto)) !== null && $this->dgSuelto($texto, 'talla', $hola)) return;

        // Cliente activo (ya compró): sin tabla ni envío. Solo el saludo; él
        // dice qué quiere ("lo mismo", "una talla más grande", "talla M"…).
        if ($hola !== '' && $this->f->dato('conocido')) {
            $this->dgDecir('¡Hola, buenas! 😊 ¿En qué le podemos servir?', 'talla');
            return;
        }

        // Primer mensaje (saludo, "información", "¿tiene pampers?"): se pide la talla.
        if ($hola !== '' || Entender::esSaludo($texto) || ! $this->f->dato('tallas_vistas')) { $this->dgPedirTalla($hola); return; }

        if (Entender::esCortesia($texto)) return;
        if ($this->dgSuelto($texto, 'talla')) return;
        $this->dgNoEntendi('talla', '¿Qué talla usa su bebé o cuánto pesa? 😊');
    }

    /**
     * Pidió varias ("M y XL"): se le mandan las presentaciones de TODAS las
     * que hay, cada grupo con su talla arriba. Las que no hay, se le avisa.
     */
    private function dgConVariasTallas(array $tallas, string $hola): void
    {
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());
        $hay = array_values(array_filter($tallas, fn ($t) => in_array(mb_strtoupper($t), $disp, true)));
        $no  = array_values(array_diff($tallas, $hay));

        if (! $hay) { $this->dgConTalla($tallas[0], $hola); return; }

        $antes = $hola;
        if ($no) {
            $this->marcarAgotado();
            $antes .= 'En este momento no tenemos talla ' . $this->unir(array_map(fn ($t) => $this->bonita($t), $no)) . " 😔\n\n";
        }

        $this->f->poner('tallas', array_map('mb_strtoupper', $hay));
        $this->dgMostrar($antes);
    }

    /** Dijo una talla: si hay, las presentaciones; si no, el peso para sugerir. */
    private function dgConTalla(string $talla, string $hola): void
    {
        $f = $this->f;
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());

        if (in_array(mb_strtoupper($talla), $disp, true)) {
            $f->poner('tallas', [mb_strtoupper($talla)]);
            $this->dgMostrar($hola);
            return;
        }

        $this->marcarAgotado();
        $f->poner('sin_talla', mb_strtoupper($talla));

        // Ya eligió de otra talla: se le avisa que esta no hay y se sigue.
        if ($f->dato('carrito')) {
            $this->dgDespuesDeElegir('En este momento no tenemos talla ' . $this->bonita($talla) . " 😔\n\n");
            return;
        }

        if ($f->dato('peso_lb') !== null) {
            $this->dgSugerir((float) $f->dato('peso_lb'), $hola . 'En este momento no tenemos talla ' . $this->bonita($talla) . ' 😔 ');
            return;
        }

        $quien = in_array(mb_strtoupper($talla), array_map('mb_strtoupper', (array) config('asistente.tallas_nino', [])), true) ? 'el niño o la niña' : 'su bebé';
        $this->dgDecir($hola . 'En este momento no tenemos talla ' . $this->bonita($talla) . " 😔 ¿Cuánto pesa {$quien}? Así le sugiero la talla que le puede quedar.", 'peso');
    }

    private function dgConPeso(array $p, string $hola): void
    {
        $f = $this->f;

        if (! empty($p['kg'])) {
            $lb = str_ends_with((string) ($p['dicho'] ?? ''), 'lb') ? (float) $p['dicho'] : round($p['kg'] * 2.2046, 1);
        } else {
            $v = (float) ($p['valor'] ?? 0);
            if ($v <= 0) { $this->dgPedirTalla($hola); return; }
            if ($v <= 25) {
                $f->poner('peso_pend', $v);
                $num = rtrim(rtrim(number_format($v, 1), '0'), '.');
                $this->dgDecir($hola . "¿Son {$num} libras o {$num} kilos?", 'unidad');
                return;
            }
            $lb = $v;
        }

        $f->poner('peso_lb', $lb);
        $this->dgPorPeso($lb, $hola);
    }

    private function dgPeso(string $texto, string $hola): void
    {
        // Le pedimos el peso, pero puede contestar con otra talla.
        $this->dgAnotarTipo($texto);
        if ($t = $this->dgTallas($texto)) { $this->dgConTalla($t[0], $hola); return; }
        if ($p = Entender::peso($texto)) { $this->dgConPeso($p, $hola); return; }
        if (Entender::esCortesia($texto)) return;
        if ($this->dgSuelto($texto, 'peso')) return;
        $this->dgNoEntendi('peso', '¿Cuánto pesa su bebé? Por ejemplo: *25 libras* 😊');
    }

    private function dgUnidad(string $texto): void
    {
        $u = Entender::unidad($texto);
        $v = (float) $this->f->dato('peso_pend');
        if (! $u || $v <= 0) { $this->dgNoEntendi('unidad', '¿Son libras o kilos?'); return; }

        $lb = $u === 'kg' ? round($v * 2.2046, 1) : $v;
        $this->f->quitar('peso_pend');
        $this->f->poner('peso_lb', $lb);
        $this->dgPorPeso($lb, '');
    }

    /** Del peso a la talla: si la que le queda hay, las presentaciones; si no, sugerir. */
    private function dgPorPeso(float $lb, string $hola): void
    {
        $quedan = $this->dgTallasPorPeso($lb);
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());
        $hay = array_values(array_intersect($quedan, $disp));
        $v = rtrim(rtrim(number_format($lb, 1), '0'), '.');

        if ($quedan && $hay && $hay[count($hay) - 1] === $quedan[count($quedan) - 1]) {
            // La más grande que le queda (le dura más) y hay.
            $t = $hay[count($hay) - 1];
            $this->f->poner('tallas', [$t]);
            $this->dgMostrar($hola . "Con {$v} libras le queda la talla *" . $this->bonita($t) . "* 😊\n\n");
            return;
        }

        $this->dgSugerir($lb, $hola);
    }

    /** Las tallas que le quedan con ese peso, de chica a grande. */
    private function dgTallasPorPeso(float $lb): array
    {
        if ($lb <= (float) config('asistente.rn_hasta_kg', 4.5) * 2.2046) return ['RN'];

        $out = [];
        foreach (array_map('mb_strtoupper', (array) config('asistente.tallas', [])) as $t) {
            $r = $this->guionRangoLb($t);
            if ($r && $lb >= $r[0] && $lb <= $r[1]) $out[] = $t;
        }
        return $out;
    }

    /** No hay la que pidió (o la que le queda): se sugiere la vecina que sí hay. */
    private function dgSugerir(float $lb, string $antes): void
    {
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());
        $hay = array_values(array_intersect($this->dgTallasPorPeso($lb), $disp));
        $v = rtrim(rtrim(number_format($lb, 1), '0'), '.');

        if (! $hay) {
            $this->marcarAgotado();
            $this->dgDespedidaSinTalla($antes);
            return;
        }

        $t = $hay[count($hay) - 1];
        $this->f->poner('sugerida', $t);
        $this->dgDecir($antes . "Con {$v} libras le puede quedar la talla *" . $this->bonita($t) . '* 😊 ¿Le muestro las presentaciones de esa talla?', 'sugerencia');
    }

    private function dgSugerencia(string $texto): void
    {
        $f = $this->f;
        $si = Entender::siNo($texto);

        if ($si === true) {
            $f->poner('tallas', [(string) $f->dato('sugerida')]);
            $f->quitar('sugerida');
            $this->dgMostrar('');
            return;
        }
        if ($si === false) { $this->dgDespedidaSinTalla(''); return; }

        if ($t = $this->dgTallas($texto)) { $this->dgConTalla($t[0], ''); return; }
        $this->dgNoEntendi('sugerencia', '¿Le muestro las presentaciones de la talla *' . $this->bonita((string) $f->dato('sugerida')) . '*?');
    }

    /** NO acepta la sugerencia (o no hay nada que le quede). */
    private function dgDespedidaSinTalla(string $antes): void
    {
        $this->texto(trim($antes . 'Le damos las gracias por su consulta 😊 No dude en comunicarse con nosotros, estamos para servirle. '
            . 'Apenas tengamos la talla disponible, nos ponemos en contacto.'));
        $this->f->estado = 'terminado';
        $this->f->motivo = 'no hay su talla (avisarle cuando entre)';
        $this->f->paso = 'diagrama · sin talla';
    }

    // ════════════════════════════════════════════════════════════════════════
    // 2 · Presentaciones, dudas y elección
    // ════════════════════════════════════════════════════════════════════════

    private function dgMostrar(string $antes): void
    {
        $f = $this->f;
        $tallas = (array) $f->dato('tallas', []);
        $varias = count($tallas) > 1;
        $max = max(1, (int) config('asistente.max_fotos', 4)) * max(1, count($tallas));
        $enviadas = [];

        // Pidió un tipo ("calzoncito de noche"): solo esas, si hay.
        $tipoPed = (string) $f->dato('tipo_pedido', '');
        $nochePed = (bool) $f->dato('noche_pedido');
        $prodPed = (string) $f->dato('producto_pedido', '');
        $f->quitar('tipo_pedido', 'noche_pedido', 'producto_pedido');

        foreach ($tallas as $t) {
            $primeraDeTalla = true;
            $lista = $this->presentaciones([$t], 'ambos');
            if (! $varias && ($tipoPed !== '' || $nochePed || $prodPed !== '')) {
                $suyas = $lista->filter(function ($s) use ($tipoPed, $nochePed, $prodPed) {
                    $nom = (string) $s->product->name;
                    if ($prodPed !== '' && ! str_contains(Entender::normalizar($nom), $prodPed)) return false;
                    if ($tipoPed !== '' && mb_strtolower($this->tipoDe($nom)) !== $tipoPed) return false;
                    if ($nochePed && ! preg_match('/noche|nocturn/iu', $nom)) return false;
                    return true;
                })->values();
                if ($suyas->isNotEmpty()) $lista = $suyas;
            }
            // Si hay más de las que se mandan, que no falte ningún tipo (cinta,
            // calzoncito, de día, de noche): antes salían solo las primeras y
            // el calzoncito de noche se quedaba afuera.
            $lista = $this->dgVariadas($lista, max(1, (int) config('asistente.max_fotos', 4)));
            foreach ($lista as $s) {
                if (count($enviadas) >= $max) break 2;
                $nro = count($enviadas) + 1;

                // Arriba de la primera foto de cada talla: "Talla M 👇".
                $pre = '';
                if (! $enviadas) $pre = trim($antes);
                if ($varias && $primeraDeTalla) $pre = trim($pre . "\n\n" . '*Talla ' . $this->bonita($t) . '* 👇');

                $this->tarjeta($s, $nro, $pre);
                $enviadas[] = ['opcion' => $nro, 'id' => (string) $s->id];
                $primeraDeTalla = false;
            }
        }
        $f->poner('fotos_enviadas', $enviadas);

        // Después de las fotos, según lo que hay en esa talla:
        //  · de cinta Y de calzoncito → botones [Calzoncito] [Cinta]
        //  · solo de un tipo           → "Solo tenemos estas presentaciones de …"
        //    y si son varias, una lista para elegir cuál.
        $this->dgPreguntarDespuesDeFotos($this->guionOpcionesVistas());
    }

    private function dgPreguntarDespuesDeFotos(array $ops): void
    {
        $this->f->poner('dpaso', 'opciones');
        $this->f->paso = 'diagrama · opciones';

        $cintas = array_values(array_filter($ops, fn ($o) => $o['tipo'] === 'Cinta'));
        $calzon = array_values(array_filter($ops, fn ($o) => $o['tipo'] === 'Calzoncito'));

        if ($cintas && $calzon) {
            $this->texto('¿Cómo lo desea: de calzoncito o de cinta (de pegar a los lados)? 😊');
            return;
        }

        $todas = $cintas ?: $calzon;
        $tipo = $cintas ? 'cinta (de pegar a los lados)' : 'calzoncito';

        if (count($todas) <= 1) { $this->texto('Solo tenemos esta presentación de ' . $tipo . ' 😊'); return; }

        // Sin "¿Cuál desea?": no se presiona. El cliente elige cuando quiera.
        $this->texto('Solo tenemos estas presentaciones de ' . $tipo . ' 😊');
    }

    /** Para elegir la presentación: escrito, con el número de opción (sin botones). */
    private function dgListaDeOpciones(string $cuerpo, array $ops): void
    {
        $l = array_map(fn ($o) => 'la *opción ' . $o['opcion'] . '* (' . trim(preg_replace('/\s*\(.*$/u', '', $o['nombre'])) . ')', $ops);
        $cuerpo = preg_replace('/[\s?😊]+$/u', '', $cuerpo);
        $this->texto(trim($cuerpo . ($l ? ': ' . $this->unir($l, ' o ') . '?' : '?') . ' 😊'));
    }

    /**
     * Varias del mismo tipo: ¿cuál desea? Nunca "¿de día o de noche?": la
     * clienta cree que la de noche solo se puede usar de noche (y al revés).
     */
    private function dgComoLasQuiere(array $ops): string
    {
        return '¿Cuál desea: ' . $this->unir(array_map(fn ($o) => 'la *opción ' . $o['opcion'] . '* (' . $o['nombre'] . ')', $ops), ' o ') . '?';
    }

    private function dgOpciones(string $texto): void
    {
        $f = $this->f;
        // Con varias tallas, cuenta solo lo de las que faltan elegir.
        $faltan = array_map('mb_strtoupper', $this->dgTallasSinElegir() ?: (array) $f->dato('tallas', []));
        $vistas = [];
        foreach ((array) $f->dato('fotos_enviadas', []) as $e) {
            $s = ProductSize::with('product')->find($e['id']);
            if ($s && in_array(mb_strtoupper(trim((string) $s->size)), $faltan, true)) {
                $vistas[] = ['id' => (string) $s->id, 'nombre' => trim((string) $s->product->name), 'unidades' => (int) $s->unidades, 'precio' => (float) $s->price];
            }
        }

        // ¿Eligió una? (por número, por nombre, o respondiendo a la foto)
        // Una pregunta ("¿tendrán de más unidades?") no es elegir, aunque
        // responda citando la foto o nombre algo de la opción.
        $esPregunta = (str_contains($texto, '?') || preg_match('/^\s*(tendran|tendra|tiene|tienen|hay|habra|cual|cuales|que|como|cuanto|cuantos|cuantas|de cuantos|vienen|traen|son|es)\b/', $nn ?? Entender::normalizar($texto)))
            && ! preg_match('/\b(quiero|deme|mandeme|me manda|envieme|la opcion|opcion \d)\b/', Entender::normalizar($texto));

        // "¿Tendrán de más unidades?", "¿paquete más grande?"
        if ($this->dgPideMasUnidades(Entender::normalizar($texto))) { $f->quitar('citada'); $this->dgMasUnidades(); return; }

        // Decir solo el tipo ("de pegar a los lados", "calzoncito") contesta
        // "¿cómo lo desea?": no es elegir una presentación, aunque esas
        // palabras estén en el nombre del producto.
        $nt = Entender::normalizar($texto);
        $soloTipo = in_array(Entender::tipo($texto), ['cinta', 'calzoncito'], true)
            && ! preg_match('/\b(quiero|quisiera|deme|mandeme|me manda|envieme|necesito|ocupo|opcion|la \d|el \d|\d+ paquetes?)\b/', $nt)
            && $this->dgCantidadEn($texto) === null && ! $this->dgProductoNombrado($texto);
        // "De estas tengo por el momento", "todavía me quedan", "casi se me
        // terminan": cuenta lo que tiene, no está pidiendo. No se elige nada.
        if (preg_match('/\b(de (estas|estos|esas|esos|esta|este) (tengo|todavia tengo|aun tengo)|todavia (tengo|me quedan)|aun (tengo|me quedan)|ya tengo|tengo (todavia|aun|por el momento)|por el momento tengo|me quedan|se me (estan )?(terminando|acabando)|casi se me (terminan|acaban)|ya casi se me)\b/', $nt)
            && ! preg_match('/\b(quiero|quisiera|deme|mandeme|me manda|envieme|necesito|ocupo|\d+ paquetes?)\b/', $nt)) {
            $f->quitar('citada');
            return;
        }

        $id = ($esPregunta || $soloTipo) ? null : ($f->dato('citada') ?: Entender::opcion($texto, $vistas));
        if ($soloTipo) $f->quitar('citada');

        // Hay una sola opción y dice "quiero 2", "sí", "lo quiero": es esa.
        // "Bueno gracias", "ok gracias" es despedirse, no elegir.
        $soloGracias = (bool) preg_match('/\bgracias\b/', Entender::normalizar($texto))
            && $this->dgCantidadEn($texto) === null && ! Entender::quiereComprar($texto)
            && ! preg_match('/\b(quiero|deme|mandeme|me manda|envieme|necesito|ocupo)\b/u', Entender::normalizar($texto));
        if (! $id && count($vistas) === 1 && ! $soloGracias && ! $esPregunta
            && ($this->dgCantidadEn($texto) !== null || Entender::siNo($texto) === true || Entender::quiereComprar($texto)
                || preg_match('/^\s*(quiero|deme|mandeme|me manda|necesito|ocupo)\b/u', Entender::normalizar($texto)))) {
            $id = $vistas[0]['id'];
        }
        $f->quitar('citada');
        if ($id && ($elegida = ProductSize::with('product')->find($id))) {
            $this->dgElegir($elegida, $this->dgCantidadEn($texto));
            return;
        }

        // Dijo el tipo o día/noche ("en calzoncito los quiero", "el de
        // noche"): si con eso queda UNA sola, esa es la que eligió.
        $idsVistas = array_column($vistas, 'id');
        $ops = array_values(array_filter($this->guionOpcionesVistas(), fn ($o) => in_array($this->dgIdDeOpcion($o['opcion']), $idsVistas, true)));
        $nn = Entender::normalizar($texto);
        // Pide OTRA talla ("y la talla M"): se suman sus fotos, sin perder las de antes.
        $nuevas = array_values(array_diff($this->dgTallas($texto), (array) $f->dato('tallas', [])));
        if ($nuevas) {   // pide otra talla más: también te lo pasa, sin contestar
            $this->pasarAWil('pide varias tallas (' . implode(', ', array_merge((array) $f->dato('tallas', []), $nuevas)) . ')', false);
            return;
        }

        // "¿Cuál es la diferencia entre el de noche y el de día?": es una
        // pregunta, no un filtro. Se contesta y no se reenvían fotos.
        if (Entender::tipo($texto) === 'diferencia' || $this->guionPideDiferencia($nn)
            || preg_match('/\b(diferencia|en que se diferencian|que tiene de diferente|cual es mejor|cual me conviene|entre (el|la|los|las)? ?(de )?(noche|dia).*(y|o) (el|la|los|las)? ?(de |d )?(noche|dia))\b/', $nn)) {
            $txt = $this->dgTextoDiaNoche($ops) ?? ($ops ? $this->guionDiferenciaReglas($ops) : '');
            if ($txt !== '') { $this->dgDecir($txt, 'opciones'); return; }
        }

        // El tipo que dijo antes ("de cinta") sigue valiendo cuando después
        // dice "de noche".
        $quiereNoche = (bool) preg_match('/\bnoche|nocturn|dormir/', $nn);
        $quiereDia   = (bool) preg_match('/\bde dia\b|\bdiario|para el dia/', $nn);
        $tipo = Entender::tipo($texto);
        if (in_array($tipo, ['cinta', 'calzoncito'], true)) $f->poner('dtipo', $tipo);
        elseif ($f->dato('dtipo') && ($quiereNoche || $quiereDia)) $tipo = (string) $f->dato('dtipo');
        if (in_array($tipo, ['cinta', 'calzoncito'], true) || $quiereNoche || $quiereDia) {
            $sirven = array_values(array_filter($ops, function ($o) use ($tipo, $quiereNoche, $quiereDia) {
                if (in_array($tipo, ['cinta', 'calzoncito'], true) && mb_strtolower($o['tipo']) !== $tipo) return false;
                if ($quiereNoche && ! $o['noche']) return false;
                if ($quiereDia && $o['noche']) return false;
                return true;
            }));
            // "Quiero 2 de cinta": los 2 se guardan para cuando elija cuál.
            if (($c = $this->dgCantidadEn($texto)) !== null) $f->poner('cant_pend', $c);

            // Del catálogo hay más de ese tipo que las que vio ("tenemos varios
            // de pegar a los lados"): se le mandan todas las de su talla.
            $tallasVer = $this->dgTallasSinElegir() ?: (array) $f->dato('tallas', []);
            if ($tallasVer && count($tallasVer) === 1) {
                $cat = $this->presentaciones([$tallasVer[0]], 'ambos')->filter(function ($s) use ($tipo, $quiereNoche, $quiereDia) {
                    $nom = (string) $s->product->name;
                    if (in_array($tipo, ['cinta', 'calzoncito'], true) && mb_strtolower($this->tipoDe($nom)) !== $tipo) return false;
                    $esNoche = (bool) preg_match('/noche|nocturn/iu', $nom);
                    if ($quiereNoche && ! $esNoche) return false;
                    if ($quiereDia && $esNoche) return false;
                    return true;
                })->values();
                if ($cat->count() > count($sirven) && ! ($this->dgCantidadEn($texto) !== null && $cat->count() === 1)) {
                    $enviadas = [];
                    foreach ($this->dgVariadas($cat, max(1, (int) config('asistente.max_fotos', 4))) as $s) {
                        $nro = count($enviadas) + 1;
                        $this->tarjeta($s, $nro, '');
                        $enviadas[] = ['opcion' => $nro, 'id' => (string) $s->id];
                    }
                    $f->poner('fotos_enviadas', $enviadas);
                    $this->dgPreguntarDespuesDeFotos($this->guionOpcionesVistas());
                    return;
                }
            }

            // Si además dice que lo quiere ("quiero 2 de noche"), es una
            // elección. Si solo dice el tipo ("calzoncito"), se le reenvían
            // las fotos de ese tipo y nada más: todavía no eligió.
            $explicito = $this->dgCantidadEn($texto) !== null || Entender::quiereComprar($texto) || (bool) $f->dato('cant_pend')
                || (bool) preg_match('/^\s*(quiero|deme|mandeme|me manda|necesito|ocupo|lo quiero|los quiero)\b/u', $nn);

            // Pidió varias tallas y en alguna no hay de ese tipo ("cinta" y en
            // M solo hay calzoncito): se le dice, en vez de callarlo.
            $que = trim((in_array($tipo, ['cinta', 'calzoncito'], true) ? 'de ' . $tipo : '') . ($quiereNoche ? ' de noche' : ($quiereDia ? ' de día' : '')));
            $nota = $this->dgTallasSinEseTipo($ops, $sirven, $que);

            if (count($sirven) === 1 && $explicito) {
                $s = ProductSize::with('product')->find($this->dgIdDeOpcion($sirven[0]['opcion']));
                if ($s) {
                    if ($nota !== '') $this->texto($nota);
                    $this->dgElegir($s, $this->dgCantidadEn($texto));
                    return;
                }
            }

            if ($sirven && count($sirven) < count($ops)) {
                $this->dgReenviar($sirven, $nota);
                return;
            }

            if (count($sirven) > 1) {
                // Todas las que vio son de lo que pidió: se le dice, sin preguntarle cuál.
                $f->poner('dpaso', 'opciones');
                $que = trim((in_array($tipo, ['cinta', 'calzoncito'], true) ? 'de ' . $tipo : '') . ($quiereNoche ? ' de noche' : ($quiereDia ? ' de día' : '')));
                $this->texto((count($sirven) === 2 ? 'Las dos son ' : 'Todas son ') . $que . ' 😊');
                return;
            }

            if (! $sirven) {
                $this->marcarAgotado();   // para avisarle cuando entre
                $this->dgDecir($nota !== '' ? $nota : 'En esa talla en este momento no tenemos ' . $que . ' 😔', 'opciones');
                return;
            }
        }

        // Ya eligió de una talla y a la otra le dice que no ("no", "así está
        // bien"): se quita esa talla y sigue.
        if (Entender::siNo($texto) === false && (array) $f->dato('carrito', []) && ($faltan = $this->dgTallasSinElegir())) {
            $f->poner('tallas', array_values(array_filter((array) $f->dato('tallas', []), fn ($t) => mb_strtoupper($t) !== mb_strtoupper($faltan[0]))));
            $this->dgDespuesDeElegir('Con gusto 😊' . "\n\n");
            return;
        }

        // ¿Tiene dudas? → se le explica el propósito de cada una.
        // Solo si pregunta por la diferencia o para qué es cada una (un "?"
        // suelto no basta: "¿De sinta?" es otra cosa).
        $duda = Entender::tipo($texto) === 'diferencia' || $this->guionPideDiferencia($nn)
            || preg_match('/\b(cual es (la )?(mejor|diferencia)|diferencia|para que (es|son|sirve)|que es|como es|como son|explique|expliqueme|duda)\b/', $nn);
        if ($duda) {
            $txt = $ops ? ($this->dgTextoDiaNoche($ops) ?? $this->guionDiferenciaReglas($ops)) : '';
            if ($txt !== '') { $this->dgDecir($txt, 'opciones'); return; }
        }

        if (Entender::siNo($texto) === false || Entender::esCortesia($texto)) return;   // "no, gracias": sin insistir

        // Otra talla en vez de elegir.
        if ($t = $this->dgTallas($texto)) { $this->dgConTalla($t[0], ''); return; }

        // Dice el peso viendo las fotos: si la talla que está viendo le queda,
        // se le confirma y sigue eligiendo; si no, se busca la que le queda.
        if ($p = Entender::peso($texto)) {
            $lb = ! empty($p['kg']) ? (str_ends_with((string) ($p['dicho'] ?? ''), 'lb') ? (float) $p['dicho'] : round($p['kg'] * 2.2046, 1)) : (float) ($p['valor'] ?? 0);
            $actual = mb_strtoupper((string) (((array) $f->dato('tallas', []))[0] ?? ''));
            if ($lb > 25 || ! empty($p['kg'])) {
                if ($actual !== '' && in_array($actual, $this->dgTallasPorPeso($lb), true)) {
                    $v = rtrim(rtrim(number_format($lb, 1), '0'), '.');
                    $this->dgDecir('Con ' . $v . ' libras le queda bien la talla *' . $this->bonita($actual) . '* 😊', 'opciones');
                    return;
                }
            }
            $this->dgConPeso($p, '');
            return;
        }

        // Pregunta el precio o cuántos trae: el de cada opción, y nada más.
        if ($ops && (Entender::preguntaPrecio($texto) || preg_match('/\b(precio|cuanto (me )?sale|en total|cuantos trae)\b/', $nn))) {
            $l = array_map(fn ($o) => '• *Opción ' . $o['opcion'] . '* (' . trim(preg_replace('/\s*\(.*$/u', '', $o['nombre'])) . '): $'
                . number_format($o['precio'], 2) . ' · ' . $o['unidades'] . ' unidades', $ops);
            $this->dgDecir("Con gusto 😊\n\n" . implode("\n", $l), 'opciones');
            return;
        }

        // "2 paquetes", "quiero uno" y hay varias: ¿de cuál? (el número se guarda)
        if (count($ops) > 1 && ($this->dgCantidadEn($texto) !== null || Entender::quiereComprar($texto)
                || preg_match('/^\s*(quiero|deme|mandeme|me manda|envieme|necesito|ocupo)\b/u', $nn))) {
            if (($c = $this->dgCantidadEn($texto)) !== null) $f->poner('cant_pend', $c);
            $this->dgListaDeOpciones('Con gusto 😊 ¿De cuál le enviamos?', $ops);
            return;
        }

        if ($this->dgSuelto($texto, 'opciones')) return;

        // "Yo quería de los otros", "el otro", "esos no": si de esa talla hay
        // presentaciones que no vio, se le mandan; si ya vio todas, es otro
        // producto (uno que no hay o que llevó antes): a una persona.
        if (preg_match('/\b(l[oa]s otr[oa]s|el otro|la otra|de otr[oa]s?|otro tipo|otra marca|otra presentacion|esos no|esas no|no son esos|no de esos|los de antes|los que (me )?(lleve|compre|mandaron))\b/', $nn)) {
            $this->dgLosOtros();
            return;
        }

        $this->dgNoEntendi('opciones', '¿Cuál de las opciones le interesa? Puede decirme el número 😊');
    }

    /** Pide otra talla después de ver las fotos: se suman sus fotos, numeradas a continuación. */
    private function dgAgregarTallas(array $nuevas): void
    {
        $f = $this->f;
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());
        $hay = array_values(array_filter($nuevas, fn ($t) => in_array(mb_strtoupper($t), $disp, true)));
        $no  = array_values(array_diff($nuevas, $hay));

        $antes = '';
        if ($no) {
            $this->marcarAgotado();
            $antes = 'En este momento no tenemos talla ' . $this->unir(array_map(fn ($t) => $this->bonita($t), $no)) . ' 😔';
        }
        if (! $hay) { $this->dgDecir($antes, 'opciones'); return; }

        $f->poner('tallas', array_values(array_unique(array_merge((array) $f->dato('tallas', []), array_map('mb_strtoupper', $hay)))));
        $enviadas = (array) $f->dato('fotos_enviadas', []);

        foreach ($hay as $t) {
            $primera = true;
            foreach ($this->presentaciones([$t], 'ambos')->take(max(1, (int) config('asistente.max_fotos', 4))) as $s) {
                $nro = count($enviadas) + 1;
                $pre = $primera ? trim($antes . "\n\n*Talla " . $this->bonita($t) . '* 👇') : '';
                $this->tarjeta($s, $nro, $pre);
                $enviadas[] = ['opcion' => $nro, 'id' => (string) $s->id];
                $primera = false; $antes = '';
            }
        }
        $f->poner('fotos_enviadas', $enviadas);

        // La pregunta, solo con lo de la talla nueva.
        $nuevasUp = array_map('mb_strtoupper', $hay);
        $ops = array_values(array_filter($this->guionOpcionesVistas(), function ($o) use ($nuevasUp) {
            $s = ProductSize::with('product')->find($this->dgIdDeOpcion($o['opcion']));
            return $s && in_array(mb_strtoupper(trim((string) $s->size)), $nuevasUp, true);
        }));
        $this->dgPreguntarDespuesDeFotos($ops);
    }

    /** Eligió una presentación: al pedido, y sigue con la otra talla o el municipio. */
    private function dgElegir(ProductSize $s, ?int $cant): void
    {
        $f = $this->f;
        if ($cant && $cant > (int) config('asistente.max_paquetes', 10)) { $this->pasarAWil("pide {$cant} paquetes"); return; }

        $carrito = (array) $f->dato('carrito', []);
        $carrito = array_values(array_filter($carrito, fn ($c) => (string) $c['id'] !== (string) $s->id));
        // Lo dijo antes de elegir ("mándeme 2" y había varias).
        if ($cant === null && ($pend = (int) $f->dato('cant_pend'))) $cant = $pend;
        $f->quitar('cant_pend');
        $carrito[] = ['id' => (string) $s->id, 'cant' => $cant ?: 1, 'dicha' => $cant !== null];
        $f->poner('carrito', $carrito);
        $f->quitar('dtipo');

        $que = '*' . trim((string) $s->product->name) . '* talla ' . $this->bonita(trim((string) $s->size));

        // Quedan tallas por elegir y no dijo cuántos de esta: se pregunta ya,
        // antes de pasar a la otra.
        if ($this->dgTallasSinElegir() && $cant === null) {
            $this->dgDecir('¡Con gusto! 😊 ¿Cuántos paquetes de ' . $que . ' le enviamos?', 'cantidad_item');
            return;
        }

        $this->dgDespuesDeElegir('¡Con gusto! 😊 ' . $que . ".\n\n");
    }

    /** La siguiente talla que pidió, o el municipio si ya no quedan. */
    private function dgDespuesDeElegir(string $antes): void
    {
        if ($faltan = $this->dgTallasSinElegir()) {
            $this->f->poner('dpaso', 'opciones');
            $ops = array_values(array_filter($this->guionOpcionesVistas(), function ($o) use ($faltan) {
                $s = ProductSize::with('product')->find($this->dgIdDeOpcion($o['opcion']));
                return $s && mb_strtoupper(trim((string) $s->size)) === mb_strtoupper($faltan[0]);
            }));

            // Sus fotos ya no están entre las enviadas (se reenviaron solo las
            // de otro tipo): se le vuelven a mandar las de esa talla.
            if (! $ops) {
                if (trim($antes) !== '') $this->texto(trim($antes));
                $antes = '';
                $enviadas = (array) $this->f->dato('fotos_enviadas', []);
                $pre = '*Talla ' . $this->bonita($faltan[0]) . '* 👇';
                foreach ($this->presentaciones([$faltan[0]], 'ambos')->take(max(1, (int) config('asistente.max_fotos', 4))) as $s) {
                    $nro = count($enviadas) + 1;
                    $this->tarjeta($s, $nro, $pre);
                    $pre = '';
                    $enviadas[] = ['opcion' => $nro, 'id' => (string) $s->id];
                }
                $this->f->poner('fotos_enviadas', $enviadas);
                $ops = array_values(array_filter($this->guionOpcionesVistas(), function ($o) use ($faltan) {
                    $s = ProductSize::with('product')->find($this->dgIdDeOpcion($o['opcion']));
                    return $s && mb_strtoupper(trim((string) $s->size)) === mb_strtoupper($faltan[0]);
                }));
                if (! $ops) {   // ya no hay nada de esa talla: se quita y sigue
                    $this->f->poner('tallas', array_values(array_filter((array) $this->f->dato('tallas', []), fn ($t) => mb_strtoupper($t) !== mb_strtoupper($faltan[0]))));
                    $this->dgDespuesDeElegir('');
                    return;
                }
            }
            $this->dgListaDeOpciones(trim($antes . '¿Y de la talla *' . $this->bonita($faltan[0]) . '*, cuál desea? 😊'), $ops);
            return;
        }

        // Ya preguntó el envío a su municipio y se le contestó: a los datos.
        if ($this->f->dato('envio_dicho') && $this->f->dato('municipio') && $this->f->dato('departamento')) {
            $this->dgPedirDatos($antes);
            return;
        }
        // Ya había dicho de dónde es (San Miguel o con el departamento por
        // aclarar): se sigue desde ahí, sin volver a preguntar el municipio.
        if ($this->f->dato('municipio')) {
            if (trim($antes) !== '') $this->texto(trim($antes));
            if (! $this->f->dato('departamento') && $this->f->dato('deptos')) {
                $this->dgDecir('Hay un ' . $this->f->dato('municipio') . ' en varios departamentos. ¿Cuál es el suyo: ' . $this->unir((array) $this->f->dato('deptos', []), ' o ') . '?', 'depto');
                return;
            }
            $this->dgDespuesDelMunicipio();
            return;
        }

        $this->dgDecir($antes . 'Tenemos cobertura nacional con los envíos. ¿Desde cuál municipio nos escribe, disculpe?', 'municipio');
    }

    /** Las tallas que pidió y de las que todavía no eligió nada. */
    private function dgTallasSinElegir(): array
    {
        $elegidas = [];
        foreach ((array) $this->f->dato('carrito', []) as $c) {
            if ($s = ProductSize::with('product')->find($c['id'])) $elegidas[] = mb_strtoupper(trim((string) $s->size));
        }
        return array_values(array_filter((array) $this->f->dato('tallas', []), fn ($t) => ! in_array(mb_strtoupper($t), $elegidas, true)));
    }

    private function dgCantidadItem(string $texto): void
    {
        $cant = Entender::cantidad($texto) ?? (preg_match('/\b(\d{1,2})\b/u', $texto, $m) ? (int) $m[1] : null);
        $sin = $this->dgSinCantidad();
        if (! $cant || ! $sin) { $this->dgNoEntendi('cantidad_item', '¿Cuántos paquetes le enviamos?'); return; }
        if ($cant > (int) config('asistente.max_paquetes', 10)) { $this->pasarAWil("pide {$cant} paquetes"); return; }

        $this->dgPonerCantidad($sin['i'], $cant);
        $this->dgDespuesDeElegir('');
    }

    /** El último producto del pedido al que todavía no se le dijo cuántos. */
    private function dgSinCantidad(): ?array
    {
        $carrito = (array) $this->f->dato('carrito', []);
        for ($i = count($carrito) - 1; $i >= 0; $i--) {
            if (empty($carrito[$i]['dicha'])) {
                $s = ProductSize::with('product')->find($carrito[$i]['id']);
                return ['i' => $i, 'que' => $s ? trim((string) $s->product->name) . ' talla ' . trim((string) $s->size) : 'ese producto'];
            }
        }
        return null;
    }

    private function dgPonerCantidad(int $i, int $cant): void
    {
        $carrito = (array) $this->f->dato('carrito', []);
        if (! isset($carrito[$i])) return;
        $carrito[$i]['cant'] = $cant;
        $carrito[$i]['dicha'] = true;
        $this->f->poner('carrito', $carrito);
    }

    /** Reenvía solo las fotos de lo que pidió ("calzoncito"), numeradas de nuevo. */
    /**
     * Las tallas pedidas en las que no queda nada de lo que dijo ("cinta"):
     * "En talla *M* no tenemos de cinta 😔, solo de calzoncito." Vacío si en
     * todas hay.
     */
    private function dgTallasSinEseTipo(array $ops, array $sirven, string $que): string
    {
        $talla = function ($o) {
            $s = ProductSize::with('product')->find($this->dgIdDeOpcion($o['opcion']));
            return $s ? mb_strtoupper(trim((string) $s->size)) : '';
        };
        $porTalla = [];
        foreach ($ops as $o) if (($t = $talla($o)) !== '') $porTalla[$t][] = $o;
        $conAlgo = array_unique(array_filter(array_map($talla, $sirven)));

        $lineas = [];
        foreach ($porTalla as $t => $suyas) {
            if (in_array($t, $conAlgo, true)) continue;
            $tipos = array_values(array_unique(array_map(fn ($o) => mb_strtolower($o['tipo']), $suyas)));
            $solo = (str_starts_with($que, 'de cinta') || str_starts_with($que, 'de calzoncito')) && count($tipos) === 1
                ? ', solo de ' . $tipos[0] : '';
            $lineas[] = 'En talla *' . $this->bonita($t) . '* en este momento no tenemos ' . $que . ' 😔' . ($solo ? $solo . '.' : '');
        }
        return implode("\n", $lineas);
    }

    private function dgReenviar(array $sirven, string $antes = ''): void
    {
        $ids = [];
        foreach ($sirven as $o) if ($id = $this->dgIdDeOpcion($o['opcion'])) $ids[] = $id;

        $enviadas = [];
        foreach ($ids as $id) {
            $s = ProductSize::with('product')->find($id);
            if (! $s) continue;
            $nro = count($enviadas) + 1;
            $this->tarjeta($s, $nro, $antes);
            $antes = '';
            $enviadas[] = ['opcion' => $nro, 'id' => (string) $s->id];
        }
        $this->f->poner('fotos_enviadas', $enviadas);

        $this->dgPreguntarDespuesDeFotos($this->guionOpcionesVistas());
    }

    private function dgIdDeOpcion(int $nro): ?string
    {
        foreach ((array) $this->f->dato('fotos_enviadas', []) as $e) {
            if ((int) $e['opcion'] === $nro) return (string) $e['id'];
        }
        return null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // 3 · Municipio y envío
    // ════════════════════════════════════════════════════════════════════════

    private function dgMunicipio(string $texto): void
    {
        $f = $this->f;
        $m = Municipios::buscarEn($texto) ?: Municipios::aproximado($texto);

        if (! $m) {
            if ((Entender::esCortesia($texto) || Entender::siNo($texto) === true) && ! $f->dato('muni_repetido')) {
                $f->poner('muni_repetido', true);
                $this->dgDecir('¿Desde cuál municipio nos escribe, disculpe? 😊', 'municipio');
                return;
            }
            $this->dgNoEntendi('municipio', 'No encontré ese municipio 🙏 ¿Me lo escribe de nuevo? Por ejemplo: *Soyapango*, *Santa Tecla*');
            return;
        }

        $this->ponerMunicipio($m);
        if (! $f->dato('departamento')) {
            $this->dgDecir('Hay un ' . $f->dato('municipio') . ' en varios departamentos. ¿Cuál es el suyo: ' . $this->unir((array) $f->dato('deptos', []), ' o ') . '?', 'depto');
            return;
        }
        $this->dgDespuesDelMunicipio();
    }

    /** Contestó de dónde escribe, después de preguntar el envío: se guarda y se sigue donde iba. */
    private function dgMuniEnvio(string $texto): void
    {
        $f = $this->f;
        $volver = (string) ($f->dato('volver_a') ?: 'talla');
        $m = Municipios::buscarEn($texto) ?: Municipios::aproximado($texto);

        if (! $m || ! $this->ponerMunicipio($m)) {
            // En vez del municipio dijo la talla o el peso: se sigue con eso.
            if ($volver === 'talla' && ($this->dgTallas($texto) || Entender::peso($texto))) {
                $f->quitar('volver_a', 'pend_texto');
                $this->dgTalla($texto, '');
                return;
            }
            $this->dgNoEntendi('muni_envio', 'No encontré ese municipio 🙏 ¿Me lo escribe de nuevo? Por ejemplo: *Soyapango*, *Santa Tecla*');
            return;
        }

        if ($this->entregaPropia()) {
            $f->quitar('volver_a', 'pend_texto');
            $this->dgDecir('¿De qué colonia nos escribe, para confirmarle el costo del envío? 🚚', 'colonia');
            return;
        }

        $f->poner('envio', (float) \App\Models\Setting::envio());
        $f->poner('envio_dicho', true);
        $pend = (string) $f->dato('pend_texto', '');
        $f->quitar('volver_a', 'pend_texto');
        $f->poner('dpaso', $volver);

        // Había preguntado cuánto sería con el envío: ahora sí, el total.
        if ($f->dato('pend_total')) {
            $f->quitar('pend_total');
            if ($this->dgTotal($volver, '¡Gracias! 😊 ')) return;
        }

        // Ya dijo de dónde es: la leyenda del envío, y se espera lo que conteste.
        $leyenda = '';
        foreach ((array) config('asistente.respuestas', []) as $r) {
            if (str_contains((string) ($r['texto'] ?? ''), '{envio}')) { $leyenda = $this->rellenar((string) $r['texto']); break; }
        }
        if ($leyenda === '') $leyenda = '🚚 Envío: $' . number_format((float) $f->dato('envio'), 2) . ', hasta su casa o lugar de trabajo.';
        $this->dgDecir($leyenda, $volver);

        // Si en el mismo mensaje del envío había pedido una talla, sus fotos.
        if ($volver === 'talla' && ($this->dgTallas($pend) || Entender::peso($pend))) $this->dgTalla($pend, '');
    }

    private function dgDepto(string $texto): void
    {
        foreach ((array) $this->f->dato('deptos', []) as $d) {
            if (str_contains(Municipios::normalizar($texto), Municipios::normalizar($d))) {
                $this->ponerDepartamento($d);
                $this->dgDespuesDelMunicipio();
                return;
            }
        }
        $this->dgNoEntendi('depto', '¿De qué departamento: ' . $this->unir((array) $this->f->dato('deptos', []), ' o ') . '?');
    }

    private function dgDespuesDelMunicipio(): void
    {
        if ($this->entregaPropia()) {
            $this->dgDecir('¿De qué colonia nos escribe, para confirmarle el costo del envío? 🚚', 'colonia');
            return;
        }

        $envio = (float) \App\Models\Setting::envio();
        $this->f->poner('envio', $envio);

        // Tu leyenda del envío, la de config/asistente.php ("¡El envío más
        // barato del mercado!…"), con el monto de Configuración.
        $leyenda = '';
        foreach ((array) config('asistente.respuestas', []) as $r) {
            if (str_contains((string) ($r['texto'] ?? ''), '{envio}')) { $leyenda = $this->rellenar((string) $r['texto']); break; }
        }
        if ($leyenda === '') $leyenda = '🚚 Envío: $' . number_format($envio, 2) . ', hasta su casa o lugar de trabajo.';

        $this->dgDecir($leyenda . "\n\n¿Le parece bien? 😊", 'envio');
    }

    private function dgColonia(string $texto): void
    {
        if (mb_strlen(trim($texto)) < 3 || Entender::esCortesia($texto)) return;
        $this->f->poner('colonia', mb_substr(trim($texto), 0, 200));
        $this->despedidaWil = trim((string) config('asistente.entrega_propia.cierre'));
        $this->pasarAWil('envío en ' . $this->f->dato('municipio') . ': ' . mb_substr(trim($texto), 0, 120));
    }

    private function dgEnvio(string $texto): void
    {
        $si = Entender::siNo($texto);

        if ($si === false) {
            // NO → no hacer nada. Se apaga en este chat, sin decirle nada.
            $this->pasarAWil('no aceptó el envío', false);
            return;
        }

        if ($si === true) {
            $this->dgPedirDatos('¡Excelente! 😊 ');
            return;
        }

        $this->dgNoEntendi('envio', '¿Le parece bien el envío? 😊');
    }

    // ════════════════════════════════════════════════════════════════════════
    // 4 · Cantidad, nombre y dirección → la orden
    // ════════════════════════════════════════════════════════════════════════

    private function dgPedirDatos(string $antes): void
    {
        $f = $this->f;

        // Cliente conocido: se le confirman los datos de la vez pasada en vez
        // de pedírselos todos ("¿Se lo enviamos a nombre de…, en…?").
        $c = (array) $f->dato('conocido', []);
        if ($c && ! $f->dato('nombre') && ! $f->dato('direccion') && ! $f->dato('conocido_respondido')) {
            $donde = trim($c['direccion'] . ($c['municipio'] && ! str_contains(Entender::normalizar($c['direccion']), Entender::normalizar($c['municipio'])) ? ', ' . $c['municipio'] : ''));
            $conf = 'se lo enviamos a nombre de *' . $c['nombre'] . '*, en *' . $donde . '*';
            $msg = ($sin = $this->dgSinCantidad())
                ? '¿Cuántos paquetes le enviamos? Y ' . $conf . ', ¿verdad? 😊'
                : '¿' . mb_strtoupper(mb_substr($conf, 0, 1)) . mb_substr($conf, 1) . '? 😊';
            $this->dgDecir($antes . $msg, 'datos_conf');
            return;
        }

        $falta = [];
        if ($sin = $this->dgSinCantidad()) $falta[] = '• ¿Cuántos paquetes' . (count((array) $f->dato('carrito', [])) > 1 ? ' de *' . $sin['que'] . '*' : '') . ' le enviamos?';
        if (! $f->dato('nombre'))         $falta[] = '• Nombre completo';
        if (! $f->dato('direccion'))      $falta[] = '• Dirección exacta (colonia, calle o pasaje, número de casa y un punto de referencia)';

        if (! $falta) { $this->dgDespuesDeDatos(); return; }

        // Solo falta cuántos: la pregunta sola, sin lista.
        if (count($falta) === 1 && $this->dgSinCantidad()) {
            $this->dgDecir($antes . '¿Cuántos paquetes le enviamos? 😊', 'datos');
            return;
        }

        $this->dgDecir($antes . "Para enviarlo me regala por favor:\n\n" . implode("\n", $falta), 'datos');
    }

    private function dgDatos(string $texto): void
    {
        $f = $this->f;
        $leyo = false;

        // Cuántos: "2", "2 paquetes", o un número al principio ("2, Ana Pérez…").
        $cant = Entender::cantidad($texto) ?? (preg_match('/^\s*(\d{1,2})\b/u', $texto, $m) ? (int) $m[1] : null);
        if ($cant && ($sin = $this->dgSinCantidad())) {
            if ($cant > (int) config('asistente.max_paquetes', 10)) { $this->pasarAWil("pide {$cant} paquetes"); return; }
            $this->dgPonerCantidad($sin['i'], $cant);
            $leyo = true;
        }

        // Nombre y dirección: "Ana Pérez, Col. Escalón pasaje 2 casa 5" o en renglones.
        $sinNumero = trim(preg_replace('/^\s*\d{1,2}\s*(paquetes?)?\s*[,.\-]?\s*/u', '', $texto));
        foreach (preg_split('/\n+/', $sinNumero) as $renglon) {
            $partes = array_map('trim', explode(',', $renglon, 2));
            $nom = Entender::pareceNombre($partes[0]);
            // "Quiero", "Sí", "Gracias", "2 paquetes"… no son nombres.
            if ($nom && preg_match('/\b(quiero|quisiera|deme|mandeme|envieme|si|no|ok|gracias|hola|buenas|buenos|dias|tardes|noches|paquetes?|bolsas?|talla|calzoncitos?|cinta|panales|pampers?|por favor|porfa|listo|bueno|claro|perfecto|seria|son|es|para|el|la|los)\b/', Entender::normalizar($nom))
                && count(preg_split('/\s+/', trim($nom))) < 2) $nom = null;
            if ($nom && preg_match('/^(quiero|quisiera|deme|mandeme|envieme|gracias|hola|buenas|buenos|si |ok )/', Entender::normalizar($nom))) $nom = null;
            if (! $f->dato('nombre') && $nom) {
                $f->poner('nombre', $nom);
                $leyo = true;
                if (count($partes) === 2 && ! $f->dato('direccion') && Entender::pareceDireccion($partes[1])) {
                    $f->poner('direccion', $partes[1]);
                }
            } elseif (! $f->dato('direccion') && Entender::pareceDireccion($renglon)) {
                $f->poner('direccion', trim($renglon));
                $leyo = true;
            }
        }

        // Si las reglas no alcanzaron, la IA solo para separar nombre y dirección.
        if (! $f->dato('nombre') || ! $f->dato('direccion')) {
            try {
                $r = Vendedora::leer([
                    'catalogo' => [], 'datos' => [], 'tallas' => [], 'estado' => ['ultima_pregunta' => 'Nombre completo y dirección exacta'],
                    'charla' => [], 'nuevo' => $texto, 'ejemplos' => [], 'permitidos' => [],
                ]);
                if ($r) {
                    if (! $f->dato('nombre') && $r['nombre'])       { $f->poner('nombre', $r['nombre']); $leyo = true; }
                    if (! $f->dato('direccion') && $r['direccion']) { $f->poner('direccion', $r['direccion']); $leyo = true; }
                }
            } catch (\Throwable $e) {
            }
        }

        if (! $leyo) {
            if (Entender::esCortesia($texto)) return;
            $this->dgNoEntendi('datos', "Para enviarlo me regala por favor su nombre completo y la dirección exacta 🙏");
            return;
        }

        // La dirección nueva dice otro municipio (lo manda a otra casa): ese
        // manda, pero solo si el municipio que había era el de la vez pasada
        // (no el que dijo hoy) y no es el nombre de un barrio o colonia
        // ("Barrio Las Flores" no es el municipio Las Flores).
        if (($dir = (string) $f->dato('direccion')) !== '' && ($m = Municipios::buscarEn($dir))
            && Municipios::normalizar($m) !== Municipios::normalizar((string) $f->dato('municipio'))
            && (! $f->dato('municipio') || ($f->dato('conocido.municipio')
                && Municipios::normalizar((string) $f->dato('municipio')) === Municipios::normalizar((string) $f->dato('conocido.municipio'))))
            && ! preg_match('/\b(barrio|bo|colonia|col|residencial|res|reparto|urbanizacion|urb|lotificacion|lot|canton|caserio|comunidad|calle|pasaje|pje|avenida|av|hacienda|finca)\.?\s+(de\s+|la\s+|las\s+|el\s+|los\s+)*' . preg_quote(Municipios::normalizar($m), '/') . '\b/', Municipios::normalizar($dir))) {
            $this->ponerMunicipio($m);
            if ($this->entregaPropia()) {
                $this->dgDecir('¿De qué colonia es, para confirmarle el costo del envío? 🚚', 'colonia');
                return;
            }
            if (! $f->dato('departamento') && $f->dato('deptos')) {
                $this->pasarAWil('dirección nueva: falta aclarar el departamento de ' . $m, false);
                return;
            }
        }

        $this->dgPedirDatos('');
    }

    private function dgDespuesDeDatos(): void
    {
        $f = $this->f;

        if (! $f->dato('telefono')) {
            if ($this->conv->esExtranjero()) {
                $this->dgDecir('¿A qué número de El Salvador le llamamos a quien recibe, para coordinar la entrega? 📞', 'telefono');
                return;
            }
            $f->poner('telefono', $this->conv->telefonoLegible());
        }

        $nombre = trim(strtok((string) $f->dato('nombre'), ' ') ?: '');
        $this->f->poner('orden_mostrada', true);
        $this->f->poner('dpaso', 'orden');
        $this->f->paso = 'diagrama · orden';

        $entrega = $this->entregaPropia() ? '' : $this->dgTextoEntrega();
        $this->texto('¡Gracias' . ($nombre !== '' ? ', ' . $nombre : '') . "! 😊\n\n" . $this->textoOrden()
            . ($entrega !== '' ? "\n\n" . $entrega : '')
            . "\n\n¿Toda la información es correcta? Así evitamos errores 🙏");
    }

    private function dgTelefono(string $texto): void
    {
        $tel = Entender::telefonoSV($texto);
        if (! $tel) { $this->dgNoEntendi('telefono', '¿A qué número de El Salvador le llamamos? (8 dígitos) 📞'); return; }
        $this->f->poner('telefono', $tel);
        $this->dgDespuesDeDatos();
    }

    private function dgOrden(string $texto, ?string $boton): void
    {
        if ($boton === 'conf:si' || ($boton === null && Entender::siNo($texto) === true)) {
            $this->f->poner('confirmado', true);
            $this->terminar();
            return;
        }

        if ($boton === 'conf:corregir' || Entender::siNo($texto) === false || preg_match('/\b(corregir|cambiar|esta mal|no es|equivocad)/', Entender::normalizar($texto))) {
            $this->despedidaWil = 'Con gusto 😊 En un momento le ayudamos a corregirlo.';
            $this->pasarAWil('quiere corregir la orden');
            return;
        }

        // Corrige el municipio ("San José la Fuente, La Unión") o la dirección
        // después de ver la orden: se cambia y se le vuelve a mostrar.
        if (($m = Municipios::buscarEn($texto)) && $this->ponerMunicipio($m)) {
            if ($this->entregaPropia()) {
                $this->dgDecir('¿De qué colonia es, para confirmarle el costo del envío? 🚚', 'colonia');
                return;
            }
            if (! $this->f->dato('departamento') && $this->f->dato('deptos')) {
                $this->dgDecir('Hay un ' . $this->f->dato('municipio') . ' en varios departamentos. ¿Cuál es el suyo: ' . $this->unir((array) $this->f->dato('deptos', []), ' o ') . '?', 'depto');
                return;
            }
            // Si además mandó una dirección más completa, también.
            if (mb_strlen(trim($texto)) > mb_strlen($m) + 12 && Entender::pareceDireccion($texto)) $this->f->poner('direccion', trim($texto));
            $this->f->poner('envio', (float) \App\Models\Setting::envio());
            $this->dgDespuesDeDatos();
            return;
        }
        if (Entender::pareceDireccion($texto) && mb_strlen(trim($texto)) >= 15) {
            $this->f->poner('direccion', trim($texto));
            $this->dgDespuesDeDatos();
            return;
        }

        if (Entender::esCortesia($texto)) return;
        $this->dgNoEntendi('orden', '¿Toda la información de la orden es correcta? 🙏');
    }
}

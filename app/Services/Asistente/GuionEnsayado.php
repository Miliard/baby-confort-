<?php

namespace App\Services\Asistente;

use App\Models\ProductSize;
use App\Models\WaMensaje;
use App\Services\Municipios;
use Illuminate\Support\Facades\Log;

/**
 * El guion que ensayamos (modo "ejemplos").
 *
 *   1. Talla       · por talla, por peso (recomienda) o por edad (orienta y pide el peso)
 *   2. Producto    · fotos de esa talla; "¿cuál le gusta y cuántos paquetes?"
 *   3. Municipio   · "📦 ¡Tenemos cobertura nacional…! ¿Desde qué municipio nos escribe?"
 *   4. Total       · envío + total; "¿Le parece bien?"
 *   5. Datos       · nombre completo y dirección exacta (el teléfono es el del chat)
 *   6. Orden       · la de siempre + "¿Me podría revisar la orden de envío…?"
 *   7. Confirma    · "¡Gracias por su compra!" y pasa a Pedidos
 *
 * Todo lo que se sabe queda en la ficha y NUNCA se vuelve a preguntar. La IA
 * solo entiende lo que escribió la clienta y contesta las preguntas sueltas
 * con tu tono (mirando el banco de ejemplos); los mensajes del pedido, los
 * precios, el envío y la orden los escribe el sistema.
 */
trait GuionEnsayado
{
    /** Lo que se le va a decir antes de la pregunta del paso (respuestas, avisos). */
    private array $gAntes = [];

    /** Si en este turno cambió el carrito (para decir "2 paquetes de…"). */
    private bool $gCarritoNuevo = false;

    /** Si en este turno aceptó el envío (para decir "¡Excelente!"). */
    private bool $gAcepto = false;

    // ════════════════════════════════════════════════════════════════════════
    // Entrada
    // ════════════════════════════════════════════════════════════════════════

    private function correrConEjemplos($mensajes, array $turnos): void
    {
        $textos = [];
        $boton  = null;
        $desde  = null;

        foreach ($mensajes as $m) {
            $desde ??= (int) $m->id;
            $this->f->ultimo_mensaje_id = $m->id;

            $t = $this->textoParaEjemplos($m);
            if ($this->paraWil) break;

            if ($b = $this->botones[$m->id] ?? null) $boton = $b;
            if ($t !== null && $t !== '') $textos[] = $t;

            // Le respondió a una de las fotos: se sabe de qué producto habla.
            if ($m->responde_a && ($sid = ((array) $this->f->dato('tarjetas', []))[$m->responde_a] ?? null)) {
                $this->f->poner('citada', (string) $sid);
            }
        }

        if (! $this->paraWil && count($turnos) >= (int) config('asistente.max_turnos_hora', 40)) {
            $this->paraWil = 'demasiados mensajes seguidos';
        }

        if ($this->paraWil) {
            $this->pasarAWil($this->paraWil);
            return;
        }

        if (! $textos && ! $boton) {
            $this->f->save();
            return;
        }

        $primero = ! $this->f->dato('turnos');
        $turnos[] = now()->timestamp;
        $this->f->poner('turnos', $turnos);

        $this->guion(implode("\n", $textos), (int) $desde, $boton, $primero);

        $this->f->save();

        // Una vez por semana el banco de ejemplos se rearma con los chats
        // nuevos. Ya se le contestó a la clienta: no la hace esperar.
        Ejemplos::alDia();
    }

    /** Lo que dijo en ese mensaje, como texto. null si no dice nada. */
    private function textoParaEjemplos(WaMensaje $m): ?string
    {
        $tipo  = (string) $m->tipo;
        $texto = trim((string) $m->texto);

        // El clic en un anuncio llega como "unsupported": es un saludo.
        if (($tipo === 'unsupported' || $texto === '[unsupported]') && ! $this->f->dato('turnos')) {
            return 'Hola';
        }

        if (in_array($tipo, ['reaction', 'sticker', 'unsupported', 'system', 'ephemeral', 'edit'], true)
            || preg_match('/^\[(reaction|sticker|unsupported|system|ephemeral|edit)\]$/', $texto)) {
            return null;
        }

        if (in_array($tipo, ['image', 'video', 'document', 'location', 'contacts'], true)
            || preg_match('/^\[(location|contacts|order|image|video|document)\]$/', $texto)) {
            $this->paraWil = 'mandó ' . ([
                'image' => 'una foto', 'video' => 'un video', 'document' => 'un documento',
                'location' => 'una ubicación', 'contacts' => 'un contacto',
            ][$tipo] ?? 'algo que no es texto');
            return null;
        }

        if (in_array($tipo, ['audio', 'voice'], true) && ($texto === '' || str_starts_with($texto, '['))) {
            $this->paraWil = 'mandó un audio que no se pudo leer';
            return null;
        }

        if ($texto === '') return null;

        if ($motivo = Entender::motivoParaWil($texto)) {
            $this->paraWil = $motivo;
            return null;
        }

        return $texto;
    }

    // ════════════════════════════════════════════════════════════════════════
    // Un turno
    // ════════════════════════════════════════════════════════════════════════

    private function guion(string $texto, int $desdeId, ?string $boton, bool $primero): void
    {
        $f = $this->f;
        $ultima = (string) $f->dato('ultima');

        // ── Botones de la orden ──────────────────────────────────────────────
        if ($boton === 'conf:si' && $f->dato('orden_mostrada')) {
            $f->poner('confirmado', true);
            $this->terminar();
            return;
        }
        if ($boton === 'conf:corregir') {
            $f->quitar('orden_mostrada');
            $f->poner('ultima', 'corregir');
            $this->texto('Con gusto 😊 ¿Qué dato corrijo: el producto, el municipio, el nombre, la dirección o el teléfono?');
            return;
        }

        // ── Qué dijo ─────────────────────────────────────────────────────────
        $r = $this->guionEntender($texto, $desdeId);

        if ($r['pasar_a_wil']) {
            $this->pasarAWil($r['motivo'] ?: 'la IA lo pasó');
            return;
        }

        $entendio = false;

        // Una pregunta suelta: tu respuesta de config si la hay; si no, la de
        // la IA (ya revisada).
        $faq = Entender::respuesta($texto, (array) config('asistente.respuestas', []));
        if ($faq !== null && str_contains($faq, '{envio}')) {
            $r['pregunta_envio'] = true;
            $faq = null;
        }
        if ($faq !== null) {
            $this->gAntes[] = $this->rellenar($faq);
            $entendio = true;
            if (str_contains($faq, 'Apenas entren')) $this->marcarAgotado();
        } elseif ($r['respuesta']) {
            $this->gAntes[] = $r['respuesta'];
            $entendio = true;
        }

        // ── La orden ya se mostró: "sí" la confirma ─────────────────────────
        $cambiaAlgo = $r['items'] || $r['cantidad'] || $r['quitar'] || $r['municipio'] || $r['nombre'] || $r['direccion'] || $r['telefono'] || $r['tallas'];
        if ($f->dato('orden_mostrada') && $r['acepta'] === true && ! $cambiaAlgo) {
            $f->poner('confirmado', true);
            $this->terminar();
            return;
        }
        if ($r['corregir'] && ! $cambiaAlgo) {
            $f->quitar('orden_mostrada');
            $f->poner('ultima', 'corregir');
            $this->texto('Con gusto 😊 ¿Qué dato corrijo: el producto, el municipio, el nombre, la dirección o el teléfono?');
            return;
        }

        // ── Se le ofreció explicar la diferencia ────────────────────────────
        // "Sí" → se le explica. "No, gracias" → se le dice que aquí estamos.
        // Nunca se le insiste en que elija.
        $ofrecio   = $ultima === 'producto' && $f->dato('ofrecio_diferencia');
        $pideDif   = Entender::tipo($texto) === 'diferencia';
        $eligeAlgo = $r['items'] || $r['cantidad'] || $r['tallas'] || $r['municipio'] || $r['peso'];

        if (! $eligeAlgo && ($pideDif || ($ofrecio && $r['acepta'] === true))) {
            if ($txt = $this->guionDiferencia()) {
                $f->quitar('ofrecio_diferencia');
                $this->gAntes = [];   // la explicación ya contesta; sin repetir
                $this->guionDecir($txt . "\n\nCualquier duda, aquí estoy 😊", 'producto');
                return;
            }
        }

        if (! $eligeAlgo && $ofrecio && $r['acepta'] === false) {
            $f->quitar('ofrecio_diferencia');
            $this->guionDecir('Con gusto 😊 Cualquier cosa, aquí estoy.', 'producto');
            return;
        }

        // ── Lo que aplica según lo último que se le preguntó ───────────────
        if ($ultima === 'muni_duda' && $f->dato('muni_duda')) {
            $duda = (string) $f->dato('muni_duda');
            $f->quitar('muni_duda');

            if ($r['municipio'] && Municipios::normalizar((string) (Municipios::buscarEn($r['municipio']) ?: $r['municipio'])) === Municipios::normalizar((string) $f->dato('municipio'))) {
                $this->gAntes[] = '¡No se preocupe! 😊 Queda para *' . $f->dato('municipio') . '*.';
                $r['municipio'] = null;
            } elseif ($r['acepta'] === true) {
                $r['municipio'] = $duda;
            } elseif ($r['acepta'] === false) {
                $this->gAntes[] = '¡Perfecto! 😊 Queda para *' . $f->dato('municipio') . '*.';
            }
            $entendio = true;
        }

        if ($ultima === 'muni_ops' && ! $r['municipio']) {
            $ops = (array) $f->dato('muni_ops', []);
            foreach ($ops as $op) {
                if (str_contains(Municipios::normalizar($texto), Municipios::normalizar($op))) $r['municipio'] = $op;
            }
            if (! $r['municipio'] && count($ops) === 1 && $r['acepta'] === true) $r['municipio'] = $ops[0];
        }

        if ($ultima === 'depto' && $f->dato('deptos')) {
            foreach ((array) $f->dato('deptos') as $d) {
                if (str_contains(Municipios::normalizar($texto), Municipios::normalizar($d))) {
                    $this->ponerDepartamento($d);
                    $entendio = true;
                }
            }
        }

        if ($ultima === 'colonia' && $this->entregaPropia() && ! $r['municipio']) {
            if (mb_strlen(trim($texto)) >= 3 && ! Entender::esCortesia($texto)) {
                $f->poner('colonia', mb_substr(trim($texto), 0, 200));
                $this->despedidaWil = trim((string) config('asistente.entrega_propia.cierre'));
                $this->pasarAWil('envío en ' . $f->dato('municipio') . ': ' . mb_substr(trim($texto), 0, 120));
                return;
            }
        }

        if ($ultima === 'total' && $r['acepta'] !== null && ! $cambiaAlgo) {
            if ($r['acepta'] === false) {
                $this->pasarAWil('no le pareció el total o el envío');
                return;
            }
            $f->poner('envio_ok', true);
            $this->gAcepto = true;
            $entendio = true;
        }

        // ── Peso, edad, talla, tipo ─────────────────────────────────────────
        if ($r['peso']) {
            $entendio = true;
            $u = $r['peso']['unidad'];
            $v = $r['peso']['valor'];
            if ($u === null && $v > 25) $u = 'lb';
            if ($u === null) {
                $f->poner('peso_pend', $v);
            } else {
                $f->poner('peso_lb', round($u === 'kg' ? $v * 2.2046 : $v, 1));
                $f->quitar('peso_pend');
                if (! $r['tallas']) $f->quitar('tallas', 'sugeridas');
            }
        } elseif ($f->dato('peso_pend') && ($u = Entender::unidad($texto))) {
            $v = (float) $f->dato('peso_pend');
            $f->poner('peso_lb', round($u === 'kg' ? $v * 2.2046 : $v, 1));
            $f->quitar('peso_pend', 'tallas', 'sugeridas');
            $entendio = true;
        }

        if ($r['edad_meses'] !== null) {
            $f->poner('edad_meses', $r['edad_meses']);
            $entendio = true;
        }

        if ($r['tallas']) {
            $nuevas = $r['tallas'];
            if ($nuevas !== (array) $f->dato('tallas', [])) {
                $f->poner('tallas', $nuevas);
                $f->quitar('elegido', 'sugeridas');
                $this->guionAclararAlias($texto, $nuevas);
            }
            $entendio = true;
        }

        if ($r['tipo']) {
            $f->poner('tipo', $r['tipo']);
            $entendio = true;
        }

        // ── Productos ───────────────────────────────────────────────────────
        // Citó una foto ("este", "de este 2"): ese es el producto.
        if (($citada = $f->dato('citada')) && ! $r['items']) {
            $corto = count(explode(' ', Entender::normalizar($texto))) <= 6;
            if ($r['cantidad'] || Entender::quiereComprar($texto) || $corto) {
                $r['items'] = [['id' => (string) $citada, 'cantidad' => $r['cantidad']]];
            }
        }
        $f->quitar('citada');

        $carrito = (array) $f->dato('carrito', []);
        $antesCarrito = $carrito;

        if ($r['reemplazar'] && $r['items']) $carrito = [];

        foreach ($r['quitar'] as $id) {
            $carrito = array_values(array_filter($carrito, fn ($c) => (string) $c['id'] !== $id));
        }

        foreach ($r['items'] as $it) {
            $cant = $it['cantidad'] ?? null;
            if ($cant === null && $r['cantidad'] && count($r['items']) === 1) $cant = $r['cantidad'];

            if ($cant) {
                $carrito = $this->guionSumar($carrito, $it['id'], $cant);
                $f->quitar('elegido');
            } else {
                $f->poner('elegido', $it['id']);
            }

            // La talla del producto que eligió queda como la talla que mira.
            if ($s = ProductSize::with('product')->find($it['id'])) {
                $t = mb_strtoupper(trim((string) $s->size));
                if (! in_array($t, (array) $f->dato('tallas', []), true)) $f->poner('tallas', [$t]);
            }
            $entendio = true;
        }

        if (! $r['items'] && $r['cantidad']) {
            if ($f->dato('elegido')) {
                $carrito = $this->guionSumar($carrito, (string) $f->dato('elegido'), $r['cantidad']);
                $f->quitar('elegido');
                $entendio = true;
            } elseif (count($carrito) === 1 && in_array($ultima, ['total', 'cantidad'], true)) {
                $carrito[0]['cant'] = min((int) config('asistente.max_paquetes', 10), $r['cantidad']);
                $entendio = true;
            }
        }

        if ($carrito !== $antesCarrito) {
            $f->poner('carrito', array_values($carrito));
            $f->quitar('envio_ok', 'orden_mostrada', 'envio');
            $this->gCarritoNuevo = true;
        }

        // ── Municipio ───────────────────────────────────────────────────────
        // El lugar que nombra dentro de la dirección no cambia el municipio
        // solo: eso se pregunta (más abajo).
        if ($r['municipio'] && $r['direccion'] && $f->dato('municipio')) $r['municipio'] = null;

        // Preguntó por el envío: solo cuenta como municipio si es uno de verdad
        // ("¿cuánto a Soyapango?"); si no, se le pregunta de dónde es.
        if ($r['municipio'] && $r['pregunta_envio'] && ! Municipios::buscarEn($r['municipio']) && ! Municipios::existe($r['municipio'])) {
            $r['municipio'] = null;
        }

        if ($r['municipio']) {
            $entendio = true;
            $this->guionPonerMunicipio($r['municipio']);

            if ($this->paraWil) {
                $this->pasarAWil($this->paraWil);
                return;
            }
        }

        if ($r['pregunta_envio']) {
            $entendio = true;
            if (! $f->dato('municipio')) {
                $f->poner('envio_primero', true);
                // Un intento fallido anterior no cuenta: esto era una pregunta.
                $f->quitar('muni_ops', 'muni_escrito', 'muni_fallos');
            }
        }

        // ── Datos de envío ──────────────────────────────────────────────────
        if ($r['nombre']) {
            $f->poner('nombre', $r['nombre']);
            $f->quitar('orden_mostrada');
            $entendio = true;
        }

        if ($r['direccion']) {
            $f->poner('direccion', $r['direccion']);
            $f->quitar('orden_mostrada');
            $entendio = true;

            // ¿La dirección nombra otro municipio? Se pregunta, no se cambia solo.
            $otro = $this->guionMunicipioEn($r['direccion']);
            $mun  = (string) $f->dato('municipio');
            if ($otro && $mun !== '' && Municipios::normalizar($otro) !== Municipios::normalizar($mun)
                && Municipios::normalizar($otro) !== Municipios::normalizar((string) $f->dato('departamento'))) {
                $f->poner('muni_duda', Municipios::nombreBueno($otro) ?: $otro);
            }
        }

        if ($r['telefono']) {
            $tel = in_array(Entender::normalizar($r['telefono']), ['este', 'este mismo', 'el mismo', 'si'], true)
                ? ($this->conv->esExtranjero() ? null : $this->conv->telefonoLegible())
                : Entender::telefonoSV($r['telefono']);
            if ($tel) {
                $f->poner('telefono', $tel);
                $f->quitar('orden_mostrada');
                $entendio = true;
            }
        }

        // ── Nada que entender ───────────────────────────────────────────────
        if (! $entendio) {
            if (Entender::esCortesia($texto)) { $f->save(); return; }

            if (! $primero && ! Entender::esSaludo($texto)) {
                $fallos = (array) $f->dato('fallos', []);
                $fallos[$ultima] = ($fallos[$ultima] ?? 0) + 1;
                $f->poner('fallos', $fallos);

                if ($fallos[$ultima] >= 2) {
                    $this->pasarAWil('no le entendí (' . ($ultima ?: 'inicio') . ')');
                    return;
                }
                $this->gAntes[] = 'Disculpe, no le entendí 🙏';
            }
        } else {
            $f->poner('fallos', []);
        }

        $this->guionSiguiente($texto, $primero);
    }

    // ════════════════════════════════════════════════════════════════════════
    // Qué se dice ahora
    // ════════════════════════════════════════════════════════════════════════

    private function guionSiguiente(string $texto, bool $primero): void
    {
        $f = $this->f;
        $hola = $primero ? '¡Hola! 😊 ' : '';

        // Una duda de municipio por la dirección.
        if ($f->dato('muni_duda')) {
            $duda = (string) $f->dato('muni_duda');
            $deps = Municipios::departamentosDe($duda);
            $msg = 'Gracias 😊 Solo para confirmar: la dirección dice *' . $duda . '*, pero antes me dijo *' . $f->dato('municipio') . '*. '
                . '¿El envío es para ' . $duda . ($deps ? ' (' . $deps[0] . ')' : '') . '?';

            [, $sub] = $this->resumenCarrito();
            $a = $this->envio($sub);
            if ($a !== null) $msg .= ' El costo es el mismo, $' . number_format($a, 2) . '.';

            if (! $f->dato('nombre')) $msg .= "\n\n¿Y a nombre de quién lo enviamos? (nombre y apellido)";

            $this->guionDecir($msg, 'muni_duda');
            return;
        }

        // Preguntó por el envío sin decir de dónde es: primero el municipio.
        if ($f->dato('envio_primero') && ! $f->dato('municipio')) {
            $this->guionPreguntarMunicipio($hola);
            return;
        }

        // El municipio recién dicho cuando preguntó por el envío: se le da el
        // costo y se sigue con lo que falta.
        if ($f->dato('envio_primero') && $f->dato('municipio') && $f->dato('departamento')) {
            $f->quitar('envio_primero');
            if (! $this->entregaPropia() && ! $f->dato('carrito')) {
                [, $sub] = $this->resumenCarrito();
                $e = $this->envio($sub);
                if ($e !== null) {
                    $this->gAntes[] = '🚚 El envío a *' . $f->dato('municipio') . '* es de *$' . number_format($e, 2) . '* (lleve lo que lleve) y llega en ' . $this->guionEntrega() . '.';
                    $f->poner('envio_dicho', true);
                }
            }
        }

        // ── 1. Talla ────────────────────────────────────────────────────────
        if ($f->dato('peso_pend')) {
            $v = rtrim(rtrim(number_format((float) $f->dato('peso_pend'), 1), '0'), '.');
            $this->guionDecir($hola . "¿Son {$v} libras o {$v} kilos?", 'peso_unidad');
            return;
        }

        if (! $f->dato('tallas')) {
            if ($f->dato('peso_lb') !== null) {
                if (! $this->guionRecomendar((float) $f->dato('peso_lb'), $hola)) return;
                // Recomendó una sola talla: sigue con las fotos en este mismo turno.
                $hola = '';
            } elseif ($f->dato('edad_meses') !== null) {
                $this->guionPorEdad((int) $f->dato('edad_meses'), $hola);
                return;
            } else {
                $this->guionListaTallas($texto, $primero);
                return;
            }
        }

        // ── 2. Producto y cantidad ──────────────────────────────────────────
        if (! $f->dato('carrito') && ! $f->dato('elegido')) {
            $this->guionMostrarProductos($hola);
            return;
        }

        // Eligió un producto sin decir cuántos. PRIMERO dónde es: sin eso no se
        // sabe el envío, y preguntar "¿cuántos le enviamos?" sin saber a dónde
        // es apurarla. Con el municipio ya dicho, se le da el costo y recién ahí
        // se le pregunta cuántos.
        if ($f->dato('elegido')) {
            $s = ProductSize::with('product')->find($f->dato('elegido'));
            $que = $s ? '*' . trim((string) $s->product->name) . '* talla ' . trim((string) $s->size) : 'ese';

            if (! $f->dato('municipio')) {
                if ($f->dato('muni_ops') || (int) $f->dato('muni_fallos', 0) > 0) {
                    $this->guionPreguntarMunicipio($hola);
                } else {
                    $this->guionDecir($hola . "¡Con gusto! 😊 {$que}.\n\n📦 ¡Tenemos cobertura nacional en nuestros envíos! 🇸🇻 ¿Desde qué municipio nos escribe?", 'municipio');
                }
                return;
            }

            if ($f->dato('departamento') && ! $this->entregaPropia()) {
                $costo = '';
                if (! $f->dato('envio_dicho')) {
                    $e = $this->envio(0);
                    if ($e !== null) {
                        $costo = '🚚 El envío a *' . $f->dato('municipio') . '* es de *$' . number_format($e, 2) . '* (lleve lo que lleve) y llega en ' . $this->guionEntrega() . ".\n\n";
                        $f->poner('envio_dicho', true);
                    }
                }
                $this->guionDecir($hola . $costo . "¿Cuántos paquetes de {$que} le enviamos? 😊", 'cantidad');
                return;
            }
            // Falta el departamento, o es San Miguel: eso va primero (abajo).
        }

        // ── 3. Municipio ────────────────────────────────────────────────────
        if (! $f->dato('municipio')) {
            $this->guionPreguntarMunicipio($hola);
            return;
        }

        if (! $f->dato('departamento')) {
            $this->guionDecir('Hay un ' . $f->dato('municipio') . ' en varios departamentos. ¿Cuál es el suyo: '
                . $this->unir((array) $f->dato('deptos', []), ' o ') . '?', 'depto');
            return;
        }

        // San Miguel: la entrega es tuya; se pregunta la colonia y te lo pasa.
        if ($this->entregaPropia() && ! $f->dato('colonia')) {
            $this->guionDecir(trim((string) config('asistente.entrega_propia.pregunta')), 'colonia');
            return;
        }

        // ── 4. Total: ¿le parece bien? ──────────────────────────────────────
        if (! $f->dato('envio_ok')) {
            $this->guionTotal();
            return;
        }

        // ── 5. Nombre y dirección (el teléfono es el del chat) ──────────────
        if (! $f->dato('nombre') || ! $f->dato('direccion')) {
            $this->guionPedirDatos();
            return;
        }

        if (! $f->dato('telefono')) {
            if ($this->conv->esExtranjero()) {
                $this->guionDecir('¿A qué número de El Salvador le podemos llamar o escribir para coordinar la entrega? (8 dígitos) 📞', 'telefono');
                return;
            }
            $f->poner('telefono', $this->conv->telefonoLegible());
        }

        // ── 6. La orden (si ya la tiene, no se le repite entera) ────────────
        if ($f->dato('orden_mostrada')) {
            $antes = $this->gAntes ? implode("\n\n", array_unique($this->gAntes)) . "\n\n" : '';
            $this->gAntes = [];
            $f->poner('ultima', 'orden');
            $this->botones($antes . '¿Me podría revisar la orden de envío para evitar futuros errores? 🙏', ['conf:si' => 'Está correcta', 'conf:corregir' => 'Corregir']);
            return;
        }

        $this->guionOrden();
    }

    // ── Los mensajes de cada paso ──────────────────────────────────────────

    /** Manda lo de antes + el mensaje, y anota qué se le preguntó. */
    private function guionDecir(string $msg, string $ultima): void
    {
        $antes = $this->gAntes ? implode("\n\n", array_unique($this->gAntes)) . "\n\n" : '';
        $this->gAntes = [];
        $this->f->poner('ultima', $ultima);
        $this->f->paso = 'guion · ' . $ultima;
        $this->texto($antes . $msg);
    }

    private function guionListaTallas(string $texto, bool $primero): void
    {
        // Ya la vio y ahora hizo una pregunta: no se le repite la lista.
        if ($this->f->dato('tallas_vistas') && ! $primero) {
            $this->guionDecir('¿Qué talla usa su bebé? Si no está segura, dígame cuánto pesa y le recomiendo 😊', 'talla');
            return;
        }
        $this->f->poner('tallas_vistas', true);

        $lineas = [];
        foreach ($this->tallasDisponibles() as $t) {
            $lb = $this->guionRangoLb($t);
            $alias = array_values(array_filter((array) (config('asistente.tallas_alias', [])[mb_strtoupper($t)] ?? []), fn ($x) => mb_strlen($x) <= 4));
            $nums = array_keys(array_filter((array) config('asistente.tallas_numericas', []), fn ($x) => mb_strtoupper($x) === mb_strtoupper($t)));
            $nums = array_filter($nums, fn ($n) => (int) $n >= 2);
            $otros = array_merge($alias, $nums ? ['talla ' . implode('-', $nums)] : []);

            $peso = mb_strtoupper($t) === 'RN'
                ? 'recién nacido, hasta ' . (int) round((float) config('asistente.rn_hasta_kg', 4.5) * 2.2046) . ' libras'
                : ($lb ? $lb[0] . ' a ' . $lb[1] . ' libras' : '');

            $lineas[] = '• *' . $this->bonita($t) . '*' . ($otros ? ' (' . implode(' · ', $otros) . ')' : '') . ($peso ? ' — ' . $peso : '');
        }

        $pregunta = (bool) preg_match('/\?|^\s*(tiene|tienen|hay|venden|manejan)\b/iu', $texto);
        $inicio = $primero
            ? ($pregunta ? '¡Hola! Sí, con gusto 😊' : '¡Hola! 😊 Con gusto le ayudo.')
            : 'Con gusto 😊';

        $this->guionDecir($inicio . " Estas son las tallas que tenemos:\n\n" . implode("\n", $lineas)
            . "\n\n¿Qué talla usa su bebé? Si no está segura, dígame cuánto pesa y le recomiendo 😊", 'talla');
    }

    private function guionPorEdad(int $meses, string $hola): void
    {
        $edad = $meses >= 12
            ? (intdiv($meses, 12) === 1 ? '1 año' : intdiv($meses, 12) . ' años')
            : ($meses === 1 ? '1 mes' : $meses . ' meses');

        $pista = null;
        foreach ((array) config('asistente.tallas_por_edad', []) as [$desde, $hasta, $txt]) {
            if ($meses >= $desde && $meses <= $hasta) { $pista = $txt; break; }
        }

        $msg = ($hola ?: '¡Qué lindo! 😊 ')
            . ($pista ? "A los {$edad} la mayoría usa talla {$pista}, pero lo que manda es el peso. " : 'Lo que manda es el peso. ')
            . '¿Sabe más o menos cuánto pesa? Así le digo la talla exacta.'
            . "\n\nSi no sabe el peso, dígame qué talla usa ahora (aunque sea de otra marca).";

        $this->guionDecir($msg, 'peso');
    }

    /**
     * Del peso a la talla. Si queda una (o una claramente mejor), la deja
     * puesta y devuelve true para seguir con las fotos. Si quedan dos
     * parejas, pregunta y devuelve false.
     */
    private function guionRecomendar(float $lb, string $hola): bool
    {
        $f = $this->f;
        $disp = array_map('mb_strtoupper', $this->tallasDisponibles());
        $v = rtrim(rtrim(number_format($lb, 1), '0'), '.');

        // Recién nacido.
        $rnLb = (float) config('asistente.rn_hasta_kg', 4.5) * 2.2046;
        if ($lb <= $rnLb && in_array('RN', $disp, true)) {
            $f->poner('tallas', ['RN']);
            $this->gAntes[] = $hola . "Con {$v} libras le queda la talla *RN* (recién nacido) 😊";
            return true;
        }

        $ninos = array_map('mb_strtoupper', (array) config('asistente.tallas_nino', []));
        $orden = array_values(array_filter(array_map('mb_strtoupper', (array) config('asistente.tallas', [])), fn ($t) => $this->guionRangoLb($t) !== null));

        $calzan = function (array $lista) use ($lb) {
            return array_values(array_filter($lista, function ($t) use ($lb) {
                $r = $this->guionRangoLb($t);
                return $r && $lb >= $r[0] && $lb <= $r[1];
            }));
        };

        $les = $calzan(array_values(array_diff($orden, $ninos))) ?: $calzan(array_values(array_intersect($orden, $ninos)));

        if (! $les) {
            $this->pasarAWil("pesa {$v} libras y no hay talla en la tabla");
            return false;
        }

        if (count($les) > 2) $les = array_slice($les, -2);

        $una = null;
        $nota = '';

        if (count($les) === 2) {
            [$a, $b] = $les;
            $ra = $this->guionRangoLb($a);
            if ($lb >= $ra[1] - 2) {
                $una = $b;
                $nota = ' La *' . $this->bonita($a) . '* llega hasta ' . $ra[1] . ' libras, así que ya le quedaría justa y le duraría poco.';
            }
        } else {
            $una = $les[0];
        }

        // Lo que hay hoy.
        if ($una !== null && ! in_array($una, $disp, true)) {
            $otra = array_values(array_filter($les, fn ($t) => $t !== $una && in_array($t, $disp, true)));
            if ($otra) {
                $this->gAntes[] = $hola . 'En este momento no tenemos la talla *' . $this->bonita($una) . '* 😔 pero con ' . $v . ' libras también le queda la *' . $this->bonita($otra[0]) . '*.';
                $f->poner('tallas', [$otra[0]]);
                return true;
            }
            $this->marcarAgotado();
            $this->guionDecir($hola . 'Con ' . $v . ' libras le queda la talla *' . $this->bonita($una) . '*, pero en este momento no la tenemos 😔 Apenas entre le avisamos por aquí.', 'talla');
            $f->poner('tallas_vistas', true);
            return false;
        }

        if ($una !== null) {
            $r = $this->guionRangoLb($una);
            $f->poner('tallas', [$una]);
            $this->gAntes[] = $hola . "Con {$v} libras le recomiendo la talla *" . $this->bonita($una) . '*' . ($r ? " ({$r[0]} a {$r[1]} libras)" : '') . ' 😊' . $nota;
            return true;
        }

        // Dos parejas.
        $hay = array_values(array_filter($les, fn ($t) => in_array($t, $disp, true)));
        if (count($hay) === 1) {
            $f->poner('tallas', $hay);
            $this->gAntes[] = $hola . "Con {$v} libras le queda la talla *" . $this->bonita($hay[0]) . '* 😊';
            return true;
        }
        if (! $hay) {
            $this->marcarAgotado();
            $this->guionDecir($hola . 'Para ese peso en este momento no tenemos talla 😔 Apenas entre le avisamos por aquí.', 'talla');
            return false;
        }

        [$a, $b] = $les;
        $f->poner('sugeridas', $les);
        $this->guionDecir($hola . "Con {$v} libras le quedan bien la *" . $this->bonita($a) . '* y la *' . $this->bonita($b) . '*; la *' . $this->bonita($b) . '* le dura más 😊'
            . "\n\n¿Le muestro la " . $this->bonita($a) . ', la ' . $this->bonita($b) . ' o las dos?', 'dos_tallas');
        return false;
    }

    private function guionMostrarProductos(string $hola): void
    {
        $f = $this->f;
        $tallas = (array) $f->dato('tallas', []);
        $tipo = (string) ($f->dato('tipo') ?: 'ambos');
        $nombreTallas = $this->unir(array_map(fn ($t) => $this->bonita($t), $tallas));

        $opciones = $this->presentaciones($tallas, $tipo);

        // No hay de ese tipo, pero sí del otro.
        if ($opciones->isEmpty() && $tipo !== 'ambos') {
            $otras = $this->presentaciones($tallas, 'ambos');
            if ($otras->isNotEmpty()) {
                $this->gAntes[] = 'En talla ' . $nombreTallas . ' en este momento no tenemos de ' . $tipo . ' 😔 pero sí tenemos estas:';
                $f->quitar('tipo');
                $opciones = $otras;
            }
        }

        if ($opciones->isEmpty()) {
            $this->marcarAgotado();
            $f->quitar('tallas');
            $this->guionDecir($hola . 'En este momento no tenemos talla ' . $nombreTallas . ' 😔 Apenas entre le avisamos por aquí. ¿Le muestro otra talla?', 'talla');
            return;
        }

        $enviadas = (array) $f->dato('fotos_enviadas', []);
        $idsEnviados = array_map(fn ($e) => (string) $e['id'], $enviadas);
        $nuevas = $opciones->filter(fn ($s) => ! in_array((string) $s->id, $idsEnviados, true))->values()
            ->take(max(1, (int) config('asistente.max_fotos', 4)));

        // Si es una sola, ya queda elegida: solo falta cuántos.
        $una = $opciones->count() === 1 ? $opciones->first() : null;
        if ($una) $f->poner('elegido', (string) $una->id);

        if ($nuevas->isNotEmpty()) {
            $prefijo = trim($hola . ($this->gAntes ? implode("\n\n", array_unique($this->gAntes)) : ''));
            $this->gAntes = [];
            if ($prefijo !== '' && ! $una) $prefijo .= "\n\nLe muestro lo que tenemos en talla " . $nombreTallas . ' 👇';

            foreach ($nuevas as $i => $s) {
                $n = count($enviadas) + 1;
                $this->tarjeta($s, $n, $i === 0 ? $prefijo : '');
                $enviadas[] = ['opcion' => $n, 'id' => (string) $s->id];
            }
            $f->poner('fotos_enviadas', array_slice($enviadas, -20));

            // Sin apurarla: se le ofrece explicar, no se le pide que elija.
            // Si no contesta, no se le insiste.
            $f->poner('ofrecio_diferencia', true);
            $this->guionDecir($una
                ? '¿Le gustaría conocer más detalles de esta presentación? 😊'
                : '¿Le gustaría que le explique la diferencia entre ellas? 😊', 'producto');
            return;
        }

        // Ya las vio: se nombran por su número de opción.
        $lineas = [];
        foreach ($opciones as $s) {
            $n = null;
            foreach ($enviadas as $e) if ((string) $e['id'] === (string) $s->id) $n = $e['opcion'];
            $lineas[] = ($n ? 'la *opción ' . $n . '*' : 'el') . ' (' . trim((string) $s->product->name) . ')';
        }

        $donde = ($tipo !== 'ambos' && $f->dato('tipo') ? 'En ' . $tipo . ' talla ' : 'En talla ') . $nombreTallas;
        $f->poner('ofrecio_diferencia', true);
        $msg = $una
            ? '¡Perfecto! 😊 ' . $donde . ' tenemos ' . $lineas[0] . '. ¿Le gustaría conocer más detalles?'
            : '¡Perfecto! 😊 ' . $donde . ' tenemos ' . $this->unir($lineas) . ', son las fotos que le mandé.' . "\n\n¿Le gustaría que le explique la diferencia entre ellas?";

        $this->guionDecir($hola . $msg, 'producto');
    }

    private function guionPreguntarMunicipio(string $hola): void
    {
        $f = $this->f;

        if ($ops = (array) $f->dato('muni_ops', [])) {
            $partes = array_map(function ($m) {
                $d = Municipios::departamentosDe($m);
                return '*' . $m . '*' . ($d ? ' (' . $d[0] . ')' : '');
            }, $ops);

            $this->guionDecir('Disculpe, no encontré "' . $f->dato('muni_escrito') . '" 🙏 ' . (count($ops) === 1
                ? '¿Quiso decir ' . $partes[0] . '?'
                : '¿Será ' . $this->unir($partes, ' o ') . '?'), 'muni_ops');
            return;
        }

        if ((int) $f->dato('muni_fallos', 0) >= 1) {
            $this->guionDecir('No encontré ese municipio 🙏 ¿Me lo escribe como aparece? Por ejemplo: *Soyapango*, *Santa Tecla*, *San Miguel*', 'municipio');
            return;
        }

        $resumen = '';
        if ($this->gCarritoNuevo && $f->dato('carrito')) {
            $partes = [];
            foreach ((array) $f->dato('carrito') as $c) {
                $s = ProductSize::with('product')->find($c['id']);
                if ($s) $partes[] = $c['cant'] . ' ' . ((int) $c['cant'] === 1 ? 'paquete' : 'paquetes') . ' de *' . trim((string) $s->product->name) . '* talla ' . trim((string) $s->size);
            }
            if ($partes) $resumen = '¡Con gusto! 😊 ' . $this->unir($partes) . ".\n\n";
        }

        // Si preguntó cuánto vale el envío, se le dice que depende de dónde es.
        $depende = $f->dato('envio_primero') ? ' El costo depende del municipio:' : '';

        $this->guionDecir($hola . $resumen . '📦 ¡Tenemos cobertura nacional en nuestros envíos! 🇸🇻' . $depende . ' ¿Desde qué municipio nos escribe?' . ($resumen ? '' : ' 😊'), 'municipio');
    }

    private function guionTotal(): void
    {
        $f = $this->f;
        [$lineas, $subtotal] = $this->resumenCarrito();
        $envio = $this->envio($subtotal);

        if ($envio === null) {
            $this->pasarAWil('no hay precio de envío para ' . $f->dato('municipio'));
            return;
        }

        $f->poner('envio', $envio);
        $mun = (string) $f->dato('municipio');

        // Si ya se le dijo el costo del envío a ese municipio, no se repite.
        $yaDicho = (bool) $f->dato('envio_dicho');
        $f->quitar('envio_dicho');

        $msg = ($yaDicho
                ? '¡Perfecto! 😊 Así queda su pedido:'
                : ($envio > 0
                    ? '¡Perfecto! 🚚 El envío a *' . $mun . '* es de *$' . number_format($envio, 2) . '* (lleve lo que lleve) y llega en ' . $this->guionEntrega() . '.'
                    : '¡Perfecto! 🚚 Su pedido a *' . $mun . '* llega en ' . $this->guionEntrega() . '.'))
            . "\n\n🛒 " . implode("\n", array_map(fn ($l) => ltrim($l, '• '), $lineas))
            . "\nEnvío — $" . number_format($envio, 2)
            . "\n*Total: $" . number_format($subtotal + $envio, 2) . '*'
            . "\n\n¿Le parece bien? 😊";

        $this->guionDecir($msg, 'total');
    }

    private function guionPedirDatos(): void
    {
        $f = $this->f;
        $pre = $this->gAcepto ? '¡Excelente! 😊 ' : '';

        if (! $f->dato('nombre') && ! $f->dato('direccion')) {
            $this->guionDecir($pre . "Para enviarlo me regala por favor:\n\n• Nombre completo\n• Dirección exacta (colonia, calle o pasaje, número de casa y un punto de referencia)", 'datos');
            return;
        }

        if (! $f->dato('nombre')) {
            $this->guionDecir($pre . '¿A nombre de quién lo enviamos? (nombre y apellido)', 'datos');
            return;
        }

        $this->guionDecir($pre . '¿Me regala la dirección exacta? (colonia, calle o pasaje, número de casa y un punto de referencia) 🏠', 'datos');
    }

    private function guionOrden(): void
    {
        $f = $this->f;
        $f->poner('orden_mostrada', true);
        $f->poner('ultima', 'orden');
        $f->paso = 'guion · orden';

        $nombre = trim(strtok((string) $f->dato('nombre'), ' ') ?: '');
        $antes = $this->gAntes ? implode("\n\n", array_unique($this->gAntes)) . "\n\n" : '';
        $this->gAntes = [];

        // La segunda vez (después de corregir) no se vuelve a agradecer.
        $saludo = $f->dato('orden_vista') ? '¡Listo, ya lo corregí! 😊' : '¡Gracias' . ($nombre !== '' ? ', ' . $nombre : '') . '! 😊';
        $f->poner('orden_vista', true);

        $todo = $antes . $saludo . "\n\n" . $this->textoOrden()
            . "\n\n¿Me podría revisar la orden de envío para evitar futuros errores? 🙏";

        $botones = ['conf:si' => 'Está correcta', 'conf:corregir' => 'Corregir'];

        if (mb_strlen($todo) <= 1020) {
            $this->botones($todo, $botones);
            return;
        }

        $this->texto($antes . $this->textoOrden());
        $this->botones('¿Me podría revisar la orden de envío para evitar futuros errores? 🙏', $botones);
    }

    // ════════════════════════════════════════════════════════════════════════
    // Entender (IA, y reglas si la IA no está)
    // ════════════════════════════════════════════════════════════════════════

    private function guionEntender(string $texto, int $desdeId): array
    {
        $catalogo = $this->catalogoParaIA();
        $r = null;

        try {
            $charla = [];
            $previos = WaMensaje::where('conversacion_id', $this->conv->id)
                ->where('id', '<', $desdeId)->orderByDesc('id')->limit(12)->get()->reverse();

            foreach ($previos as $p) {
                $t = trim((string) $p->texto);
                if ($p->direccion === 'saliente' && $p->tipo === 'image') $t = '[foto: ' . mb_substr(str_replace("\n", ' · ', $t), 0, 100) . ']';
                if ($t === '' || preg_match('/^\[(reaction|sticker|unsupported|revoke|edit)\]$/', $t)) continue;
                $charla[] = ['quien' => $p->direccion === 'entrante' ? 'CLIENTA' : 'TIENDA', 'texto' => $t];
            }

            $r = Vendedora::leer([
                'catalogo'   => $catalogo,
                'datos'      => $this->datosDeLaTienda(),
                'tallas'     => $this->tallasParaIA(),
                'estado'     => $this->guionEstado(),
                'charla'     => $charla,
                'nuevo'      => $texto,
                'ejemplos'   => Ejemplos::parecidos($texto, (int) config('asistente.ejemplos.cuantos', 10), $this->conv->id),
                'permitidos' => $this->montosPermitidos(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Guion, IA: ' . $e->getMessage());
        }

        $reglas = $this->guionReglas($texto, $catalogo);

        if ($r === null) return $reglas;

        // Lo que la IA dejó vacío y las reglas sí vieron (cosas seguras).
        foreach (['peso', 'acepta', 'cantidad', 'telefono'] as $k) {
            if (($r[$k] === null) && $reglas[$k] !== null) $r[$k] = $reglas[$k];
        }
        if (! $r['municipio'] && $reglas['municipio']) $r['municipio'] = $reglas['municipio'];
        if (! $r['tallas'] && $reglas['tallas'] && in_array($this->f->dato('ultima'), ['', 'talla', 'dos_tallas', 'peso'], true)) $r['tallas'] = $reglas['tallas'];

        return $r;
    }

    /** Lo que se entiende sin IA. Mismo formato que Vendedora::limpiar. */
    private function guionReglas(string $texto, array $catalogo): array
    {
        $f = $this->f;
        $ultima = (string) $f->dato('ultima');
        $n = Entender::normalizar($texto);

        $r = Vendedora::limpiar([], []);

        // Talla: por nombre, por alias o por número de otra marca.
        $tallas = Entender::tallas($texto, ! in_array($ultima, ['', 'talla', 'dos_tallas', 'peso'], true));
        if (! $tallas && ($num = Entender::numeroDeTalla($texto)) !== null) {
            $t = config('asistente.tallas_numericas', [])[$num] ?? null;
            if ($t) $tallas = [$t];
        }
        if ($ultima === 'dos_tallas' && preg_match('/\b(las dos|los dos|ambas|ambos)\b/', $n)) $tallas = (array) $f->dato('sugeridas', []);
        $r['tallas'] = array_values(array_intersect(array_map('mb_strtoupper', $tallas), array_map('mb_strtoupper', (array) config('asistente.tallas', []))));

        if ($p = Entender::peso($texto)) {
            if (! empty($p['kg'])) {
                $r['peso'] = ['valor' => round($p['kg'] * 2.2046, 1), 'unidad' => 'lb'];
                if (str_ends_with((string) ($p['dicho'] ?? ''), 'lb')) $r['peso'] = ['valor' => (float) $p['dicho'], 'unidad' => 'lb'];
            } elseif (! empty($p['valor']) && in_array($ultima, ['peso', 'talla'], true)) {
                $r['peso'] = ['valor' => (float) $p['valor'], 'unidad' => null];
            }
        }

        if (preg_match('/\b(\d{1,2}|un|una|dos|tres|cuatro)\s*(mes|meses|ano|anos|anito|anitos)\b/', $n, $m) && ! $r['peso']) {
            $num = ['un' => 1, 'una' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4][$m[1]] ?? (int) $m[1];
            $r['edad_meses'] = str_starts_with($m[2], 'an') ? $num * 12 : $num;
        }

        $tipo = Entender::tipo($texto);
        if (in_array($tipo, ['cinta', 'calzoncito', 'ambos'], true)) $r['tipo'] = $tipo;

        // La opción: "la 2", "opción 1".
        $vistas = [];
        foreach ((array) $f->dato('fotos_enviadas', []) as $e) {
            $s = ProductSize::with('product')->find($e['id']);
            if ($s) $vistas[] = ['id' => (string) $s->id, 'nombre' => trim((string) $s->product->name), 'unidades' => (int) $s->unidades, 'precio' => (float) $s->price];
        }
        // Con un producto ya elegido, un número solo ("2") es la cantidad, no
        // la opción 2. La opción cuenta si lo dice: "la 2", "opción 2".
        $diceOpcion = $ultima === 'producto' || preg_match('/\b(opcion|numero|la|el|#)\s*\d/', $n) || ! preg_match('/^\s*\d+\s*$/', $n);
        if (in_array($ultima, ['producto', 'cantidad'], true) && $diceOpcion && ($id = Entender::opcion($texto, $vistas))) {
            $r['items'] = [['id' => $id, 'cantidad' => Entender::cantidad($texto, true)]];
        }

        $r['cantidad'] = Entender::cantidad($texto);

        // "el de noche, 2": el número que acompaña al producto elegido.
        if ($r['items'] && $r['items'][0]['cantidad'] === null && preg_match('/(?:^|[\s,])(\d{1,2})(?:\s*(paquetes?|bolsas?))?\s*$/u', $n, $mm)) {
            $r['items'][0]['cantidad'] = (int) $mm[1];
        }

        if ($m = Municipios::buscarEn($texto)) {
            if (! in_array($ultima, ['datos'], true)) $r['municipio'] = $m;
        } elseif (in_array($ultima, ['municipio', 'muni_ops', 'cantidad'], true) && count(explode(' ', $n)) <= 4 && ! $r['cantidad']
            // Una pregunta ("q vale el envío", "¿cuánto cuesta?") no es un municipio mal escrito.
            && ! str_contains($texto, '?')
            && ! preg_match('/\b(q|que|cuanto|cuantos|vale|valen|cuesta|cuestan|precio|costo|envio|envios|tienen|hay|como|cuando|donde|sale)\b/', $n)) {
            $r['municipio'] = preg_replace('/^(de que|de|que|soy de|somos de|para|en|desde)\s+/iu', '', trim($texto, " .,!"));
        }

        $r['acepta'] = Entender::siNo($texto);

        if ($ultima === 'datos') {
            if (! $f->dato('nombre') && ($nom = Entender::pareceNombre($texto))) $r['nombre'] = $nom;
            if (! $f->dato('direccion') && Entender::pareceDireccion($texto)) $r['direccion'] = trim($texto);
        }

        if ($tel = Entender::telefonoSV($texto)) $r['telefono'] = $tel;

        return $r;
    }

    /**
     * La diferencia entre las opciones que se le mostraron de la talla que
     * mira, con el número de foto de cada una y lo que dice la ficha del
     * producto. Vacío si no hay qué comparar.
     */
    private function guionDiferencia(): string
    {
        $f = $this->f;
        $tallas = array_map('mb_strtoupper', (array) $f->dato('tallas', []));
        $tipo = (string) ($f->dato('tipo') ?: 'ambos');

        $lineas = [];
        $tipos = [];

        foreach ((array) $f->dato('fotos_enviadas', []) as $e) {
            $s = ProductSize::with('product')->find($e['id']);
            if (! $s || ! $s->product) continue;
            if ($tallas && ! in_array(mb_strtoupper(trim((string) $s->size)), $tallas, true)) continue;

            $p = $s->product;
            $t = $this->tipoDe((string) $p->name);
            if ($tipo !== 'ambos' && mb_strtolower($t) !== $tipo) continue;
            $tipos[$t] = true;

            // Lo que distingue al producto: sus características cargadas en el
            // admin, o la primera oración de la descripción.
            $rasgos = array_values(array_filter(array_map(
                fn ($x) => trim(strip_tags((string) (is_array($x) ? ($x['texto'] ?? $x['text'] ?? reset($x)) : $x))),
                (array) ($p->features ?? [])
            )));
            $resumen = $rasgos ? implode(' · ', array_slice($rasgos, 0, 2)) : '';
            if ($resumen === '') {
                $desc = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($p->description ?? ''))));
                $resumen = $desc !== '' ? (preg_split('/(?<=[.!?])\s/u', $desc)[0] ?? $desc) : '';
            }
            if (mb_strlen($resumen) > 170) $resumen = rtrim(mb_substr($resumen, 0, 167)) . '…';

            $lineas[] = '*Opción ' . $e['opcion'] . ' — ' . trim((string) $p->name) . "* ({$t})"
                . ($resumen !== '' ? "\n" . $resumen : '')
                . ((int) $s->unidades > 0 ? "\n" . (int) $s->unidades . ' unidades por paquete' : '');
        }

        if (count($lineas) === 0) return '';

        $txt = "Con gusto le explico 😊\n\n" . implode("\n\n", $lineas);
        if (count($tipos) > 1) $txt .= "\n\n" . trim((string) config('asistente.textos.diferencia'));

        return $txt;
    }

    /** Cómo va el pedido, para la IA (y lo último que se le preguntó). */
    private function guionEstado(): array
    {
        $f = $this->f;

        $preguntas = [
            ''            => '(nada todavía)',
            'talla'       => '¿Qué talla usa su bebé? (o cuánto pesa)',
            'peso'        => '¿Cuánto pesa el bebé?',
            'peso_unidad' => '¿Son libras o kilos?',
            'dos_tallas'  => '¿Le muestro una talla, la otra o las dos?',
            'producto'    => 'Se le mostraron las fotos y se le ofreció explicarle la diferencia (o más detalles). Si dice que sí, acepta=true. Si elige un producto o dice cuántos, va en items/cantidad.',
            'cantidad'    => '¿Cuántos paquetes?',
            'municipio'   => '¿Desde qué municipio nos escribe?',
            'muni_ops'    => '¿Será alguno de estos municipios?',
            'depto'       => '¿De qué departamento?',
            'colonia'     => '¿En qué colonia de San Miguel?',
            'total'       => 'Se le mostró el envío y el total: ¿Le parece bien?',
            'datos'       => 'Nombre completo y dirección exacta',
            'muni_duda'   => 'La dirección nombra otro municipio: ¿el envío es para ese otro?',
            'telefono'    => '¿A qué número le llamamos?',
            'orden'       => 'Se le mostró la orden: ¿me la revisa? (¿está correcta?)',
            'corregir'    => '¿Qué dato corrijo?',
        ];

        $fotos = [];
        foreach ((array) $f->dato('fotos_enviadas', []) as $e) {
            $s = ProductSize::with('product')->find($e['id']);
            if ($s) $fotos[] = ['opcion' => $e['opcion'], 'id' => (string) $s->id, 'producto' => trim((string) $s->product->name) . ' talla ' . trim((string) $s->size) . ' (' . $this->tipoDe((string) $s->product->name) . ')'];
        }

        $carrito = [];
        foreach ((array) $f->dato('carrito', []) as $c) {
            $s = ProductSize::with('product')->find($c['id']);
            if ($s) $carrito[] = ['id' => (string) $s->id, 'producto' => trim((string) $s->product->name) . ' talla ' . trim((string) $s->size), 'cantidad' => (int) $c['cant']];
        }

        return array_filter([
            'ultima_pregunta' => $preguntas[(string) $f->dato('ultima')] ?? (string) $f->dato('ultima'),
            'talla'           => $f->dato('tallas'),
            'tallas_sugeridas'=> $f->dato('sugeridas'),
            'peso_libras'     => $f->dato('peso_lb'),
            'tipo'            => $f->dato('tipo'),
            'fotos_enviadas'  => $fotos,
            'eligio_sin_cantidad' => $f->dato('elegido'),
            'carrito'         => $carrito,
            'municipio'       => $f->dato('municipio'),
            'opciones_de_municipio' => $f->dato('muni_ops'),
            'municipio_en_duda' => $f->dato('muni_duda'),
            'nombre'          => $f->dato('nombre'),
            'direccion'       => $f->dato('direccion'),
            'orden_mostrada'  => $f->dato('orden_mostrada') ? true : null,
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    // ════════════════════════════════════════════════════════════════════════
    // Ayudas
    // ════════════════════════════════════════════════════════════════════════

    private function guionSumar(array $carrito, string $id, int $cant): array
    {
        $cant = min((int) config('asistente.max_paquetes', 10), max(1, $cant));

        foreach ($carrito as &$c) {
            if ((string) $c['id'] === $id) { $c['cant'] = $cant; return $carrito; }
        }
        unset($c);

        $carrito[] = ['id' => $id, 'cant' => $cant];
        return $carrito;
    }

    /** El municipio que dijo: exacto, o los parecidos para preguntarle. */
    private function guionPonerMunicipio(string $dicho): void
    {
        $f = $this->f;
        $m = Municipios::buscarEn($dicho);
        if (! $m && Municipios::existe($dicho)) $m = $dicho;

        if ($m) {
            $nuevo = Municipios::nombreBueno($m) ?: $m;
            if (Municipios::normalizar($nuevo) !== Municipios::normalizar((string) $f->dato('municipio'))) {
                $f->quitar('envio_ok', 'orden_mostrada', 'colonia', 'envio', 'envio_dicho');
            }
            $this->ponerMunicipio($m);
            $f->quitar('muni_ops', 'muni_escrito', 'muni_fallos');
            return;
        }

        // Mal escrito: los dos más parecidos.
        $n = Municipios::normalizar(preg_replace('/^(de que|de|que|soy de|somos de|para|en|desde)\s+/iu', '', trim($dicho, " .,!")));
        $dist = [];
        foreach (Municipios::todos() as $mun) {
            $dist[$mun] = levenshtein($n, Municipios::normalizar($mun));
        }
        asort($dist);

        $tope = max(2, (int) floor(mb_strlen($n) * 0.4));
        $ops = array_slice(array_keys(array_filter($dist, fn ($d) => $d <= $tope)), 0, 2);

        if ($ops) {
            $f->poner('muni_ops', $ops);
            $f->poner('muni_escrito', mb_substr(trim($dicho), 0, 40));
        } else {
            $f->quitar('muni_ops');
        }

        // Segunda vez que no se encuentra: te lo pasa, para no hacerla pelear.
        $fallos = (int) $f->dato('muni_fallos', 0) + 1;
        $f->poner('muni_fallos', $fallos);

        if ($fallos >= 2) {
            $this->paraWil = 'no le entendí el municipio';
        }
    }

    /**
     * ¿Nombra un municipio dentro de ese texto (la dirección)? También mal
     * escrito por una letra ("mejicano" → Mejicanos).
     */
    private function guionMunicipioEn(string $texto): ?string
    {
        if ($m = Municipios::buscarEn($texto)) return $m;

        $palabras = preg_split('/[^a-z0-9ñ]+/u', Municipios::normalizar($texto), -1, PREG_SPLIT_NO_EMPTY);
        $nombres = [];
        foreach (Municipios::todos() as $mun) $nombres[Municipios::normalizar($mun)] = $mun;

        for ($largo = 3; $largo >= 1; $largo--) {
            for ($i = 0; $i + $largo <= count($palabras); $i++) {
                $trozo = implode(' ', array_slice($palabras, $i, $largo));
                if (mb_strlen($trozo) < 6) continue;
                foreach ($nombres as $n => $mun) {
                    if (abs(strlen($n) - strlen($trozo)) <= 1 && levenshtein($trozo, $n) <= 1) return $mun;
                }
            }
        }

        return null;
    }

    /** "La talla G es nuestra L 👍" cuando la pidió con otro nombre. */
    private function guionAclararAlias(string $texto, array $tallas): void
    {
        if (count($tallas) !== 1) return;
        $t = mb_strtoupper($tallas[0]);
        $n = Entender::normalizar($texto);

        if (preg_match('/\b' . preg_quote(Entender::normalizar($t), '/') . '\b/', $n)) return;   // la dijo igual

        $dicho = null;
        if (($num = Entender::numeroDeTalla($texto)) !== null) $dicho = 'talla ' . $num;
        foreach ((array) (config('asistente.tallas_alias', [])[$t] ?? []) as $a) {
            if (preg_match('/\b' . preg_quote(Entender::normalizar($a), '/') . '\b/', $n)) { $dicho = 'talla ' . mb_strtoupper($a); break; }
        }

        if ($dicho) $this->gAntes[] = 'La ' . $dicho . ' es nuestra talla *' . $this->bonita($t) . '* 👍';
    }

    /** [mín, máx] en libras de una talla, de config/tallas_peso.php. */
    private function guionRangoLb(string $talla): ?array
    {
        $txt = (string) (config('tallas_peso', [])[mb_strtoupper($talla)] ?? config('tallas_peso', [])[$talla] ?? '');
        if (preg_match('/(\d+)\s*[–\-—a]+\s*(\d+)\s*lb/u', $txt, $m)) return [(int) $m[1], (int) $m[2]];
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*[–\-—a]+\s*(\d+(?:[.,]\d+)?)\s*kg/u', $txt, $m)) {
            return [(int) round((float) str_replace(',', '.', $m[1]) * 2.2046), (int) round((float) str_replace(',', '.', $m[2]) * 2.2046)];
        }
        return null;
    }

    private function guionEntrega(): string
    {
        return trim((string) \App\Models\Setting::get('envio_tiempo', '24 horas hábiles')) ?: '24 horas hábiles';
    }

    /** Lo que la IA puede afirmar de la tienda. */
    private function datosDeLaTienda(): array
    {
        $sm = implode(', ', (array) config('asistente.entrega_propia.municipios', []));

        $datos = [
            '- Envío a domicilio a todo El Salvador por $' . number_format((float) \App\Models\Setting::envio(), 2) . ', lleve lo que lleve. Llega en ' . $this->guionEntrega() . ' con Express El Salvador. Los domingos no se despacha.',
            '- En ' . $sm . ' la entrega la hace la tienda y el costo depende de la colonia. Nunca se dice que es gratis.',
            '- Diferencia: ' . str_replace("\n", ' ', trim((string) config('asistente.textos.diferencia'))),
        ];

        foreach ((array) config('asistente.respuestas', []) as $r) {
            $t = (string) ($r['texto'] ?? '');
            if ($t === '' || str_contains($t, '{envio}')) continue;
            $datos[] = '- ' . str_replace("\n", ' ', $this->rellenar($t));
        }

        return $datos;
    }

    /** Las tallas por peso y cómo les dicen otras marcas. */
    private function tallasParaIA(): array
    {
        $lineas = [];
        foreach ((array) config('tallas_peso', []) as $t => $rango) $lineas[] = "{$t}: {$rango}";
        $lineas[] = 'RN (recién nacido): hasta ' . (int) round((float) config('asistente.rn_hasta_kg', 4.5) * 2.2046) . ' libras';

        foreach ((array) config('asistente.tallas_numericas', []) as $num => $t) {
            $lineas[] = "\"talla {$num}\" de otras marcas = nuestra {$t}";
        }
        foreach ((array) config('asistente.tallas_alias', []) as $t => $alias) {
            $lineas[] = implode(', ', (array) $alias) . " = nuestra {$t}";
        }

        return $lineas;
    }

    /** Los montos que puede escribir la IA en una respuesta suelta. */
    private function montosPermitidos(): array
    {
        $ok = [(float) \App\Models\Setting::envio()];

        foreach ($this->consultaBase()->get() as $s) {
            $ok[] = (float) $s->price;
            if ((float) $s->combo_price > 0) $ok[] = (float) $s->combo_price;
        }

        return array_values(array_unique($ok, SORT_REGULAR));
    }
}

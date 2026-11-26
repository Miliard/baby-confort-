<?php

namespace App\Services;

use App\Services\Asistente\Entender;

/**
 * Qué tan cerca de comprar está una clienta, por lo que escribió.
 *
 * Los pesos salen de los chats reales (5,682 mensajes, 30 días): cuántos de
 * cada tipo de mensaje terminaron en orden de envío en las 48 horas siguientes.
 * El promedio es 38%.
 *
 *   3 🔥🔥🔥  pega una orden (95%), manda sus datos (79%), dice que lo quiere
 *            (64%), pregunta el total (56%)
 *   2 🔥🔥    elige "este / la opción 2" (46%), envío a su municipio (44%),
 *            forma de pago (42%)
 *   1 🔥      talla o peso (38%, el promedio)
 *   0 ❄️      "quiero más información" del anuncio (10%), solo "¿precio?"
 *            (8–17%), "estaré pendiente / lo voy a pensar" (0–9%), "ok
 *            muchas gracias" (0%)
 */
class Interes
{
    /**
     * "Ya transferí", "le mando el comprobante", "lo voy a hacer por
     * transferencia", "ya deposité", "ahí está el pago"…
     */
    public static function esPago(?string $texto): bool
    {
        $n = Entender::normalizar($texto);
        if ($n === '') return false;
        if (preg_match('/\b(no|todavia no|aun no) (he )?(transferido|depositado|pagado|transferi|deposite|pague)\b/', $n)) return false;

        // Ya pagó: siempre.
        if (preg_match('/\b('
            . 'ya (le |se lo |se los )?(transferi|deposite|pague|cancele|hice (la )?transferencia|hice (el )?deposito|mande (la )?transferencia|envie (la )?transferencia|realice (la )?transferencia)'
            . '|comprobante|captura (de|del) (pago|la transferencia|deposito)'
            . '|transferencia (realizada|hecha|lista|enviada|exitosa)|deposito (realizado|hecho)|pago (realizado|hecho|enviado)'
            . '|ahi (esta|va|le va|le mando) (la transferencia|el comprobante|el pago|el deposito)'
            . '|ya (esta|quedo) (pagado|cancelado|transferido)'
            . ')\b/', $n)) return true;

        // Va a pagar así (no es pregunta: "¿puedo pagar por transferencia?" no cuenta).
        if (str_contains((string) $texto, '?') || preg_match('/^(puedo|se puede|aceptan|tienen|reciben|hay)\b/', $n)) return false;

        return (bool) preg_match('/\b('
            . '(lo|la|los) (voy a|vamos a) (pagar|hacer|cancelar) (por|con) (transferencia|deposito|wompi|tarjeta)'
            . '|(voy a|vamos a) (transferir|depositar|hacer la transferencia|hacer el deposito)'
            . '|(pago|pagare|pagaria|cancelo|cancelare) (por|con) (transferencia|deposito|wompi|tarjeta)'
            . '|sera (en|por) transferencia|(le )?transfiero|le deposito|le hago la transferencia'
            . ')\b/', $n);
    }

    /** ¿Ese mensaje nuestro le pasó la cuenta del banco o el enlace de pago? */
    public static function esDatosDePago(?string $texto): bool
    {
        $n = Entender::normalizar($texto);
        return $n !== '' && (bool) preg_match('/\b(numero de cuenta|cuenta (de ahorro|corriente|bancaria)|banco (agricola|cuscatlan|promerica|davivienda|hipotecario|atlantida|azul|industrial)|bac|wompi|tigo money|transferencia a (la|nuestra) cuenta|a nombre de .{2,40} cuenta)\b/', $n);
    }

    /** ['puntos' => 0..3, 'motivo' => '…'] */
    public static function de(?string $texto, string $tipo = 'text'): array
    {
        if ($tipo === 'location') return ['puntos' => 3, 'motivo' => 'mandó su ubicación'];

        $crudo = (string) $texto;
        $n = Entender::normalizar($crudo);
        if ($n === '') return ['puntos' => 0, 'motivo' => ''];

        // ── 4 💳 Pagó o va a pagar por transferencia ─────────────────────────
        // Lo más urgente: si la guía ya salió con cobro, hay que ponerla en $0
        // antes de que el repartidor le cobre.
        if (static::esPago($crudo)) {
            return ['puntos' => 4, 'motivo' => 'avisa que paga por transferencia'];
        }

        // ── 3 ───────────────────────────────────────────────────────────────
        if (preg_match('/orden de env[ií]o|nombre completo|direcci[oó]n exacta|total a pagar/iu', $crudo)) {
            return ['puntos' => 3, 'motivo' => 'pegó una orden de envío'];
        }
        if (preg_match('/\b(colonia|col|barrio|canton|caserio|pasaje|residencial|urbanizacion|lotificacion|frente a|a la par de|contiguo a|a nombre de|mi nombre es|casa (n|#|numero)?\s*\d+)\b/', $n)
            && mb_strlen($n) >= 15) {
            return ['puntos' => 3, 'motivo' => 'mandó sus datos'];
        }
        if (preg_match('/\b(cuanto (sale|seria|serian|es|son|queda|me sale) (por |en )?todo|cuanto (seria|es|sale) (ya )?con (el )?envio|ya con (el )?envio|cuanto (seria|es) el total|cuanto pago en total)\b/', $n)) {
            return ['puntos' => 3, 'motivo' => 'pregunta el total'];
        }
        if (Etiquetado::quierePedir($crudo)) {
            return ['puntos' => 3, 'motivo' => 'dice que lo quiere'];
        }

        // ── 0 (antes que lo tibio: "ok gracias, estaré pendiente") ─────────
        if (preg_match('/\b(estare pendiente|estamos pendientes|lo voy a pensar|lo pienso|despues le (escribo|aviso|confirmo)|luego le (escribo|aviso)|mas tarde le|le aviso|yo le aviso|yo le escribo|otro dia|la otra semana|por ahora no|no gracias)\b/', $n)
            || preg_match('/^(ok|okey|oki|gracias|muchas gracias|ok muchas gracias|ok gracias|bueno gracias|esta bien gracias)$/', $n)) {
            return ['puntos' => 0, 'motivo' => 'se enfrió'];
        }

        // ── 2 ───────────────────────────────────────────────────────────────
        if (preg_match('/\b(contra entrega|pago al recibir|transferencia|como (le )?pago|forma de pago|formas de pago)\b/', $n)) {
            return ['puntos' => 2, 'motivo' => 'pregunta cómo pagar'];
        }
        if (preg_match('/\b((hacen|hace|tienen|ase|asen) envios? (a|hasta)|envian (a|hasta)|mandan (a|hasta)|llegan (a|hasta)|envio (a|hasta|para))\b/', $n)
            || (Municipios::buscarEn($n) && preg_match('/\b(envio|envios|mandan|llega|entregan)\b/', $n))) {
            return ['puntos' => 2, 'motivo' => 'pregunta envío a su municipio'];
        }
        if (mb_strlen($n) <= 30 && preg_match('/^(y )?(este|ese|esta|esa|estos|esos|estas|esas|de este|de ese|de esta|de esa|opcion \d|la opcion \d|la \d|el \d)\b/', $n)) {
            return ['puntos' => 2, 'motivo' => 'eligió'];
        }

        // ── 1 / 0 ───────────────────────────────────────────────────────────
        if (preg_match('/\b(talla|tallas|rn|xl|xxl|xxxl|libras?|lbs?|kilos?|meses|anos)\b|^(s|m|l|g|xg)$/', $n)) {
            return ['puntos' => 1, 'motivo' => 'dijo la talla'];
        }
        if (preg_match('/\b(mas informacion|quiero informacion|informacion sobre|me gustaria conseguir)\b/', $n)
            || preg_match('/^(precio|precios|que precio|que cuesta|cuanto cuesta|cuanto vale|a como|el precio|precio\?)$/', $n)) {
            return ['puntos' => 0, 'motivo' => 'solo pregunta'];
        }

        return ['puntos' => 1, 'motivo' => ''];
    }
}

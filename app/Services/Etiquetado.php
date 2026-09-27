<?php

namespace App\Services;

use App\Models\WaConversacion;
use App\Models\WaEtiqueta;
use Illuminate\Support\Facades\Log;

/**
 * Mueve las conversaciones de etiqueta solas, según lo que pasa en el chat.
 *
 * El problema que resuelve es de personas, no de programa: la orden de envío se
 * escribe, se manda, y a nadie se le ocurre que además hay que ir a ponerle la
 * etiqueta. Así el pedido queda sin marcar y después nadie lo encuentra.
 *
 * Entonces el panel lo hace por su cuenta:
 *
 *   · Aparece una orden de envío en el chat  → etiqueta "pedido"
 *   · Sale el enlace de rastreo              → etiqueta "procesada", y se
 *                                              quita la de "pedido"
 *
 * Da igual de dónde venga el mensaje: escrito desde el panel, desde la app del
 * teléfono, o mandado por el propio cliente. Todos los mensajes pasan por
 * WaMensaje al guardarse, y de ahí se dispara esto.
 *
 * Qué etiqueta juega cada papel lo elige Wil en el admin. Si no eligió
 * ninguna, no pasa nada: no se etiqueta y el panel sigue igual que antes.
 */
class Etiquetado
{
    /** ¿Este texto es una orden de envío? */
    public static function pareceOrden(?string $texto): bool
    {
        $t = trim((string) $texto);
        if ($t === '') return false;

        // La plantilla de Wil lleva el encabezado; "Total a pagar" queda como
        // respaldo por si alguien la escribió a mano sin el título.
        foreach (['Orden de Envío', 'Orden de Envio', 'Total a pagar'] as $pista) {
            if (mb_stripos($t, $pista) !== false) return true;
        }

        return false;
    }

    /**
     * Las formas de decir "quiero comprar", como las escriben los clientes.
     *
     * Se comparan contra el mensaje ya en minúsculas y sin tildes, así que
     * acá van escritas igual: "envieme" y no "envíeme".
     *
     * Para agregar una frase nueva, se suma un renglón. Cada una trata de ir
     * pegada a algo que diga PRODUCTO o CANTIDAD — "me manda dos", "me manda
     * la talla L" — y no suelta. "Me manda" solo también es "me manda el
     * precio" o "me manda una foto", y esos son preguntas, no pedidos: la
     * pestaña se llenaría de gente que solo estaba averiguando.
     */
    private const QUIERE_PEDIR = [
        // "quiero otro paquete", "quiero 2", "quiero pedir", "quiero la talla L"
        '/\bquiero\s+(otro|otra|un|una|dos|tres|cuatro|cinco|\d+|mas|pedir|encargar|comprar|llevar|hacer\s+(un|mi|el)?\s*pedido)\b(?!\s+(foto|fotos|imagen|imagenes|informacion|info|precio|precios|ubicacion|captura|video|audio|lista|catalogo|cotizacion|numero|cuenta|mensaje))/',
        '/\bquiero\s+(el|la|los|las)\s+(de\s+)?(talla|paquete|paquetes|panales|calzoncito)/',
        '/\b(quisiera|deseo|necesito|ocupo)\s+(otro|otra|un|una|dos|tres|\d+|pedir|encargar|comprar|llevar)\b(?!\s+(foto|fotos|imagen|imagenes|informacion|info|precio|precios|ubicacion|captura|video|audio|lista|catalogo|cotizacion|numero|cuenta|mensaje))/',

        // "me manda dos", "mándeme la talla XL", "me puede enviar otro"
        '/\bme\s+(manda|mandas|mandan|envia|envias|envian|trae|traes)\s+(otro|otra|un|una|dos|tres|cuatro|\d+|el\s+paquete|los\s+paquetes|la\s+talla|talla|panales|calzoncito)\b(?!\s+(foto|fotos|imagen|imagenes|informacion|info|precio|precios|ubicacion|captura|video|audio|lista|catalogo|cotizacion|numero|cuenta|mensaje))/',
        '/\b(mandeme|mandame|enviame|envieme|traigame|traeme)\s+(otro|otra|un|una|dos|tres|cuatro|\d+|el\s+paquete|los\s+paquetes|la\s+talla|talla|panales|calzoncito)\b(?!\s+(foto|fotos|imagen|imagenes|informacion|info|precio|precios|ubicacion|captura|video|audio|lista|catalogo|cotizacion|numero|cuenta|mensaje))/',
        '/\bme\s+(puede|podria|pueden|podrian|podes)\s+(mandar|enviar|traer)\s+(otro|otra|un|una|dos|tres|\d+|el\s+paquete|los\s+paquetes|la\s+talla|talla|panales)\b(?!\s+(foto|fotos|imagen|imagenes|informacion|info|precio|precios|ubicacion|captura|video|audio|lista|catalogo|cotizacion|numero|cuenta|mensaje))/',

        // "quiero hacer un pedido", "cómo hago el pedido", "otro pedido"
        '/\b(hacer|hago|realizar|poner)\s+(un|el|mi|otro|nuevo)?\s*pedido\b/',
        '/\b(otro|nuevo)\s+(pedido|paquete)\b/',
        '/\bcomo\s+(hago|puedo\s+hacer|hacemos|le\s+hago)\s+(el|un|mi)?\s*pedido\b/',

        // "se los encargo", "lo quiero", "me llevo dos", "apártemelo"
        '/\b(le|se\s+lo|se\s+los|se\s+las)\s+(encargo|pido)\b/',
        '/\b(si\s+)?(lo|los|la|las)\s+quiero\b/',
        '/\bme\s+(llevo|quedo\s+con)\b/',
        '/\b(apartame|aparteme|apartemelo|apartemelos)\b/',
    ];

    /**
     * ¿El cliente está diciendo que quiere comprar?
     *
     * Solo reglas, sin IA, y a propósito: esto se pregunta con CADA mensaje
     * que entra, en el mismo instante en que llega. Una llamada a la IA acá
     * tardaría segundos por mensaje — y el aviso de WhatsApp se corta si
     * tardamos, que es lo que ya hizo perder mensajes una vez.
     *
     * Primero se descartan las negaciones: "ya no lo quiero", "no me mande
     * otro". Ahí las frases de arriba también calzan, y significan lo
     * contrario.
     */
    public static function quierePedir(?string $texto): bool
    {
        $t = mb_strtolower(trim((string) $texto));
        if ($t === '' || mb_strlen($t) < 4) return false;

        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $t = preg_replace('/\s+/u', ' ', $t);

        // "no lo quiero", "ya no quiero", "todavía no me mande", "tampoco"
        if (preg_match(
            '/\b(no|ya\s+no|todavia\s+no|aun\s+no|tampoco|nunca)\s+(me\s+)?(lo\s+|los\s+|la\s+|las\s+)?'
            . '(quiero|quisiera|deseo|necesito|ocupo|mande|mandes|envie|envies|traiga)\b/',
            $t
        )) {
            return false;
        }

        foreach (static::QUIERE_PEDIR as $patron) {
            if (preg_match($patron, $t)) return true;
        }

        return false;
    }

    /** ¿Este texto lleva el enlace de rastreo? */
    public static function llevaRastreo(?string $texto): bool
    {
        $t = trim((string) $texto);
        if ($t === '') return false;

        return mb_stripos($t, '/rastreo') !== false;
    }

    /**
     * Se llama con cada mensaje nuevo, venga de donde venga.
     *
     * EL RECORRIDO COMPLETO, en el orden en que pasa:
     *
     *   orden de envío en el chat   → Pedidos
     *   enlace de rastreo mandado   → Preparados      (acá)
     *   foto de la etiqueta mandada → Entregados      (FotosAlChat::mandarUna)
     *
     * Cada paso tiene su propia señal, y eso es lo que hace útil la lista: una
     * conversación que se quedó en Preparados es una a la que le salió el
     * enlace pero NO la foto. Con los dos pasos pegados en uno, esa diferencia
     * no se podía ver.
     *
     * El orden de las preguntas importa: primero el rastreo. Un mensaje que
     * lleva el enlace es un paso más adelante, aunque de casualidad también
     * mencione un total.
     */
    public static function alGuardarMensaje(?WaConversacion $conv, ?string $texto, bool $delCliente = false): void
    {
        if (! $conv) return;

        try {
            if (static::llevaRastreo($texto)) {
                static::marcarProcesada($conv);
                return;
            }

            if (static::pareceOrden($texto)) {
                static::marcarPedido($conv);
                return;
            }

            // El cliente dijo que quiere comprar, con cualquiera de las mil
            // formas de decirlo. Va a Pedidos para que no se pierda; si al
            // final no se concretó, lo sacás vos al depurar la pestaña.
            if ($delCliente && static::quierePedir($texto)) {
                static::marcarIntencion($conv);
            }
        } catch (\Throwable $e) {
            // Etiquetar es una comodidad. Que falle no puede costar un mensaje
            // ni tumbar el webhook.
            Log::warning('Etiquetado automático: ' . $e->getMessage());
        }
    }

    /**
     * Hay una orden nueva en esta conversación.
     *
     * Una orden nueva EMPIEZA EL RECORRIDO DE CERO. Eso significa soltar las
     * etiquetas del pedido anterior y olvidar la guía vieja que se estaba
     * vigilando.
     *
     * Sin esto pasaba algo grave: un cliente que ya había recibido su pedido
     * volvía a pedir, la conversación quedaba marcada "Pedidos" Y "Entregados"
     * a la vez, y en la siguiente revisión el vigilante miraba la guía VIEJA
     * —que seguía entregada— y le quitaba el "Pedidos". El pedido nuevo
     * desaparecía del tablero y nadie lo armaba.
     */
    public static function marcarPedido(WaConversacion $conv): void
    {
        $pedido = WaEtiqueta::porRol('pedido');
        if (! $pedido) return;

        // syncWithoutDetaching: no le toca las etiquetas que no son del
        // recorrido. Si además está marcada como "San Miguel", sigue estándolo.
        $conv->etiquetas()->syncWithoutDetaching([$pedido->id]);

        // Fuera las del recorrido anterior.
        foreach (['procesada', 'entregada'] as $rol) {
            $e = WaEtiqueta::porRol($rol);
            if ($e) $conv->etiquetas()->detach($e->id);
        }

        // Y a olvidar la guía anterior: si no, el vigilante seguiría
        // preguntando por un paquete que ya llegó hace una semana.
        try {
            $conv->forceFill([
                'guia'              => null,
                'etapa_envio'       => null,
                'entregas_seguidas' => 0,
                'revisado_at'       => null,
            ])->save();
        } catch (\Throwable $e) {
            // Si las columnas todavía no existen, no pasa nada: el etiquetado
            // es lo que importa.
        }
    }

    /**
     * El cliente dijo que quiere comprar. SOLO se le agrega "Pedidos".
     *
     * A diferencia de marcarPedido(), acá no se suelta nada ni se reinicia el
     * recorrido. Y la diferencia importa: una intención no es una orden. El
     * cliente que está en Preparados esperando su paquete y escribe "me manda
     * otro para mi hermana" sigue teniendo un paquete en camino. Si esto le
     * sacara la etiqueta de Preparados, su envío actual desaparecería de la
     * lista donde se controla.
     *
     * Así queda en las dos pestañas a la vez, que es exactamente lo que pasa:
     * tiene un pedido en curso Y quiere otro. Al depurar Pedidos, decidís.
     */
    public static function marcarIntencion(WaConversacion $conv): void
    {
        $pedido = WaEtiqueta::porRol('pedido');
        if (! $pedido) return;

        $conv->etiquetas()->syncWithoutDetaching([$pedido->id]);
    }

    /**
     * Esta conversación ya tiene su guía y su enlace mandado.
     *
     * Acá sí se quita la de "pedido": lo que se está diciendo es que pasó de
     * una etapa a la otra, y dejar las dos puestas haría que apareciera en los
     * dos filtros a la vez.
     */
    public static function marcarProcesada(WaConversacion $conv): void
    {
        $lista = WaEtiqueta::porRol('procesada');
        if ($lista) $conv->etiquetas()->syncWithoutDetaching([$lista->id]);

        $pedido = WaEtiqueta::porRol('pedido');
        if ($pedido) $conv->etiquetas()->detach($pedido->id);
    }

    /**
     * El courier confirmó la entrega.
     *
     * NO le toca el "Pedidos". Si esa etiqueta está puesta es porque llegó una
     * orden NUEVA después de este envío, y ese pedido todavía está por armarse.
     * Quitárselo acá lo borraba del tablero — que es lo que pasó.
     */
    public static function marcarEntregada(WaConversacion $conv): void
    {
        $fin = WaEtiqueta::porRol('entregada');
        if ($fin) $conv->etiquetas()->syncWithoutDetaching([$fin->id]);

        $lista = WaEtiqueta::porRol('procesada');
        if ($lista) $conv->etiquetas()->detach($lista->id);
    }

    /**
     * Se había marcado entregada y el courier se desdijo.
     *
     * Pasa: al repartidor se le va marcar entregado y lo corrige después. La
     * etiqueta tiene que poder volver, no solo avanzar — si solo avanzara, un
     * error de ellos se quedaría acá para siempre.
     */
    public static function volverAPreparada(WaConversacion $conv): void
    {
        $lista = WaEtiqueta::porRol('procesada');
        if ($lista) $conv->etiquetas()->syncWithoutDetaching([$lista->id]);

        $fin = WaEtiqueta::porRol('entregada');
        if ($fin) $conv->etiquetas()->detach($fin->id);
    }
}

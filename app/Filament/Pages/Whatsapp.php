<?php

namespace App\Filament\Pages;

use App\Models\WaConversacion;
use App\Models\WaMensaje;
use App\Services\WhatsappApi;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Inbox compartido de WhatsApp.
 *
 * Tres personas atendiendo el mismo número sin pisarse. No usa WebSockets: la
 * pantalla se refresca sola cada 3 segundos, que para tres agentes y unos 50
 * mensajes al día alcanza de sobra y evita tener otro servicio corriendo.
 */
class Whatsapp extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'WhatsApp';
    protected static ?string $title = 'WhatsApp';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.whatsapp';

    /** Conversación abierta en el panel derecho. */
    public ?int $abierta = null;

    /** Lo que el agente está escribiendo. */
    public string $texto = '';

    /** Filtro de la lista de la izquierda. */
    public string $buscar = '';

    /** Mostrar solo las que nadie ha tomado. */
    public bool $soloSinTomar = false;

    /** Filtro por etiqueta. null = todas. */
    public ?int $filtroEtiqueta = null;

    /** Qué pestaña se ve a la derecha: chat, pedido o respuestas. */
    public string $pestana = 'chat';

    // ── El pedido que se va armando sin salir del chat ───────────────────────
    public string $pedNombre = '';
    public string $pedTelefono = '';
    public string $pedDireccion = '';
    public string $pedMunicipio = '';
    public string $pedDepartamento = '';
    public string $pedNota = '';

    /** Productos escritos a mano, como vienen en la orden de envío. */
    public string $pedProductosTexto = '';

    /** Si se llena, manda sobre el total calculado. Viene de la orden. */
    public string $pedCobrarManual = '';

    /** El texto de la orden, para tenerlo a la vista mientras se compara. */
    public string $pedOrigen = '';

    /** Cada renglón: ['size_id' => '', 'cantidad' => 1]. */
    public array $pedLineas = [];

    public function mount(): void
    {
        $id = request()->integer('chat');
        if ($id) $this->abrir($id);
    }

    public function verPestana(string $cual): void
    {
        $this->pestana = in_array($cual, ['chat', 'pedido', 'respuestas'], true) ? $cual : 'chat';

        if ($this->pestana === 'pedido' && ! $this->pedLineas) {
            $this->agregarLinea();
        }
    }

    /** Cuántas conversaciones sin leer hay: se muestra en el menú. */
    public static function getNavigationBadge(): ?string
    {
        try {
            if (! WaConversacion::hayTabla()) return null;

            $n = WaConversacion::where('sin_leer', '>', 0)->where('archivada', false)->count();

            return $n > 0 ? (string) $n : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /** La lista de la izquierda. */
    public function conversaciones()
    {
        if (! WaConversacion::hayTabla()) return collect();

        try {
            $q = WaConversacion::with(['agente', 'etiquetas'])->where('archivada', false);

            if ($this->filtroEtiqueta) {
                $q->whereHas('etiquetas', fn ($w) => $w->where('wa_etiquetas.id', $this->filtroEtiqueta));
            }

            if (trim($this->buscar) !== '') {
                $b = trim($this->buscar);
                $d = preg_replace('/\D/', '', $b);

                $q->where(function ($w) use ($b, $d) {
                    $w->where('nombre', 'like', '%' . $b . '%');
                    if ($d !== '') $w->orWhere('telefono', 'like', '%' . $d . '%');
                });
            }

            if ($this->soloSinTomar) $q->whereNull('agente_id');

            return $q->orderByDesc('ultimo_mensaje_at')->limit(60)->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function conversacion(): ?WaConversacion
    {
        if (! $this->abierta) return null;

        try {
            return WaConversacion::with(['agente', 'etiquetas'])->find($this->abierta);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Etiquetas ────────────────────────────────────────────────────────────

    public function etiquetas()
    {
        return \App\Models\WaEtiqueta::todas();
    }

    /** Pone o quita la etiqueta de la conversación abierta, con un toque. */
    public function alternarEtiqueta(int $id): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        try {
            if ($conv->etiquetas->contains($id)) {
                $conv->etiquetas()->detach($id);
            } else {
                $conv->etiquetas()->attach($id);
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo cambiar la etiqueta')
                ->body($e->getMessage())
                ->danger()->send();
        }
    }

    public function filtrarPor(?int $id): void
    {
        $this->filtroEtiqueta = ($this->filtroEtiqueta === $id) ? null : $id;
    }

    /** Cuántas conversaciones hay en cada etiqueta, para el contador. */
    public function cuentaEtiquetas(): array
    {
        try {
            return \App\Models\WaEtiqueta::withCount(['conversaciones' => fn ($q) =>
                $q->where('archivada', false)
            ])->pluck('conversaciones_count', 'id')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Los mensajes del chat abierto, del más viejo al más nuevo. */
    public function mensajes()
    {
        if (! $this->abierta) return collect();

        try {
            return WaMensaje::with('agente')
                ->where('conversacion_id', $this->abierta)
                ->orderBy('id')
                ->limit(200)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** Abre un chat y lo marca como leído. */
    public function abrir(int $id): void
    {
        $this->abierta = $id;
        $this->texto = '';
        $this->pestana = 'chat';

        $conv = $this->conversacion();
        if (! $conv) return;

        // Se precarga lo que ya sabemos del cliente, para no volver a pedírselo.
        $this->cargarDatosDelCliente($conv);

        if ($conv->sin_leer > 0) {
            $conv->sin_leer = 0;
            $conv->save();

            // Las dos palomitas azules del lado del cliente.
            $ultimo = WaMensaje::where('conversacion_id', $conv->id)
                ->where('direccion', 'entrante')
                ->whereNotNull('wa_message_id')
                ->orderByDesc('id')->first();

            if ($ultimo) WhatsappApi::marcarLeido($ultimo->wa_message_id);
        }
    }

    /** La flecha de volver del teléfono: cierra el chat y muestra la lista. */
    public function cerrarChat(): void
    {
        $this->abierta = null;
        $this->texto = '';
        $this->pestana = 'chat';
    }

    /** "Tomar" el chat para que los demás sepan que lo estás atendiendo. */
    public function tomar(): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        $conv->agente_id = auth()->id();
        $conv->tomada_at = now();
        $conv->save();
    }

    public function soltar(): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        $conv->agente_id = null;
        $conv->tomada_at = null;
        $conv->save();
    }

    public function archivar(): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        $conv->archivada = true;
        $conv->save();

        $this->abierta = null;

        Notification::make()->title('Conversación archivada')->success()->send();
    }

    /** Manda el mensaje que escribió el agente. */
    public function enviar(): void
    {
        $conv  = $this->conversacion();
        $texto = trim($this->texto);

        if (! $conv || $texto === '') return;

        if (! $conv->ventanaAbierta()) {
            Notification::make()
                ->title('La ventana de 24 horas está cerrada')
                ->body('WhatsApp solo deja escribir libremente dentro de las 24 horas '
                     . 'desde el último mensaje del cliente. Fuera de eso, únicamente plantillas aprobadas.')
                ->warning()->persistent()->send();
            return;
        }

        // Al contestar, el chat queda tuyo.
        if (! $conv->agente_id) $this->tomar();

        $mensaje = WhatsappApi::enviarTexto($conv, $texto, auth()->id());

        $this->texto = '';

        if ($mensaje->estado === 'fallido') {
            Notification::make()
                ->title('No se pudo enviar')
                ->body($mensaje->error ?: 'Meta rechazó el mensaje.')
                ->danger()->persistent()->send();
        }
    }

    // ═══ Catálogo por talla ══════════════════════════════════════════════════

    /**
     * Las tallas que hoy tienen algo que vender.
     *
     * Se sacan del inventario y no de una lista fija: si mañana entra una talla
     * nueva, el botón aparece solo. Y si una se agota, desaparece.
     */
    public function tallasDisponibles(): array
    {
        try {
            $filas = \App\Models\ProductSize::with('product')
                ->where('price', '>', 0)
                ->where('quantity', '>', 0)
                ->whereHas('product', fn ($q) => $q->where('active', true))
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $tallas = [];

        foreach ($filas as $s) {
            $t = trim((string) $s->size);
            if ($t === '') continue;

            $tallas[$t] = ($tallas[$t] ?? 0) + 1;
        }

        // Primero las de bebé en su orden natural, después lo demás alfabético.
        $orden = ['RN', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

        uksort($tallas, function ($a, $b) use ($orden) {
            $ia = array_search(mb_strtoupper($a), $orden, true);
            $ib = array_search(mb_strtoupper($b), $orden, true);

            if ($ia !== false && $ib !== false) return $ia <=> $ib;
            if ($ia !== false) return -1;
            if ($ib !== false) return 1;

            return strnatcasecmp($a, $b);
        });

        return $tallas;
    }

    // ── La ventana del catálogo ──────────────────────────────────────────────

    public bool $catalogoAbierto = false;
    public ?string $tallaElegida = null;

    /** Los identificadores de presentación marcados para mandar. */
    public array $elegidas = [];

    public function abrirCatalogo(): void
    {
        $this->catalogoAbierto = true;
        $this->tallaElegida = null;
        $this->elegidas = [];
    }

    public function cerrarCatalogo(): void
    {
        $this->catalogoAbierto = false;
        $this->tallaElegida = null;
        $this->elegidas = [];
    }

    /**
     * Al entrar a una talla vienen todos marcados.
     *
     * Es lo que casi siempre quiere: mandar todo lo que hay en esa talla.
     * Desmarcar dos es más rápido que marcar seis.
     */
    public function elegirTalla(string $talla): void
    {
        $this->tallaElegida = $talla;
        $this->elegidas = $this->presentacionesDe($talla)->pluck('id')->map(fn ($i) => (string) $i)->all();
    }

    public function volverATallas(): void
    {
        $this->tallaElegida = null;
        $this->elegidas = [];
    }

    public function alternarProducto(string $id): void
    {
        if (in_array($id, $this->elegidas, true)) {
            $this->elegidas = array_values(array_diff($this->elegidas, [$id]));
        } else {
            $this->elegidas[] = $id;
        }
    }

    /** Las presentaciones con existencia de una talla. */
    public function presentacionesDe(string $talla)
    {
        try {
            return \App\Models\ProductSize::with('product')
                ->where('size', $talla)
                ->where('price', '>', 0)
                ->where('quantity', '>', 0)
                ->whereHas('product', fn ($q) => $q->where('active', true))
                ->get()
                ->sortBy(fn ($s) => $s->product->orden ?? 0)
                ->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Manda las presentaciones marcadas, con foto y precio.
     *
     * Va una imagen por producto, cada una con su pie. Son varios mensajes,
     * pero dentro de la ventana de 24 horas Meta cobra por conversación y no
     * por mensaje, así que no cuesta más que mandar uno.
     */
    public function enviarElegidas(): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        if (! $conv->ventanaAbierta()) {
            Notification::make()
                ->title('La ventana de 24 horas está cerrada')
                ->body('Hay que esperar a que el cliente escriba de nuevo.')
                ->warning()->send();
            return;
        }

        if (empty($this->elegidas)) {
            Notification::make()->title('No marcaste ningún producto')->warning()->send();
            return;
        }

        if (! $conv->agente_id) $this->tomar();

        try {
            $filas = \App\Models\ProductSize::with('product')
                ->whereIn('id', $this->elegidas)
                ->get()
                ->sortBy(fn ($s) => $s->product->orden ?? 0);
        } catch (\Throwable $e) {
            Notification::make()->title('No se pudo leer el inventario')->danger()->send();
            return;
        }

        if ($filas->isEmpty()) {
            Notification::make()->title('No quedó nada por mandar')->warning()->send();
            return;
        }

        $talla = $this->tallaElegida ?: '';
        $mandados = 0;
        $fallados = 0;
        $sinFoto  = [];

        foreach ($filas as $s) {
            $p = $s->product;
            if (! $p) continue;

            $pie = '*' . trim((string) $p->name) . "*\n"
                . 'Talla ' . trim((string) $s->size);

            if ((int) ($s->unidades ?? 0) > 0) {
                $pie .= ' · ' . (int) $s->unidades . ' unidades';
            }

            $pie .= "\n$" . number_format((float) $s->price, 2);

            if ($s->combo_qty > 0 && $s->combo_price > 0) {
                $pie .= ' · ' . (int) $s->combo_qty . ' x $' . number_format((float) $s->combo_price, 2);
            }

            $relativa = $this->fotoDe($s, $p);

            if (! $relativa) {
                $sinFoto[] = $p->name;
                $m = WhatsappApi::enviarTexto($conv, $pie, auth()->id());
            } else {
                $absoluta = str_starts_with($relativa, 'http') ? $relativa : url($relativa);
                $m = WhatsappApi::enviarImagen($conv, $absoluta, $pie, auth()->id());
            }

            $m->estado === 'fallido' ? $fallados++ : $mandados++;
        }

        if ($fallados > 0) {
            Notification::make()
                ->title("Se mandaron {$mandados}, fallaron {$fallados}")
                ->body('Mirá el chat: cada mensaje que falló dice por qué.')
                ->warning()->persistent()->send();
        } else {
            $aviso = "Se mandaron {$mandados} " . ($mandados === 1 ? 'producto' : 'productos');
            if ($talla !== '') $aviso .= " en talla {$talla}";
            if ($sinFoto) $aviso .= ' (' . count($sinFoto) . ' sin foto, fueron como texto)';

            Notification::make()->title($aviso)->success()->send();
        }

        $this->cerrarCatalogo();
    }

    /**
     * La foto que mejor representa esa presentación.
     *
     * Primero la de la talla, porque muestra el empaque exacto que va a
     * recibir; si no tiene, la del producto. Devuelve la ruta tal como la sirve
     * el sitio, sin el dominio.
     */
    /** Para avisar en la ventana cuál va a salir como texto por falta de foto. */
    public function tieneFoto($size): bool
    {
        return filled($size->product ? $this->fotoDe($size, $size->product) : null);
    }

    private function fotoDe($size, $producto): ?string
    {
        if (filled($size->image_upload ?? null)) {
            return '/storage/' . ltrim($size->image_upload, '/');
        }

        if (filled($size->image ?? null)) {
            return \App\Models\Product::urlDe($size->image);
        }

        return $producto->imageUrl();
    }

    /**
     * Arma el catálogo con los precios de verdad y lo deja listo para mandar.
     *
     * Sale de la base, así que si cambiás un precio en el admin, el mensaje
     * cambia solo. No hay una lista escrita a mano que se quede vieja.
     *
     * Solo van las presentaciones con existencia: mandarle a un cliente algo
     * que no se le puede vender es la manera más rápida de quedar mal.
     */
    public function mandarCatalogo(): void
    {
        try {
            $productos = \App\Models\Product::with('sizes')
                ->where('active', true)
                ->orderBy('orden')
                ->orderBy('name')
                ->get();
        } catch (\Throwable $e) {
            Notification::make()->title('No se pudo leer el catálogo')->danger()->send();
            return;
        }

        $t = "\u{1F6D2} *Cat\u{E1}logo Baby-Confort*\n";
        $cortado = false;
        $hubo = false;

        foreach ($productos as $p) {
            $lineas = [];

            foreach ($p->sizes as $s) {
                $precio = (float) $s->price;

                // Sin precio no dice nada; agotada no se puede vender.
                if ($precio <= 0 || (int) $s->quantity <= 0) continue;

                $l = '• ' . trim((string) $s->size);

                if ((int) ($s->unidades ?? 0) > 0) {
                    $l .= ' · ' . (int) $s->unidades . ' uds';
                }

                $l .= ' — $' . number_format($precio, 2);

                if ($s->combo_qty > 0 && $s->combo_price > 0) {
                    $l .= ' · ' . (int) $s->combo_qty . ' x $' . number_format((float) $s->combo_price, 2);
                }

                $lineas[] = $l;
            }

            if (! $lineas) continue;

            $bloque = "\n*" . trim((string) $p->name) . "*\n" . implode("\n", $lineas) . "\n";

            // WhatsApp corta a los 4096 caracteres: mejor cortar nosotros bien
            // que dejar que corte él a la mitad de un precio.
            if (mb_strlen($t . $bloque) > 3500) {
                $cortado = true;
                break;
            }

            $t .= $bloque;
            $hubo = true;
        }

        if (! $hubo) {
            Notification::make()
                ->title('No hay presentaciones con existencia')
                ->body('El catálogo saldría vacío. Revisá las cantidades en el admin.')
                ->warning()->send();
            return;
        }

        if ($cortado) {
            $t .= "\n_...y m\u{E1}s productos en el enlace._\n";
        }

        $t .= "\n\u{1F517} *Ver todo con fotos:*\n" . url('/');

        $this->texto = $t;
        $this->pestana = 'chat';
    }

    /** Manda la tabla de tallas a mano, sin esperar al disparador. */
    public function mandarTallas(): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        $texto = \App\Services\AutoRespuestas::tablaDeTallas(
            config('auto-respuestas.disparadores.0', [])
        );

        if ($texto === '') return;

        $this->texto = $texto;
    }

    /**
     * Baja una imagen que en su momento no se pudo guardar.
     *
     * Pasa cuando el mensaje llegó antes de que el identificador de acceso
     * estuviera bien puesto: el mensaje quedó registrado, pero la foto no.
     * Meta guarda los archivos un tiempo, así que casi siempre se recupera.
     */
    public function bajarImagen(int $id): void
    {
        $m = WaMensaje::find($id);

        if (! $m || ! $m->media_id) {
            Notification::make()
                ->title('Ese mensaje no trae imagen')
                ->warning()->send();
            return;
        }

        $ruta = WhatsappApi::bajarMedia($m->media_id);

        if (! $ruta) {
            Notification::make()
                ->title('No se pudo recuperar')
                ->body('Meta guarda los archivos unos 30 días. Si el mensaje es viejo, '
                     . 'lo más probable es que ya no esté. Pedile al cliente que la reenvíe.')
                ->warning()->persistent()->send();
            return;
        }

        $m->update(['media_ruta' => $ruta]);
    }

    // ═══ Pestaña "Respuestas rápidas" ═══════════════════════════════════════

    public function respuestas()
    {
        return \App\Models\RespuestaRapida::paraElChat();
    }

    /**
     * Pone la respuesta en el cuadro de texto, sin mandarla.
     *
     * A propósito no se envía de una: casi siempre hay que agregarle el nombre
     * del cliente o cambiar un precio, y un botón que manda solo termina
     * mandando cosas a medias.
     */
    public function usarRespuesta(int $id): void
    {
        $r = \App\Models\RespuestaRapida::find($id);
        if (! $r) return;

        $this->texto = $r->texto;
        $this->pestana = 'chat';
    }

    // ═══ Pestaña "Tomar pedido" ═════════════════════════════════════════════

    /** Lo que ya sabemos de quien está escribiendo. */
    private function cargarDatosDelCliente(WaConversacion $conv): void
    {
        $this->pedTelefono = $conv->telefono ?: '';
        $this->pedNombre   = $conv->comoSeLlama() ?: '';

        $cliente = $conv->cliente();
        if ($cliente) {
            $this->pedNombre       = $cliente['nombre'] ?: $this->pedNombre;
            $this->pedDireccion    = $cliente['direccion'] ?? '';
            $this->pedMunicipio    = $cliente['municipio'] ?? '';
            $this->pedDepartamento = $cliente['departamento'] ?? '';
        }
    }

    /** ¿Ya nos compró antes? Se muestra arriba del formulario. */
    public function clienteConocido(): ?array
    {
        $conv = $this->conversacion();
        return $conv ? $conv->cliente() : null;
    }

    /** Todas las presentaciones con precio, para el desplegable. */
    public function opcionesProductos(): array
    {
        try {
            $filas = \App\Models\ProductSize::with('product')
                ->whereHas('product', fn ($q) => $q->where('active', true))
                ->get();

            $lista = [];
            foreach ($filas as $s) {
                if (! $s->product) continue;

                $etiqueta = $s->product->name . ' · ' . $s->size
                    . ' — $' . number_format((float) $s->price, 2);

                $lista[$s->id] = $etiqueta;
            }

            asort($lista);
            return $lista;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function agregarLinea(): void
    {
        $this->pedLineas[] = ['size_id' => '', 'cantidad' => 1];
    }

    public function quitarLinea(int $i): void
    {
        unset($this->pedLineas[$i]);
        $this->pedLineas = array_values($this->pedLineas);
    }

    /** Suma de productos, sin envío. */
    public function subtotalPedido(): float
    {
        $total = 0.0;

        foreach ($this->pedLineas as $l) {
            $s = $this->presentacion($l['size_id'] ?? null);
            if (! $s) continue;

            $total += (float) $s->price * max(1, (int) ($l['cantidad'] ?? 1));
        }

        return round($total, 2);
    }

    public function envioPedido(): float
    {
        return \App\Models\Setting::envioPara($this->subtotalPedido());
    }

    /**
     * Lo que se le cobra al cliente.
     *
     * Si la orden traía un total escrito, ese manda: es el número que el
     * cliente ya vio y aceptó. Recalcularlo por nuestra cuenta sería cambiarle
     * el precio después de haberlo acordado.
     */
    public function totalPedido(): float
    {
        if (trim($this->pedCobrarManual) !== '') {
            return round((float) $this->pedCobrarManual, 2);
        }

        // Sin total escrito y sin productos del catálogo no hay nada que
        // cobrar. Mostrar el costo de envío suelto solo confundiría.
        if (empty($this->pedLineas)) return 0.0;

        return round($this->subtotalPedido() + $this->envioPedido(), 2);
    }

    public function totalEsManual(): bool
    {
        return trim($this->pedCobrarManual) !== '';
    }

    private function presentacion($id)
    {
        if (! $id) return null;

        try {
            return \App\Models\ProductSize::with('product')->find($id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * El texto que va en la columna de contenido de la guía.
     *
     * Sale tal cual de la orden: es lo que el cliente leyó y confirmó. Si
     * quedó escrito en varios renglones, se juntan con coma, que es como lo
     * espera el Excel.
     */
    public function descripcionPedido(): string
    {
        $t = trim($this->pedProductosTexto);
        if ($t === '') return '';

        $partes = [];

        foreach (preg_split('/\r\n|\n|\r/u', $t) as $linea) {
            // Se quitan viñetas y palomitas que vengan pegadas de la orden.
            $l = trim(preg_replace('/^[\s\-\x{2013}\x{2014}\x{00B7}\x{2022}*>\x{2705}]+/u', '', $linea));
            if ($l !== '') $partes[] = $l;
        }

        return implode(', ', $partes);
    }

    /**
     * Arma la orden de envío en blanco y la deja en el cuadro de escribir.
     *
     * Viene con lo que ya sabemos del cliente puesto: nombre, teléfono,
     * municipio y dirección de la última entrega. Solo queda escribir los
     * productos y los montos, mandarla, y después procesarla con el botón que
     * aparece debajo del mensaje.
     */
    public function plantillaOrden(): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        $cliente = $conv->cliente();

        $nombre    = $cliente['nombre'] ?? ($conv->comoSeLlama() ?: '');
        $municipio = $cliente['municipio'] ?? '';
        $direccion = $cliente['direccion'] ?? '';

        $this->texto = "\u{1F4E6} Orden de Env\u{ED}o: \u{1F69A}\n"
            . "\u{2705} Nombre completo: {$nombre}\n"
            . "\u{2705} Tel\u{E9}fono: {$conv->telefono}\n"
            . "\u{2705} Municipio: {$municipio}\n"
            . "\u{2705} Direcci\u{F3}n exacta: {$direccion}\n"
            . "\u{2705} Producto(s): \n"
            . "\u{2705} Costo de env\u{ED}o: $\n"
            . "\u{1F4B0} Total a pagar: $\n\n"
            . "\u{2728} \u{A1}Gracias por tu preferencia! Tu pedido estar\u{E1} en camino muy pronto";

        $this->pestana = 'chat';

        if ($cliente) {
            Notification::make()
                ->title('Orden armada con los datos que ya teníamos')
                ->body('Completá los productos y los montos antes de mandarla.')
                ->success()->send();
        }
    }

    // ── Procesar una orden de envío escrita en el chat ───────────────────────

    /**
     * Toma el mensaje de la orden y llena el formulario con lo que dice.
     *
     * El texto original queda pegado arriba del formulario a propósito: la
     * gracia es poder comparar renglón por renglón sin cambiar de ventana, que
     * es justo lo que no se podía hacer antes.
     */
    public function procesarOrden(int $mensajeId): void
    {
        $m = WaMensaje::find($mensajeId);
        if (! $m || blank($m->texto)) return;

        $this->pedOrigen = $m->texto;
        $datos = $this->leerOrden($m->texto);

        if (filled($datos['nombre']))    $this->pedNombre      = $datos['nombre'];
        if (filled($datos['telefono']))  $this->pedTelefono    = $datos['telefono'];
        if (filled($datos['municipio'])) $this->pedMunicipio   = $datos['municipio'];
        if (filled($datos['direccion'])) $this->pedDireccion   = $datos['direccion'];
        if (filled($datos['productos'])) $this->pedProductosTexto = $datos['productos'];
        if (filled($datos['total']))     $this->pedCobrarManual   = $datos['total'];

        $this->pestana = 'pedido';

        $leidos = count(array_filter($datos, fn ($v) => filled($v)));

        Notification::make()
            ->title($leidos > 0 ? "Se leyeron {$leidos} datos de la orden" : 'No se pudo leer la orden')
            ->body($leidos > 0
                ? 'Revisalos contra el texto original, que quedó arriba del formulario.'
                : 'Revisá que el mensaje tenga el formato de siempre, con dos puntos después de cada campo.')
            ->{$leidos > 0 ? 'success' : 'warning'}()
            ->send();
    }

    /**
     * Lee la orden de envío campo por campo.
     *
     * Busca por el nombre del campo y no por la posición del renglón, así que
     * aguanta que cambien los emojis, el orden o que se agregue una línea.
     */
    private function leerOrden(string $texto): array
    {
        $campos = [
            'nombre'    => ['nombre completo', 'nombre'],
            'telefono'  => ['telefono', 'teléfono', 'tel'],
            'municipio' => ['municipio'],
            'direccion' => ['direccion exacta', 'dirección exacta', 'direccion', 'dirección'],
            'productos' => ['producto(s)', 'productos', 'producto'],
            'envio'     => ['costo de envio', 'costo de envío', 'envio', 'envío'],
            'total'     => ['total a pagar', 'total'],
        ];

        $salida = array_fill_keys(array_keys($campos), '');

        // El campo que se está llenando. Mientras no aparezca otra etiqueta,
        // todo lo que venga abajo pertenece a este: así los productos escritos
        // en varios renglones entran completos y no solo el primero.
        $actual = null;

        foreach (preg_split('/\r\n|\n|\r/u', $texto) as $linea) {
            $cruda = rtrim($linea);

            // Fuera emojis, palomitas y viñetas del principio.
            $l = preg_replace('/^[^\p{L}\p{N}]+/u', '', trim($cruda));

            $esEtiqueta = false;

            if ($l !== '' && str_contains($l, ':')) {
                [$etiqueta, $valor] = array_map('trim', explode(':', $l, 2));
                $limpia = mb_strtolower($etiqueta);

                foreach ($campos as $clave => $alias) {
                    foreach ($alias as $a) {
                        if ($limpia === $a || str_starts_with($limpia, $a)) {
                            $actual = $clave;
                            $esEtiqueta = true;

                            if ($salida[$clave] === '') {
                                $salida[$clave] = trim($valor, " \t$");
                            }

                            break 2;
                        }
                    }
                }
            }

            if ($esEtiqueta) continue;

            // Renglón suelto: si veníamos llenando algo, se le suma.
            if ($actual === null) continue;

            $suelto = trim($cruda);
            if ($suelto === '') continue;

            // El cierre de cortesía no es parte de ningún campo.
            if (preg_match('/gracias por tu preferencia|estar\p{L}? en camino/iu', $suelto)) {
                $actual = null;
                continue;
            }

            $salida[$actual] = trim($salida[$actual] . "\n" . $suelto);
        }

        // El total viene como "$25.50": nos quedamos con el número.
        foreach (['envio', 'total'] as $k) {
            if ($salida[$k] !== '') {
                $salida[$k] = preg_replace('/[^\d.]/', '', $salida[$k]);
            }
        }

        return $salida;
    }

    /** Limpia el formulario para empezar de nuevo. */
    public function limpiarPedido(): void
    {
        $this->pedProductosTexto = '';
        $this->pedCobrarManual = '';
        $this->pedOrigen = '';
        $this->pedNota = '';
        $this->pedLineas = [];
        $this->agregarLinea();

        $conv = $this->conversacion();
        if ($conv) $this->cargarDatosDelCliente($conv);
    }

    /** Guarda el pedido en la cola de guías, lista para el Excel. */
    public function guardarPedido(): void
    {
        $faltan = [];
        if (trim($this->pedNombre) === '')    $faltan[] = 'el nombre';
        if (trim($this->pedTelefono) === '')  $faltan[] = 'el teléfono';
        if (trim($this->pedDireccion) === '') $faltan[] = 'la dirección';
        if ($this->descripcionPedido() === '') $faltan[] = 'al menos un producto';

        if ($faltan) {
            Notification::make()
                ->title('Falta ' . implode(', ', $faltan))
                ->warning()->send();
            return;
        }

        try {
            \App\Models\GuiaBorrador::create([
                'nombre'       => trim($this->pedNombre),
                'telefono'     => trim($this->pedTelefono),
                'direccion'    => trim($this->pedDireccion),
                'municipio'    => trim($this->pedMunicipio),
                'departamento' => trim($this->pedDepartamento),
                'descripcion'  => $this->descripcionPedido(),
                'cobrar'       => $this->totalPedido(),
            ]);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo guardar el pedido')
                ->body($e->getMessage())
                ->danger()->persistent()->send();
            return;
        }

        $this->limpiarPedido();
        $this->pestana = 'chat';

        Notification::make()
            ->title('Pedido guardado')
            ->body('Ya está en la cola de guías, listo para bajar el Excel.')
            ->success()->send();
    }

    /** Resumen del pedido para mandárselo al cliente y que confirme. */
    public function pasarPedidoAlChat(): void
    {
        if ($this->descripcionPedido() === '') {
            Notification::make()->title('Todavía no hay productos')->warning()->send();
            return;
        }

        $t = "*Tu pedido:*\n";

        foreach ($this->pedLineas as $l) {
            $s = $this->presentacion($l['size_id'] ?? null);
            if (! $s || ! $s->product) continue;

            $cant = max(1, (int) ($l['cantidad'] ?? 1));
            $t .= "\u{2022} {$cant} × {$s->product->name} talla {$s->size} — $"
                . number_format((float) $s->price * $cant, 2) . "\n";
        }

        $envio = $this->envioPedido();
        $t .= "\n*Envío:* " . ($envio > 0 ? '$' . number_format($envio, 2) : 'gratis \u{1F389}') . "\n";
        $t .= "*Total a pagar al recibir:* $" . number_format($this->totalPedido(), 2) . "\n\n";
        $t .= "\u{BF}Te lo confirmo as\u{ED}? \u{1F499}";

        $this->texto = $t;
        $this->pestana = 'chat';
    }

    /** Dirección de "Procesar orden": abre el armador de guías con este chat. */
    public function enlaceProcesar(): ?string
    {
        if (! $this->abierta) return null;

        return CrearGuia::getUrl() . '?wa=' . $this->abierta;
    }

    /** ¿Está configurado el enlace con Meta? Si no, se avisa arriba. */
    public function configurado(): bool
    {
        return WhatsappApi::configurado();
    }
}

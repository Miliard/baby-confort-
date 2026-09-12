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

    /** Qué pestaña se ve a la derecha: chat, pedido o respuestas. */
    public string $pestana = 'chat';

    // ── El pedido que se va armando sin salir del chat ───────────────────────
    public string $pedNombre = '';
    public string $pedTelefono = '';
    public string $pedDireccion = '';
    public string $pedMunicipio = '';
    public string $pedDepartamento = '';
    public string $pedNota = '';

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
            $q = WaConversacion::with('agente')->where('archivada', false);

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
            return WaConversacion::with('agente')->find($this->abierta);
        } catch (\Throwable $e) {
            return null;
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

    public function totalPedido(): float
    {
        return round($this->subtotalPedido() + $this->envioPedido(), 2);
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

    /** El texto que va en la columna de contenido de la guía. */
    private function descripcionPedido(): string
    {
        $partes = [];

        foreach ($this->pedLineas as $l) {
            $s = $this->presentacion($l['size_id'] ?? null);
            if (! $s || ! $s->product) continue;

            $cant = max(1, (int) ($l['cantidad'] ?? 1));
            $partes[] = $cant . ' ' . $s->product->name . ' talla ' . $s->size;
        }

        if (trim($this->pedNota) !== '') $partes[] = trim($this->pedNota);

        return implode(', ', $partes);
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

        $this->pedLineas = [];
        $this->pedNota = '';
        $this->agregarLinea();
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

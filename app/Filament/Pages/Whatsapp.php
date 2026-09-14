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

    /**
     * Mostrar solo las que están esperando respuesta.
     *
     * Reemplaza al viejo "sin tomar", que miraba si alguien tenía asignada la
     * conversación. Eso dejó de servir cuando se quitaron los botones de tomar
     * y soltar: una conversación contestada desde el teléfono seguía
     * apareciendo como "sin tomar" aunque ya estuviera respondida.
     */
    public bool $soloSinResponder = false;

    /** Mostrar solo las que tienen mensajes sin leer. */
    public bool $soloSinLeer = false;

    public function alternarSinLeer(): void
    {
        $this->soloSinLeer = ! $this->soloSinLeer;
    }

    public function alternarSinResponder(): void
    {
        $this->soloSinResponder = ! $this->soloSinResponder;
    }

    /** Cuántas están esperando respuesta. */
    public function cuantasSinResponder(): int
    {
        try {
            if (! WaConversacion::hayTabla()) return 0;

            return WaConversacion::where('archivada', false)
                ->where('ultimo_saliente', false)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Cuántas conversaciones tienen mensajes sin leer. */
    public function cuantasSinLeer(): int
    {
        try {
            if (! WaConversacion::hayTabla()) return 0;

            return WaConversacion::where('sin_leer', '>', 0)
                ->where('archivada', false)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

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
        $this->pestana = in_array($cual, ['chat', 'pedido'], true) ? $cual : 'chat';

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
                    $w->where('nombre', 'like', '%' . $b . '%')
                      ->orWhere('alias', 'like', '%' . $b . '%');
                    if ($d !== '') $w->orWhere('telefono', 'like', '%' . $d . '%');
                });
            }

            if ($this->soloSinResponder) $q->where('ultimo_saliente', false);
            if ($this->soloSinLeer)      $q->where('sin_leer', '>', 0);

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

    // ── Responder a un mensaje puntual ───────────────────────────────────────

    /** Identificador del mensaje que se está citando, si hay uno. */
    public ?int $respondiendo = null;

    public function responderA(int $id): void
    {
        $this->respondiendo = $id;
        $this->pestana = 'chat';
    }

    public function cancelarRespuesta(): void
    {
        $this->respondiendo = null;
    }

    public function mensajeCitado(): ?WaMensaje
    {
        if (! $this->respondiendo) return null;

        try {
            return WaMensaje::find($this->respondiendo);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Mejorar el texto antes de mandarlo ───────────────────────────────────

    /** Lo que había escrito antes de corregir, para poder volver atrás. */
    public string $textoAntes = '';

    public function puedeMejorar(): bool
    {
        return \App\Services\MejorarTexto::disponible();
    }

    public function mejorar(): void
    {
        $original = trim($this->texto);

        $r = \App\Services\MejorarTexto::mejorar($original);

        if (! ($r['ok'] ?? false)) {
            Notification::make()
                ->title('No se pudo corregir')
                ->body($r['error'] ?? 'Error desconocido.')
                ->warning()->send();
            return;
        }

        // Si no cambió nada, no vale la pena ofrecer deshacer.
        if ($r['texto'] === $original) {
            Notification::make()->title('Ya estaba bien escrito')->success()->send();
            return;
        }

        $this->textoAntes = $original;
        $this->texto = $r['texto'];
    }

    /** Vuelve a lo que habías escrito vos. */
    public function deshacerMejora(): void
    {
        if ($this->textoAntes === '') return;

        $this->texto = $this->textoAntes;
        $this->textoAntes = '';
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

        // Si se está citando un mensaje, se manda su identificador de Meta
        // para que al cliente le llegue con la cita arriba.
        $citado = $this->mensajeCitado();

        $mensaje = WhatsappApi::enviarTexto(
            $conv,
            $texto,
            auth()->id(),
            false,
            $citado?->wa_message_id
        );

        $this->texto = '';
        $this->textoAntes = '';
        $this->respondiendo = null;

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

    // ── El nombre que le ponemos al contacto ─────────────────────────────────

    public bool $editandoAlias = false;
    public string $aliasTexto = '';

    public function editarAlias(): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        $this->aliasTexto = (string) ($conv->alias ?? '');
        $this->editandoAlias = true;
    }

    public function guardarAlias(): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        $nuevo = trim($this->aliasTexto);

        // Vacío borra el nombre propio y vuelve a mostrarse el del perfil.
        $conv->alias = $nuevo !== '' ? mb_substr($nuevo, 0, 80) : null;
        $conv->save();

        $this->editandoAlias = false;

        // Si el cliente ya está en la libreta, se le guarda ahí también: así
        // el nombre aparece en la guía sin volver a escribirlo.
        try {
            if ($nuevo !== '' && trim($this->pedNombre) === '') {
                $this->pedNombre = $nuevo;
            }
        } catch (\Throwable $e) {
        }
    }

    public function cancelarAlias(): void
    {
        $this->editandoAlias = false;
    }

    // ── Las fotos guardadas ──────────────────────────────────────────────────

    public bool $fotosAbiertas = false;

    /** Las que están marcadas para mandar juntas. */
    public array $fotosElegidas = [];

    public function abrirFotos(): void
    {
        $this->fotosAbiertas = true;
        $this->fotosElegidas = [];
    }

    public function cerrarFotos(): void
    {
        $this->fotosAbiertas = false;
        $this->fotosElegidas = [];
    }

    public function alternarFoto(string $id): void
    {
        if (in_array($id, $this->fotosElegidas, true)) {
            $this->fotosElegidas = array_values(array_diff($this->fotosElegidas, [$id]));
        } else {
            $this->fotosElegidas[] = $id;
        }
    }

    public function marcarTodasLasFotos(): void
    {
        $todas = $this->fotosGuardadas()->pluck('id')->map(fn ($i) => (string) $i)->all();

        // Si ya estaban todas, el botón desmarca: sirve para las dos cosas.
        $this->fotosElegidas = count($this->fotosElegidas) === count($todas) ? [] : $todas;
    }

    public function fotosGuardadas()
    {
        return \App\Models\WaFoto::paraElChat();
    }

    /**
     * Manda todas las fotos marcadas, una detrás de otra.
     *
     * Van como mensajes separados porque WhatsApp no agrupa imágenes por API,
     * pero salen seguidas y al cliente le llegan juntas. Dentro de la ventana
     * de 24 horas Meta cobra por conversación, no por mensaje, así que mandar
     * seis no cuesta más que mandar una.
     */
    public function mandarFotosElegidas(): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        if (! $conv->ventanaAbierta()) {
            Notification::make()
                ->title('La ventana de 24 horas está cerrada')
                ->warning()->send();
            return;
        }

        if (empty($this->fotosElegidas)) {
            Notification::make()->title('No marcaste ninguna foto')->warning()->send();
            return;
        }

        try {
            $fotos = \App\Models\WaFoto::whereIn('id', $this->fotosElegidas)
                ->orderBy('orden')->orderBy('titulo')->get();
        } catch (\Throwable $e) {
            Notification::make()->title('No se pudieron leer las fotos')->danger()->send();
            return;
        }

        if ($fotos->isEmpty()) {
            Notification::make()->title('Esas fotos ya no están')->warning()->send();
            return;
        }

        if (! $conv->agente_id) $this->tomar();

        $mandadas = 0;
        $fallaron = 0;

        foreach ($fotos as $f) {
            $url = $f->urlCompleta();
            if (! $url) { $fallaron++; continue; }

            $m = WhatsappApi::enviarImagen($conv, $url, $f->pie ?: null, auth()->id());
            $m->estado === 'fallido' ? $fallaron++ : $mandadas++;
        }

        $this->cerrarFotos();

        if ($fallaron > 0) {
            Notification::make()
                ->title("Se mandaron {$mandadas}, fallaron {$fallaron}")
                ->body('Mirá el chat: cada una que falló dice por qué.')
                ->warning()->persistent()->send();
        } else {
            Notification::make()
                ->title("Se {$this->conjugar($mandadas)} {$mandadas} " . ($mandadas === 1 ? 'foto' : 'fotos'))
                ->success()->send();
        }
    }

    private function conjugar(int $n): string
    {
        return $n === 1 ? 'mandó' : 'mandaron';
    }

    // ── La ventana del catálogo ──────────────────────────────────────────────

    public bool $catalogoAbierto = false;
    public ?string $tallaElegida = null;

    /** Los identificadores de presentación marcados para mandar. */
    public array $elegidas = [];

    /** Mandar también las fotos del producto puesto, no solo el paquete. */
    public bool $conFotosUso = false;

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

            // Cada dato en su renglón y con aire entre bloques. Todo pegado
            // el cliente no lo lee: se le va la vista y pregunta lo que ya
            // estaba escrito.
            $pie = '*' . trim((string) $p->name) . "*\n\n"
                . '*Talla:* ' . trim((string) $s->size) . "\n";

            if ((int) ($s->unidades ?? 0) > 0) {
                $pie .= '*Contiene:* ' . (int) $s->unidades . " unidades\n";
            }

            $pie .= '*Precio:* $' . number_format((float) $s->price, 2) . "\n";

            if ($s->combo_qty > 0 && $s->combo_price > 0) {
                $pie .= '*Llevando ' . (int) $s->combo_qty . ':* $'
                    . number_format((float) $s->combo_price, 2) . "\n";
            }

            // El enlace a su página: la foto sirve para que mire, el enlace
            // para que entre a la tienda y pida sin tener que escribir. Va con
            // la talla adelantada para que le abra la que están hablando.
            try {
                $pie .= "\n*Miralo aqu\u{ED}:*\n"
                    . route('store.show', $p) . '?t=' . rawurlencode(trim((string) $s->size));
            } catch (\Throwable $e) {
                // Si la ruta cambiara, mejor mandar el producto sin enlace que
                // no mandarlo.
            }

            $cierre = trim((string) config('whatsapp.pie_catalogo', ''));
            if ($cierre !== '') $pie .= "\n\n" . $cierre;

            $relativa = $this->fotoDe($s, $p);

            if (! $relativa) {
                $sinFoto[] = $p->name;
                $m = WhatsappApi::enviarTexto($conv, $pie, auth()->id());
            } else {
                $absoluta = str_starts_with($relativa, 'http') ? $relativa : url($relativa);
                $m = WhatsappApi::enviarImagen($conv, $absoluta, $pie, auth()->id());
            }

            $m->estado === 'fallido' ? $fallados++ : $mandados++;

            // Las fotos del producto ya puesto. Salen de la galería del
            // producto en el admin, después de la principal.
            if ($this->conFotosUso) {
                foreach ($this->fotosDeUso($p) as $uso) {
                    $mu = WhatsappApi::enviarImagen($conv, $uso, null, auth()->id());
                    $mu->estado === 'fallido' ? $fallados++ : $mandados++;
                }
            }
        }

        // Sin mensaje de cierre con el enlace de la talla: cada foto ya lleva
        // el enlace de su producto, así que sería un tercer enlace repetido.

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
    // ── Municipio y departamento ─────────────────────────────────────────────

    /**
     * Cada vez que se escribe el municipio, se revisa contra el departamento.
     *
     * Si no hay duda, el departamento se completa solo. Nació de una guía que
     * salió con "San Vicente, San Salvador" y se fue para el otro lado del
     * país.
     */
    public function updatedPedMunicipio(): void
    {
        $M = \App\Services\Municipios::class;

        // Se corrige la escritura (tildes, mayúsculas) si lo reconocemos.
        $bueno = $M::nombreBueno($this->pedMunicipio);
        if ($bueno) $this->pedMunicipio = $bueno;

        // El departamento se deduce del municipio, siempre que no haya duda.
        // Si el que estaba puesto no corresponde, se reemplaza: la tabla manda
        // sobre lo que haya quedado escrito antes.
        $seguro = $M::departamentoSeguro($this->pedMunicipio);

        if ($seguro) $this->pedDepartamento = $seguro;
    }

    public function updatedPedDepartamento(): void
    {
        // Solo para que la revisión de abajo se actualice al escribir.
    }

    /** Lo que se muestra debajo de los campos. */
    public function revisionZona(): array
    {
        return \App\Services\Municipios::revisar($this->pedMunicipio, $this->pedDepartamento);
    }

    public function departamentos(): array
    {
        return \App\Services\Municipios::departamentos();
    }

    /** Cuántas guías hay esperando en la cola, listas para el Excel. */
    public function enCola(): int
    {
        try {
            return \App\Models\GuiaBorrador::lista()->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Dirección de la pantalla donde se baja el Excel. */
    public function enlaceCola(): ?string
    {
        try {
            return CrearGuia::getUrl();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Las fotos del producto ya puesto, sacadas de la galería del admin.
     *
     * Se saltea la primera, que es la del paquete y ya se mandó. Van dos como
     * mucho: el cliente quiere ver cómo queda, no un álbum.
     */
    public function fotosDeUso($producto): array
    {
        try {
            $todas = $producto->galleryUrls();
        } catch (\Throwable $e) {
            return [];
        }

        // La primera es la principal: esa ya salió con su precio.
        $extra = array_slice(array_filter($todas), 1, 2);

        return array_map(
            fn ($u) => str_starts_with($u, 'http') ? $u : url($u),
            $extra
        );
    }

    /** Cuántas fotos de uso hay entre lo que se va a mandar. */
    public function cuantasFotosUso(): int
    {
        if (empty($this->elegidas)) return 0;

        try {
            $filas = \App\Models\ProductSize::with('product')
                ->whereIn('id', $this->elegidas)->get();
        } catch (\Throwable $e) {
            return 0;
        }

        $n = 0;
        $vistos = [];

        foreach ($filas as $s) {
            if (! $s->product || isset($vistos[$s->product->id])) continue;

            $vistos[$s->product->id] = true;
            $n += count($this->fotosDeUso($s->product));
        }

        return $n;
    }

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
        $this->pedNombre   = $this->limpiarNombre($conv->comoSeLlama() ?: '');

        $cliente = $conv->cliente();
        if ($cliente) {
            $this->pedNombre       = $this->limpiarNombre($cliente['nombre'] ?? '') ?: $this->pedNombre;
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
            // Sin enlace de rastreo a propósito: ese sale solo al guardar la
            // guía en la cola. Así, mirando el chat, se sabe de un vistazo
            // cuáles órdenes ya se procesaron y cuáles quedaron a medias.
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

        if (filled($datos['nombre']))    $this->pedNombre    = $this->limpiarNombre($datos['nombre']);
        if (filled($datos['telefono']))  $this->pedTelefono  = $datos['telefono'];
        if (filled($datos['direccion'])) $this->pedDireccion = $datos['direccion'];

        $this->resolverZona($datos['municipio'] ?? '', $datos['direccion'] ?? '');
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
     * Decide el municipio y el departamento de una orden.
     *
     * La regla de fondo: **el departamento no se lee del texto, se deduce del
     * municipio**. La tabla de municipios es la que manda. Si el intérprete se
     * confunde repartiendo los renglones, o si en la orden quedó escrito un
     * departamento que no corresponde, acá se corrige igual.
     *
     * Busca el municipio en tres lugares, en este orden:
     *   1. El campo "Municipio", quedándose con lo de antes de la coma.
     *   2. Ese mismo campo completo, por si venía mezclado con otra cosa.
     *   3. La dirección, que casi siempre repite el municipio al final.
     */
    private function resolverZona(string $municipioCrudo, string $direccion): void
    {
        $M = \App\Services\Municipios::class;

        $encontrado = null;

        if (trim($municipioCrudo) !== '') {
            // "Nueva Concepción, Chalatenango" → se prueba con lo de antes de
            // la coma, que es donde va el municipio.
            $antesDeComa = trim(explode(',', $municipioCrudo)[0]);

            $encontrado = $M::existe($antesDeComa)
                ? $M::nombreBueno($antesDeComa)
                : $M::buscarEn($municipioCrudo);
        }

        // Último recurso: la dirección. "…los chilamates nueva concepción
        // chalatenango" trae el municipio aunque el campo haya salido mal.
        if (! $encontrado && trim($direccion) !== '') {
            $encontrado = $M::buscarEn($direccion);
        }

        if (! $encontrado) {
            // No se reconoció nada: se deja lo que vino y que la pantalla avise.
            if (trim($municipioCrudo) !== '') {
                $this->pedMunicipio = trim(explode(',', $municipioCrudo)[0]);
            }
            return;
        }

        $this->pedMunicipio = $encontrado;

        // Y acá lo importante: el departamento sale de la tabla, no del texto.
        $seguro = $M::departamentoSeguro($encontrado);

        if ($seguro) {
            $this->pedDepartamento = $seguro;
            return;
        }

        // Nombre que existe en varios departamentos: solo ahí se mira lo que
        // decía la orden, y únicamente si es uno de los posibles.
        $posibles = $M::departamentosDe($encontrado);
        $escrito = trim(explode(',', $municipioCrudo)[1] ?? '');

        foreach ($posibles as $p) {
            if ($escrito !== '' && $M::normalizar($p) === $M::normalizar($escrito)) {
                $this->pedDepartamento = $p;
                return;
            }

            if ($M::buscarEn($direccion) === null && str_contains($M::normalizar($direccion), $M::normalizar($p))) {
                $this->pedDepartamento = $p;
                return;
            }
        }

        // Sigue habiendo duda: se deja vacío para que la pantalla lo pregunte.
        $this->pedDepartamento = '';
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

    /**
     * Saca el teléfono de adentro del nombre.
     *
     * Pasa seguido: el cliente escribe "+503 6031 6911 Carlos Chicas" en el
     * renglón del nombre, o así se llama su perfil de WhatsApp. Ese texto se
     * iba tal cual a la guía y el Excel salía con el número pegado al nombre,
     * que además ya va en su propia columna.
     *
     * Si al quitar el número no queda nada (o sea: nunca hubo nombre), se
     * devuelve lo de antes. Vale más que quede el número a que quede vacío y
     * la guía salga sin a quién entregarle.
     */
    private function limpiarNombre(string $crudo): string
    {
        $n = trim($crudo);
        if ($n === '') return '';

        // El código de país, con o sin más y con cualquier separador.
        $limpio = preg_replace('/(?<!\d)\+?503[\s.\-]*/u', ' ', $n);

        // Un número salvadoreño de ocho dígitos, partido ("6031 6911") o no.
        $limpio = preg_replace('/(?<!\d)[267]\d{3}[\s.\-]?\d{4}(?!\d)/u', ' ', $limpio);

        $limpio = preg_replace('/\s{2,}/u', ' ', (string) $limpio);
        $limpio = trim((string) $limpio, " \t.,;:\u{00A0}-");

        return $limpio !== '' ? $limpio : $n;
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
        if (trim($this->pedMunicipio) === '') $faltan[] = 'el municipio';
        if ($this->descripcionPedido() === '') $faltan[] = 'al menos un producto';

        if ($faltan) {
            Notification::make()
                ->title('Falta ' . implode(', ', $faltan))
                ->warning()->send();
            return;
        }

        // La zona se revisa acá y no solo en pantalla: es la última puerta
        // antes de que la guía entre a la cola y se baje al Excel.
        $zona = $this->revisionZona();

        if (in_array($zona['estado'], ['error', 'ambiguo'], true)) {
            Notification::make()
                ->title('Revisá el municipio y el departamento')
                ->body($zona['mensaje'] . ' Un paquete con el departamento equivocado se pierde el viaje.')
                ->danger()->persistent()->send();
            return;
        }

        if ($zona['estado'] === 'desconocido') {
            Notification::make()
                ->title('Ese municipio no está en la lista')
                ->body($zona['mensaje'] . ' Si estás seguro de que existe, decímelo y lo agrego.')
                ->warning()->persistent()->send();
            return;
        }

        // Si quedó vacío y se puede deducir, se completa sin molestar.
        if (trim($this->pedDepartamento) === '' && $zona['sugerido']) {
            $this->pedDepartamento = $zona['sugerido'];
        }

        $fila = [
            'nombre'       => trim($this->pedNombre),
            'telefono'     => trim($this->pedTelefono),
            'direccion'    => trim($this->pedDireccion),
            'municipio'    => trim($this->pedMunicipio),
            'departamento' => trim($this->pedDepartamento),
            'descripcion'  => $this->descripcionPedido(),
            'cobrar'       => $this->totalPedido(),
        ];

        try {
            $guia = \App\Models\GuiaBorrador::create($fila);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo guardar el pedido')
                ->body($e->getMessage())
                ->danger()->persistent()->send();
            return;
        }

        // Recién ahora sale el enlace de rastreo. Ese es el sello de que la
        // orden se procesó: si en el chat no está, es que quedó a medias.
        $conv = $this->conversacion();
        $aviso = '';

        if ($conv && $conv->ventanaAbierta()) {
            $m = WhatsappApi::enviarTexto(
                $conv,
                \App\Models\GuiaBorrador::mensajeCliente($fila),
                auth()->id()
            );

            if ($m->estado === 'fallido') {
                $aviso = ' No se pudo mandarle el enlace de rastreo: ' . $m->error;
            } else {
                try {
                    $guia->update(['enviado_at' => now()]);
                } catch (\Throwable $e) {
                    // Que no se marque no cambia nada de lo que ve el cliente.
                }
            }
        } elseif ($conv) {
            // Fuera de las 24 horas no se puede escribir: se deja preparado.
            $this->texto = \App\Models\GuiaBorrador::mensajeCliente($fila);
            $aviso = ' La ventana de 24 horas está cerrada: el mensaje con el '
                   . 'rastreo quedó escrito, mandalo cuando el cliente responda.';
        }

        $this->limpiarPedido();
        $this->pestana = 'chat';

        $cola = $this->enCola();

        $cuerpo = "Ya hay {$cola} " . ($cola == 1 ? 'guía esperando' : 'guías esperando')
                . '. Cuando quieras, entrá a Crear guías y bajá el Excel.';

        $n = Notification::make()->title('Guardado en la cola');

        if ($aviso === '') {
            $n->body($cuerpo . ' Al cliente ya le salió el enlace de rastreo.')->success();
        } else {
            $n->body($cuerpo . $aviso)->warning()->persistent();
        }

        $n->send();
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

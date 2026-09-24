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

    /**
     * Mostrar solo las del día: 'hoy', 'ayer' o 'anteayer'.
     *
     * Null es sin filtro. Tres días y no más: para atrás está el buscador, que
     * es mejor herramienta cuando ya no te acordás de cuándo fue.
     */
    public ?string $filtroDia = null;

    public function filtrarDia(string $cual): void
    {
        // Tocar el que ya está puesto lo quita: el mismo botón sirve para las
        // dos cosas y no hace falta una ✕ aparte.
        $this->filtroDia = ($this->filtroDia === $cual) ? null : $cual;
    }

    /**
     * El rango de ese día, en horas del reloj de acá.
     *
     * Las fechas se guardan en UTC y El Salvador va seis horas atrás, así que
     * "hoy" no es de medianoche a medianoche en la base. Sin esta conversión,
     * los mensajes de después de las 6 de la tarde aparecerían como de mañana.
     */
    private function rangoDelDia(?string $cual): ?array
    {
        if (! $cual) return null;

        $tz = config('app.zona_local');

        $inicio = now()->timezone($tz)->startOfDay();

        if ($cual === 'ayer')     $inicio->subDay();
        if ($cual === 'anteayer') $inicio->subDays(2);

        return [
            $inicio->copy()->utc(),
            $inicio->copy()->endOfDay()->utc(),
        ];
    }

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

    /**
     * El id del último mensaje que entró, sea de quien sea.
     *
     * Es el disparador del aviso sonoro, y reemplaza a contar los sin leer.
     *
     * Contar no servía: si tenés el chat abierto, ese mismo chat se marca como
     * leído, así que el número no sube y no sonaba nada. Y si venían dos
     * mensajes del mismo cliente, el contador de conversaciones tampoco se
     * movía. O sea que justo cuando estás trabajando es cuando menos avisaba.
     *
     * Un id siempre crece. Si cambió, entró algo. Sin vueltas.
     */
    public function ultimoEntranteId(): int
    {
        try {
            return (int) WaMensaje::where('direccion', 'entrante')->max('id');
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

    /** El de quien pide. Es su identificación: va junto al nombre en la guía. */
    public string $pedTelefono = '';

    /**
     * El de quien recibe, cuando el paquete va para otra persona.
     *
     * Va en la columna TELEFONO de la guía, que es la que ve el repartidor en
     * el sistema de ellos y a la que llama para entregar. Si queda vacío, se
     * usa el del cliente para las dos cosas.
     */
    public string $pedTelefonoRecibe = '';
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
    /**
     * Desde el admin, el enlace del menú lleva al panel de mensajes.
     *
     * Esta página está registrada en los dos paneles, así que al tocar
     * "WhatsApp" en el admin se abría acá adentro: con la barra lateral del
     * admin comiéndose el ancho y sin la pantalla completa, el carrusel de
     * filtros ni el anclado del teclado, que son cosas del panel de mensajes.
     *
     * Con esto el menú del admin es una puerta al panel de chat, no una copia
     * a medias de él.
     */
    public static function getNavigationUrl(): string
    {
        try {
            $panel = \Filament\Facades\Filament::getCurrentPanel()?->getId();

            if ($panel !== null && $panel !== 'chat') {
                return static::getUrl([], true, 'chat');
            }
        } catch (\Throwable $e) {
            // Si el panel de chat no estuviera disponible, mejor el enlace de
            // siempre que un menú roto.
        }

        return parent::getNavigationUrl();
    }

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

            // Por día, según el último mensaje de la conversación.
            $rango = $this->rangoDelDia($this->filtroDia);
            if ($rango) $q->whereBetween('ultimo_mensaje_at', $rango);

            // Las fijadas van arriba siempre, aunque otras tengan mensajes más
            // nuevos: para eso se fijan. Entre ellas, la última que fijaste
            // primero. El resto sigue ordenado por lo más reciente.
            //
            // Se pregunta si la columna existe porque entre que sale el código
            // y corre la migración hay unos segundos, y no vale la pena que la
            // lista se vea vacía en ese rato.
            if (static::hayFijadas()) {
                $q->orderByRaw('fijada_at IS NULL')->orderByDesc('fijada_at');
            }

            return $q->orderByDesc('ultimo_mensaje_at')->limit(60)->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** ¿Ya corrió la migración de las fijadas? Se pregunta una sola vez. */
    public static function hayFijadas(): bool
    {
        static $hay = null;

        if ($hay !== null) return $hay;

        try {
            $hay = \Illuminate\Support\Facades\Schema::hasColumn('wa_conversaciones', 'fijada_at');
        } catch (\Throwable $e) {
            $hay = false;
        }

        return $hay;
    }

    /**
     * Clava o suelta una conversación de arriba de la lista.
     *
     * Hay un tope de seis: fijar todo es no fijar nada, la lista vuelve a
     * quedar igual de larga y ya no se sabe qué es lo importante.
     */
    public function fijar(int $id): void
    {
        if (! static::hayFijadas()) return;

        try {
            $conv = WaConversacion::find($id);
            if (! $conv) return;

            if ($conv->fijada()) {
                $conv->fijada_at = null;
                $conv->save();
                return;
            }

            $cuantas = WaConversacion::whereNotNull('fijada_at')->count();

            if ($cuantas >= 6) {
                \Filament\Notifications\Notification::make()
                    ->title('Ya tenés 6 chats fijados')
                    ->body('Soltá alguno antes de fijar este. Con más de seis arriba, la lista vuelve a ser igual de larga.')
                    ->warning()->send();
                return;
            }

            $conv->fijada_at = now();
            $conv->save();
        } catch (\Throwable $e) {
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
            // Ojo con el orden: antes decía orderBy('id')->limit(200), que trae
            // los 200 mensajes MÁS VIEJOS, no los más nuevos. En una
            // conversación larga eso significa dejar de ver lo último que
            // escribió el cliente. Se piden al revés y se dan vuelta.
            return WaMensaje::with('agente')
                ->where('conversacion_id', $this->abierta)
                ->orderByDesc('id')
                ->limit(200)
                ->get()
                ->reverse()
                ->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    // ── El latido ────────────────────────────────────────────────────────────

    /**
     * Una huella de lo que se está viendo, para saber si cambió algo.
     *
     * Son los últimos mensajes con su estado, más cuántos sin leer hay. Si eso
     * es idéntico a hace tres segundos, no pasó nada y no hay nada que
     * redibujar.
     */
    public string $huella = '';

    /**
     * Mensajes que quedaron en "enviando" y nadie despachó.
     *
     * Con el envío en dos tiempos aparece un riesgo nuevo: si el navegador se
     * cierra entre el primer tiempo y el segundo, el mensaje queda anotado
     * pero nunca sale. Sin esto se vería en gris para siempre y vos creerías
     * que el cliente lo recibió.
     *
     * Después de un minuto se dan por fallidos. Mandarlos tarde sería peor:
     * un "ya salió su pedido" que llega tres horas después es mentira. Mejor
     * que lo veas en rojo y decidas vos.
     */
    private function rescatarAtascados(): void
    {
        try {
            WaMensaje::where('estado', 'enviando')
                ->where('created_at', '<', now()->subMinute())
                ->update([
                    'estado' => 'fallido',
                    'error'  => 'Quedó a medias: se cerró la pantalla antes de mandarlo. Volvé a mandarlo.',
                ]);
        } catch (\Throwable $e) {
        }
    }

    private function huellaActual(): string
    {
        try {
            // Por id descendente: va por la llave primaria, así que es de las
            // consultas más baratas que hay. Trae el estado además del id,
            // para que también se note cuando una luz pasa de amarillo a verde.
            $ultimos = WaMensaje::orderByDesc('id')->limit(40)->pluck('estado', 'id')->all();
            $sinLeer = (int) WaConversacion::where('archivada', false)->sum('sin_leer');

            return md5(json_encode($ultimos) . '|' . $sinLeer);
        } catch (\Throwable $e) {
            // Si falla, se devuelve algo distinto cada vez: ante la duda,
            // redibujar. Vale más gastar de más que quedarse congelado.
            return (string) microtime(true);
        }
    }

    /**
     * Lo que corre cada tres segundos.
     *
     * Antes acá iba un $refresh, que volvía a dibujar TODO —la lista, los 200
     * globos, el formulario de pedido— aunque no hubiera pasado nada. Eso es lo
     * que se sentía como tirones: cada tres segundos el navegador rehacía la
     * pantalla entera.
     *
     * Ahora primero se pregunta si cambió algo. Si no, skipRender() corta ahí
     * mismo: no se arma el HTML, no se manda, no se toca el navegador. El
     * noventa y pico por ciento de los latidos no hacen nada.
     */
    public function latir(): void
    {
        $this->rescatarAtascados();

        $nueva = $this->huellaActual();

        if ($nueva === $this->huella) {
            $this->skipRender();
            return;
        }

        $this->huella = $nueva;
    }

    /** Abre un chat y lo marca como leído. */
    public function abrir(int $id): void
    {
        $this->abierta = $id;
        $this->texto = '';
        $this->pestana = 'chat';

        // Una foto que quedó esperando en otro chat no se va con este.
        $this->rapidaFoto = null;

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

    /**
     * Trae el texto de un mensaje al cuadro de escribir.
     *
     * Para lo de siempre: mandaste algo con una letra mala y WhatsApp no deja
     * editar mensajes ya enviados — no existe forma de hacerlo, ni acá ni
     * desde el teléfono. Lo único que queda es escribirlo de nuevo corregido,
     * y volver a teclearlo entero por una letra es absurdo.
     *
     * Si ya había algo escrito, no se pisa: se le suma abajo.
     */
    public function reusar(int $id): void
    {
        try {
            $m = WaMensaje::find($id);
            if (! $m || blank($m->texto)) return;

            $this->texto = trim($this->texto) === ''
                ? $m->texto
                : rtrim($this->texto) . "\n" . $m->texto;

            $this->pestana = 'chat';
            $this->abrirCajaSiHaceFalta($this->texto);
        } catch (\Throwable $e) {
        }
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
        $this->abrirCajaSiHaceFalta($r['texto']);
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

        // Si venía una foto esperando de una respuesta rápida, sale como foto
        // con el texto de pie: un solo mensaje, no dos. Para el cliente es la
        // diferencia entre recibir una imagen explicada y recibir una imagen
        // suelta seguida de un párrafo.
        $conFoto = $this->fotoPendiente();

        if ($conFoto) {
            // Con foto se manda de corrido: Meta tiene que ir a buscar la
            // imagen a nuestro sitio, así que el globo no podría dibujarse
            // antes de saber si la aceptó.
            $mensaje = WhatsappApi::enviarImagen(
                $conv,
                $conFoto->urlCompleta(),
                $texto,
                auth()->id()
            );
        } else {
            // En dos tiempos. Acá solo se anota, que es cosa de milisegundos,
            // y el globo aparece enseguida en gris. La llamada a Meta —lo que
            // de verdad tarda— se hace en el segundo tiempo, ya con el mensaje
            // en pantalla y el cuadro de texto libre para seguir escribiendo.
            $mensaje = WhatsappApi::anotarSaliente(
                $conv,
                $texto,
                auth()->id(),
                false,
                $citado?->wa_message_id
            );

            $this->dispatch('wa-despachar', id: $mensaje->id);
        }

        $this->texto = '';
        $this->textoAntes = '';
        $this->respondiendo = null;
        $this->rapidaFoto = null;
        // Mandado: el cuadro vuelve a su tamaño chico y el chat recupera el alto.
        $this->cajaGrande = false;

        // Con el envío en dos tiempos, el fallo se avisa en el segundo tiempo,
        // no acá: en este punto todavía no se sabe cómo le fue.
        if (! $conFoto) return;

        if ($mensaje->estado === 'fallido') {
            Notification::make()
                ->title('No se pudo enviar')
                ->body($mensaje->error ?: 'Meta rechazó el mensaje.')
                ->danger()->persistent()->send();
        }
    }

    /**
     * El segundo tiempo del envío: acá se llama a Meta de verdad.
     *
     * Lo dispara el navegador apenas termina de dibujar el globo. Para quien
     * escribe, el mensaje ya está en pantalla y el cuadro ya está libre; esta
     * espera ocurre por detrás.
     *
     * Si falla, el globo se pone en rojo con el motivo —no desaparece ni se
     * queda en gris para siempre— y además salta el aviso.
     */
    public function despachar(int $id): void
    {
        try {
            $m = WaMensaje::find($id);
            if (! $m) return;

            $m = WhatsappApi::despacharTexto($m);

            if ($m->estado === 'fallido') {
                Notification::make()
                    ->title('No se pudo enviar')
                    ->body($m->error ?: 'Meta rechazó el mensaje.')
                    ->danger()->persistent()->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo enviar')
                ->body($e->getMessage())
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
                $pie .= '*Oferta:* ' . (int) $s->combo_qty . ' por $'
                    . number_format((float) $s->combo_price, 2) . "\n";
            }

            // El enlace a su página: la foto sirve para que mire, el enlace
            // para que entre a la tienda y pida sin tener que escribir. Va con
            // la talla adelantada para que le abra la que están hablando.
            //
            // urlencode y no rawurlencode: el primero manda los espacios como
            // "+" y el segundo como "%20". Con tallas de varias palabras —"4 a
            // 7 años"— el %20 llena el enlace de símbolos y en WhatsApp queda
            // ilegible. PHP lee los dos igual del otro lado.
            try {
                $pie .= "\n*Miralo aqu\u{ED}:*\n"
                    . route('store.show', $p) . '?t=' . urlencode(trim((string) $s->size));
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
                foreach ($this->fotosDeUso($s) as $uso) {
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


    /** Lo que se muestra debajo de los campos. */
    public function revisionZona(): array
    {
        return \App\Services\Municipios::revisar($this->pedMunicipio, $this->pedDepartamento);
    }

    public function departamentos(): array
    {
        return \App\Services\Municipios::departamentos();
    }

    /**
     * Los municipios del departamento elegido, para el desplegable.
     *
     * Sin departamento devuelve vacío a propósito: con los 262 de golpe, en el
     * teléfono, encontrar el propio es peor que escribirlo.
     */
    public function municipiosDelDepartamento(): array
    {
        return \App\Services\Municipios::deDepartamento($this->pedDepartamento);
    }

    /** Todos, para sugerir cuando todavía no se eligió departamento. */
    public function todosLosMunicipios(): array
    {
        return \App\Services\Municipios::todos();
    }

    /**
     * El mapa completo departamento → municipios, para el buscador.
     *
     * Va entero al navegador una sola vez. El buscador elige de ahí la lista
     * que toca según el departamento, sin volver a preguntar acá. Son unos
     * pocos kilobytes y ahorra un viaje al servidor por cada cambio.
     */
    public function municipiosPorDepartamento(): array
    {
        return \App\Services\Municipios::porDepartamento();
    }

    /**
     * Al cambiar de departamento, el municipio anterior ya no vale.
     *
     * Es lo que impide de raíz el error que originó todo esto: una guía que
     * decía "San Vicente, San Salvador". Si el municipio solo puede salir de
     * la lista del departamento elegido, esa pareja no se puede ni formar.
     */
    public function updatedPedDepartamento(): void
    {
        if ($this->pedMunicipio === '') return;

        $suyos = $this->municipiosDelDepartamento();

        if (! in_array($this->pedMunicipio, $suyos, true)) {
            $this->pedMunicipio = '';
        }
    }

    /**
     * Todo lo que quedó a medias, en un solo lugar.
     *
     * El panel ya sabía estas cuatro cosas, pero cada una vivía en su pantalla.
     * Juntarlas importa porque el trabajo no se pierde por falta de funciones,
     * se pierde en los pasos que nadie ve: la orden que no llegó a guía, la
     * guía cuyo enlace nunca se mandó, el cliente que quedó esperando.
     *
     * Cada número lleva a su lista. La idea es dejar de acordarse y empezar a
     * mirar.
     */
    public function pendientes(): array
    {
        // Solo el circuito de las guías. Las de "sin responder" y "sin leer" se
        // quitaron: la barra roja de cada conversación ya marca las primeras, y
        // "Sin leer" ya está en el carrusel de filtros. Contarlas acá era gastar
        // dos consultas en cada dibujado para repetir algo que ya se veía.
        // Queda solo "sin enlace". "Sin guía" se quitó porque era la etiqueta
        // Pedidos contada de otra forma, y esa ya vive en el carrusel de
        // filtros: dos botones para lo mismo confunden en vez de ayudar.
        $p = ['sin_enlace' => 0];

        // Guías armadas a las que nunca se les mandó el enlace de rastreo.
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('guias_borrador')) {
                $p['sin_enlace'] = \App\Models\GuiaBorrador::whereNull('enviado_at')->count();
            }
        } catch (\Throwable $e) {
        }

        return $p;
    }

    /**
     * Revisa las entregas ahora mismo, sin esperar al programador.
     *
     * El comando corre solo cada media hora, pero eso necesita un cron
     * levantado en Railway. Este botón hace lo mismo a pedido: sirve para
     * probar que todo está bien enganchado, y como salida mientras el cron no
     * exista.
     *
     * Tarda: cada guía es una consulta a la página del courier. Por eso se
     * limita a unas pocas por vez y se avisa qué pasó.
     */
    public function revisarEntregas(): void
    {
        try {
            $entregada = \App\Models\WaEtiqueta::porRol('entregada');

            if (! $entregada) {
                Notification::make()
                    ->title('Falta decir cuál etiqueta es la de entregado')
                    ->body('Andá a Etiquetas → Entregados y en "¿Se pone sola?" elegí '
                         . '"Cuando el courier confirma la entrega". Sin eso el panel no '
                         . 'sabe adónde moverlas.')
                    ->warning()->persistent()->send();
                return;
            }

            \Illuminate\Support\Facades\Artisan::call('entregas:revisar', ['--limite' => 15]);

            Notification::make()
                ->title('Revisión terminada')
                ->body(trim(\Illuminate\Support\Facades\Artisan::output()) ?: 'Sin novedades.')
                ->success()->persistent()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo revisar')
                ->body($e->getMessage())
                ->danger()->persistent()->send();
        }
    }

    /** Cuántas conversaciones están esperando entrega. */
    public function cuantasEsperandoEntrega(): int
    {
        try {
            $p = \App\Models\WaEtiqueta::porRol('procesada');

            return $p ? $p->conversaciones()->where('archivada', false)->count() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
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
     * Las fotos de cómo queda puesta ESA talla.
     *
     * Antes salían de la galería del producto en la página, así que Magic M y
     * Magic XXL mandaban las mismas — y no se parecen en nada puestos. Ahora
     * cada talla tiene las suyas, cargadas en el admin del producto, y esas
     * fotos no se publican en la página: existen solo para el chat.
     *
     * Si una talla no tiene fotos propias, no manda nada. Mandar la de otra
     * talla sería peor que no mandar ninguna.
     */
    public function fotosDeUso($talla): array
    {
        try {
            return $talla?->fotosUsoUrls() ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Cuántas fotos de uso hay entre lo que se va a mandar. */
    public function cuantasFotosUso(): int
    {
        if (empty($this->elegidas)) return 0;

        try {
            $filas = \App\Models\ProductSize::whereIn('id', $this->elegidas)->get();
        } catch (\Throwable $e) {
            return 0;
        }

        $n = 0;

        // Sin agrupar por producto: ahora son de la talla, y dos tallas del
        // mismo producto tienen fotos distintas.
        foreach ($filas as $s) {
            $n += count($this->fotosDeUso($s));
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
        $this->abrirCajaSiHaceFalta($r->texto);

        // Si la respuesta lleva foto, la foto NO se manda todavía: queda
        // esperando pegada al cuadro de texto. Así seguís pudiendo corregir el
        // texto —agregar el nombre, cambiar un precio— y cuando le des a
        // Enviar sale una sola cosa: la foto con ese texto de pie.
        $this->rapidaFoto = $r->tieneFoto() ? $r->id : null;
    }

    /** La respuesta rápida cuya foto está esperando, si hay alguna. */
    public ?int $rapidaFoto = null;

    /**
     * El cuadro de escribir, abierto a lo alto.
     *
     * En el teléfono el cuadro está limitado a tres renglones para no comerse
     * la conversación. Pero cuando cae una respuesta rápida de diez renglones,
     * esos tres no alcanzan ni para leerla: había que borrar texto para ver el
     * final, que es exactamente lo que no se debe hacer.
     *
     * Entonces se abre solo cuando llega un texto largo de un tirón —una
     * respuesta rápida, una mejora de la IA, un mensaje traído para corregir—
     * y se vuelve a cerrar al mandar. También se puede abrir y cerrar a mano.
     */
    public bool $cajaGrande = false;

    public function alternarCaja(): void
    {
        $this->cajaGrande = ! $this->cajaGrande;
    }

    /** Abre el cuadro si lo que acaba de entrar no cabe en tres renglones. */
    private function abrirCajaSiHaceFalta(?string $texto): void
    {
        $t = trim((string) $texto);

        if ($t === '') return;

        // Con pasar de dos renglones ya conviene abrirlo. Antes el umbral era
        // el triple y casi ninguna respuesta rápida lo alcanzaba, así que el
        // cuadro se quedaba chico justo cuando acababa de llenarse.
        //
        // Los 80 caracteres son, más o menos, dos renglones en un teléfono.
        $renglones = substr_count($t, "\n");

        if ($renglones >= 1 || mb_strlen($t) > 80) {
            $this->cajaGrande = true;
        }
    }

    public function fotoPendiente(): ?\App\Models\RespuestaRapida
    {
        if (! $this->rapidaFoto) return null;

        try {
            $r = \App\Models\RespuestaRapida::find($this->rapidaFoto);
            return $r && $r->tieneFoto() ? $r : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Mandar el texto solo, sin la foto que venía con la respuesta. */
    public function quitarFotoPendiente(): void
    {
        $this->rapidaFoto = null;
    }

    // ═══ Pestaña "Tomar pedido" ═════════════════════════════════════════════

    /** Lo que ya sabemos de quien está escribiendo. */
    private function cargarDatosDelCliente(WaConversacion $conv): void
    {
        $this->pedTelefono = $conv->telefono ?: '';
        $this->pedNombre   = $this->limpiarNombre($conv->comoSeLlama() ?: '');

        // Que el segundo teléfono de un pedido no se cuele en el siguiente.
        $this->pedTelefonoRecibe = '';

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

        if (filled($datos['nombre'])) $this->pedNombre = $this->limpiarNombre($datos['nombre']);

        /*
         * LA ORDEN MANDA. Lo que la orden no diga, se vacía.
         *
         * Acá estaba el error de la dirección distinta. Al abrir el chat, el
         * formulario se precarga con los datos del pedido ANTERIOR de ese
         * cliente — cómodo para escribir una orden a mano. Pero al procesar
         * una orden, si el intérprete no lograba leer el renglón de la
         * dirección, este campo no se tocaba: se quedaba la dirección vieja.
         *
         * Y así se ve igual que si la hubiera leído bien. Una dirección de
         * otro pedido, sentada en el formulario, con cara de dato correcto.
         * Eso es una guía a la casa equivocada.
         *
         * Vacío se nota. Y abajo se avisa exactamente qué quedó sin leer.
         */
        $antes = [
            'direccion'    => $this->pedDireccion,
            'municipio'    => $this->pedMunicipio,
            'departamento' => $this->pedDepartamento,
        ];

        $this->pedDireccion    = filled($datos['direccion'] ?? null) ? $datos['direccion'] : '';
        $this->pedMunicipio    = '';
        $this->pedDepartamento = '';

        // ── Los dos teléfonos ────────────────────────────────────────────────
        //
        // En la orden pueden venir dos y quieren decir cosas distintas:
        //
        //   · el que va pegado al nombre → quien PIDE. Es su identificación:
        //     con ese rastrea el paquete y con ese lo reconocemos la próxima.
        //   · el del renglón "Teléfono:" → a quien LLAMA el repartidor. Cuando
        //     el pedido va para otra persona, es el de ella.
        //
        // Antes el renglón "Teléfono:" pisaba el del cliente, así que en los
        // pedidos que van para un tercero se perdía uno de los dos.
        $delNombre = $this->telefonoDentroDe($datos['nombre'] ?? '');

        if (filled($delNombre)) {
            $this->pedTelefono = $delNombre;
        }

        if (filled($datos['telefono'])) {
            $suelto = preg_replace('/\D/', '', $datos['telefono']);
            $mio    = preg_replace('/\D/', '', $this->pedTelefono);

            // Si es el mismo de siempre, no hay segunda persona: se deja como
            // el del cliente y el campo de quien recibe queda vacío.
            if ($mio === '' || $suelto === $mio) {
                $this->pedTelefono = $datos['telefono'];
            } else {
                $this->pedTelefonoRecibe = $datos['telefono'];
            }
        }

        $this->resolverZona($datos['municipio'] ?? '', $datos['direccion'] ?? '');
        if (filled($datos['productos'])) $this->pedProductosTexto = $datos['productos'];
        if (filled($datos['total']))     $this->pedCobrarManual   = $datos['total'];

        $this->pestana = 'pedido';

        $leidos = count(array_filter($datos, fn ($v) => filled($v)));

        // Qué se vació porque la orden no lo traía, y qué había antes ahí.
        // Decirlo importa: el campo que quedó en blanco es justo el que hay
        // que mirar, y si antes tenía algo, es dato de OTRO pedido.
        $perdidos = [];

        foreach (['direccion' => 'la dirección', 'municipio' => 'el municipio'] as $campo => $nombre) {
            $ahora = $campo === 'direccion' ? $this->pedDireccion : $this->pedMunicipio;

            if (trim((string) $ahora) === '' && trim((string) $antes[$campo]) !== '') {
                $perdidos[] = $nombre;
            }
        }

        if ($perdidos) {
            Notification::make()
                ->title('Ojo: ' . implode(' y ', $perdidos) . ' no venía en la orden')
                ->body('Lo que había ahí era del pedido anterior de este cliente, así que lo '
                     . 'borré. Escribilo mirando el texto de la orden, que quedó arriba.')
                ->warning()->persistent()->send();
            return;
        }

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
    /**
     * El teléfono que venía escrito adentro del nombre, si había uno.
     *
     * Es el mismo que limpiarNombre() quita: allá se descarta y acá se guarda,
     * porque es la identificación de quien pide.
     */
    private function telefonoDentroDe(string $texto): string
    {
        if (preg_match('/(?<!\d)([267]\d{3})[\s.\-]?(\d{4})(?!\d)/u', $texto, $m)) {
            return $m[1] . $m[2];
        }

        return '';
    }

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
        $this->pedTelefonoRecibe = '';
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
            // Si va vacío, el Excel usa el del cliente para las dos columnas.
            'telefono_recibe' => trim($this->pedTelefonoRecibe) ?: null,
            'direccion'    => trim($this->pedDireccion),
            'municipio'    => trim($this->pedMunicipio),
            'departamento' => trim($this->pedDepartamento),
            'descripcion'  => $this->descripcionPedido(),
            'cobrar'       => $this->totalPedido(),
            // Quién la armó: en la cola se mezclan las de todo el equipo.
            'user_id'      => auth()->id(),
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

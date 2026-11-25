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
    use \Livewire\WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'WhatsApp';

    /** Grupo del menú solo en el panel admin (en Mensajes la barra va sin grupos). */
    public static function getNavigationGroup(): ?string
    {
        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'admin' ? 'Ventas' : null;
    }

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

    /** Rango de horas a mano ("de 20:00 a 06:00"), hora de El Salvador. */
    public string $horaDesde = '';
    public string $horaHasta = '';

    public function filtrarRangoHoras(): void
    {
        if (! preg_match('/^\d{1,2}:\d{2}$/', $this->horaDesde) || ! preg_match('/^\d{1,2}:\d{2}$/', $this->horaHasta)) {
            Notification::make()->title('Poné la hora de inicio y la de fin')->warning()->send();
            return;
        }
        $this->filtroDia = 'rango';
    }

    public function filtrarDia(string $cual): void
    {
        // Tocar el que ya está puesto lo quita: el mismo botón sirve para las
        // dos cosas y no hace falta una ✕ aparte.
        $this->filtroDia = ($this->filtroDia === $cual) ? null : $cual;
        $this->limiteLista = 60;
    }

    /**
     * El rango de cada botón, contado hacia atrás desde AHORA y no por día del
     * calendario.
     *
     *   24 h · las últimas 24 horas
     *   Ayer · de 24 a 48 horas atrás
     *   Anteayer · de 48 a 72 horas atrás
     *
     * Antes "Hoy" iba de medianoche a medianoche: a las 7 de la mañana no
     * mostraba a la clienta que escribió anoche a las 10, que es justo la que
     * hay que atender primero. Así los tres botones se tocan uno con otro, sin
     * huecos ni repetidos.
     */
    private function rangoDelDia(?string $cual): ?array
    {
        if (! $cual) return null;

        // Las últimas 2, 6 o 12 horas.
        if (preg_match('/^(\d{1,2})h$/', $cual, $m)) {
            return [now()->subHours((int) $m[1])->utc(), now()->utc()];
        }

        // De una hora a otra (hora de El Salvador). Si el fin es antes que el
        // inicio, cruza la medianoche: "de 20:00 a 06:00" es de anoche a hoy.
        if ($cual === 'rango') {
            try {
                $zona = 'America/El_Salvador';
                $ahora = now($zona);
                $hasta = $ahora->copy()->setTimeFromTimeString($this->horaHasta);
                $desde = $hasta->copy()->setTimeFromTimeString($this->horaDesde);
                if ($desde->gte($hasta)) $desde->subDay();          // cruza la medianoche
                if ($hasta->gt($ahora)) {
                    // Todavía no llegó la hora de fin: si el inicio ya pasó, es
                    // de hoy hasta ahora; si no, el mismo rango de ayer.
                    if ($desde->lte($ahora)) $hasta = $ahora->copy();
                    else { $desde->subDay(); $hasta->subDay(); }
                }
                return [$desde->utc(), $hasta->utc()];
            } catch (\Throwable $e) {
                return null;
            }
        }

        $atras = ['hoy' => 0, 'ayer' => 1, 'anteayer' => 2][$cual] ?? null;
        if ($atras === null) return null;

        $fin = now()->subHours(24 * $atras);

        return [
            $fin->copy()->subHours(24)->utc(),
            $fin->copy()->utc(),
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

    /**
     * Cada renglón: ['size_id' => '', 'cantidad' => 1, 'precio' => ''].
     *
     * El precio es el UNITARIO y manda sobre el del catálogo cuando está
     * escrito. Existe porque lo que se le cotizó al cliente en el chat es un
     * compromiso con él: si le dijiste \$17 y el admin tiene \$18, se le cobra
     * \$17. Vacío significa "usá el del catálogo", no significa gratis.
     */
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

    /**
     * Cuántos chats se muestran en la lista. Empieza en 60 para que cargue
     * rápido; "Ver más" va sumando de 60 en 60.
     */
    public int $limiteLista = 60;

    public function verMasChats(): void
    {
        $this->limiteLista = min(2000, $this->limiteLista + 60);
    }

    /** Al cambiar de filtro o de búsqueda, la lista vuelve a empezar en 60. */
    public function updatedBuscar(): void
    {
        $this->limiteLista = 60;
    }

    /** La lista de la izquierda. */
    public function conversaciones()
    {
        if (! WaConversacion::hayTabla()) return collect();

        try {
            $convs = $this->consultaConversaciones()->with(['agente', 'etiquetas'])
                ->orderByDesc('ultimo_mensaje_at')->limit($this->limiteLista)->get();
        } catch (\Throwable $e) {
            return collect();
        }

        $this->ponerHistorial($convs);

        return $convs;
    }

    /**
     * Los dos mensajes de antes del último, para la lista ancha: así se ve de
     * qué va la plática sin entrar al chat. Se cuelgan como relación
     * "historial" (lo más viejo primero).
     *
     * Una sola consulta: un pedacito "los últimos 5 de este chat" por cada
     * chat de la lista, unidos. Cada pedacito usa el índice de la
     * conversación, así que no recorre chats enteros aunque tengan miles de
     * mensajes. Se piden 5 para poder saltar las reacciones y ediciones.
     */
    private function ponerHistorial($convs): void
    {
        if ($convs->isEmpty()) return;

        $porConv = [];
        try {
            $q = null;
            foreach ($convs as $c) {
                $parte = WaMensaje::query()
                    ->select(['id', 'conversacion_id', 'direccion', 'tipo', 'texto', 'automatico'])
                    ->where('conversacion_id', $c->id)
                    ->orderByDesc('id')->limit(5);
                $q = $q ? $q->unionAll($parte) : $parte;
            }
            foreach ($q->get() as $m) $porConv[$m->conversacion_id][] = $m;
        } catch (\Throwable $e) {
            $porConv = [];
        }

        $ruido = ['[reaction]', '[edit]', '[unsupported]', '[sticker]'];

        foreach ($convs as $c) {
            $msjs = collect($porConv[$c->id] ?? [])
                ->sortByDesc('id')
                ->reject(fn ($m) => in_array(trim((string) $m->texto), $ruido, true) && ! in_array($m->tipo, ['image', 'audio', 'voice', 'video', 'document'], true))
                ->values()
                ->slice(1, 2)          // el último ya se ve en la vista previa
                ->reverse()->values();  // lo más viejo arriba

            $c->setRelation('historial', $msjs);
        }
    }

    /** Cuántos chats hay con los filtros puestos (para "Mostrando 60 de 412"). */
    public function totalConversaciones(): int
    {
        if (! WaConversacion::hayTabla()) return 0;

        try {
            return $this->consultaConversaciones(false)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Los filtros de la lista, sin el límite. */
    private function consultaConversaciones(bool $ordenFijadas = true)
    {
            $q =WaConversacion::query()->where('archivada', false);

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
            if ($ordenFijadas && static::hayFijadas()) {
                $q->orderByRaw('fijada_at IS NULL')->orderByDesc('fijada_at');
            }

            return $q;
    }

    /** ¿Ya corrió la migración de las fijadas? Se pregunta una sola vez. */
    public static function hayFijadas(): bool
    {
        static $hay = null;

        if ($hay !== null) return $hay;

        $hay = \App\Models\WaConversacion::columnaExiste('wa_conversaciones', 'fijada_at');

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

    // ── Mensajes fijados dentro del chat ────────────────────────────────────

    /** ¿Ya corrió la migración de los mensajes fijados? */
    public static function hayMensajesFijados(): bool
    {
        static $hay = null;
        if ($hay !== null) return $hay;

        $hay = \App\Models\WaConversacion::columnaExiste('wa_mensajes', 'fijado_at');

        return $hay;
    }

    /**
     * 📌 en un mensaje: lo fija arriba del chat (o lo suelta si ya estaba).
     * Como en WhatsApp, hasta 3 por chat: al fijar el cuarto se suelta el más viejo.
     * Solo se ve en el panel; al cliente no le cambia nada.
     */
    public function fijarMensaje(int $id): void
    {
        if (! static::hayMensajesFijados() || ! $this->abierta) return;

        try {
            $m = WaMensaje::where('conversacion_id', $this->abierta)->find($id);
            if (! $m) return;

            if ($m->fijado_at) {
                $m->fijado_at = null;
                $m->save();
                return;
            }

            $fijados = WaMensaje::where('conversacion_id', $this->abierta)->whereNotNull('fijado_at')
                ->orderBy('fijado_at')->get(['id', 'fijado_at']);
            foreach ($fijados->slice(0, max(0, $fijados->count() - 2)) as $viejo) {
                WaMensaje::whereKey($viejo->id)->update(['fijado_at' => null]);
            }

            $m->fijado_at = now();
            $m->save();
        } catch (\Throwable $e) {
            Notification::make()->title('No se pudo fijar el mensaje')->body($e->getMessage())->danger()->send();
        }
    }

    /** Los fijados del chat abierto, el último fijado primero. */
    public function mensajesFijados()
    {
        if (! static::hayMensajesFijados() || ! $this->abierta) return collect();

        try {
            return WaMensaje::where('conversacion_id', $this->abierta)->whereNotNull('fijado_at')
                ->orderByDesc('fijado_at')->limit(3)->get();
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
            \Illuminate\Support\Facades\Cache::forget('wa:cuenta_etiquetas');
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
        $this->limiteLista = 60;
    }

    /** Cuántas conversaciones hay en cada etiqueta, para el contador. */
    public function cuentaEtiquetas(): array
    {
        // Se cuentan una vez por minuto, no en cada redibujado: con 400 chats
        // en una etiqueta, contarlos cada vez que entra un mensaje era de lo
        // más pesado. Al poner o quitar una etiqueta a mano se recuentan ya.
        try {
            return \Illuminate\Support\Facades\Cache::remember('wa:cuenta_etiquetas', 60, fn () =>
                \App\Models\WaEtiqueta::withCount(['conversaciones' => fn ($q) =>
                    $q->where('archivada', false)
                ])->pluck('conversaciones_count', 'id')->all()
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Cuántos mensajes se muestran del chat abierto (los más nuevos).
     *
     * Eran 200: cada vez que entraba un mensaje en CUALQUIER chat, el panel
     * redibujaba los 200 globos del abierto, y eso es lo que se sentía pesado.
     * Con 50 alcanza para leer la conversación; los de antes, con el botón
     * "Ver mensajes anteriores" (o tocando una cita vieja).
     */
    public int $limiteMensajes = 50;

    /** "⬆️ Ver mensajes anteriores": 100 más, y la pantalla se queda donde estaba. */
    public function verAnteriores(int $primero = 0): void
    {
        $this->limiteMensajes = min(3000, $this->limiteMensajes + 100);
        if ($primero) $this->dispatch('wa-quedarse', id: $primero);
    }

    /** ¿Hay mensajes más viejos que los que se están mostrando? */
    public function hayAnteriores(int $mostrados): bool
    {
        if (! $this->abierta || $mostrados < $this->limiteMensajes) return false;

        try {
            return WaMensaje::where('conversacion_id', $this->abierta)->count() > $mostrados;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Tocó una cita de un mensaje viejo que no está entre los cargados: se
     * cargan los necesarios para llegar hasta él, y se avisa al navegador para
     * que se deslice hasta ahí.
     */
    public function irAMensaje(int $id): void
    {
        if (! $this->abierta) return;

        $existe = WaMensaje::where('conversacion_id', $this->abierta)->whereKey($id)->exists();
        if (! $existe) return;

        $despues = WaMensaje::where('conversacion_id', $this->abierta)->where('id', '>=', $id)->count();
        $this->limiteMensajes = min(3000, max($this->limiteMensajes, $despues + 15));

        $this->dispatch('wa-ir', id: $id);
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
                ->limit(max(50, $this->limiteMensajes))
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

        // Órdenes confirmadas por el cliente: a la cola solas (una vez por minuto).
        $hizoAlgo = false;
        try { $hizoAlgo = $this->autoCola(); } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('autoCola: ' . $e->getMessage());
        }

        $nueva = $this->huellaActual();

        if ($nueva === $this->huella && ! $hizoAlgo) {
            $this->skipRender();
            return;
        }

        $this->huella = $nueva;
    }

    /** Abre un chat y lo marca como leído. */
    public function abrir(int $id): void
    {
        $this->abierta = $id;
        $this->limiteMensajes = 50;
        $this->texto = '';
        $this->pestana = 'chat';

        // Una foto que quedó esperando en otro chat no se va con este.
        $this->rapidaFoto = null;

        $conv = $this->conversacion();
        if (! $conv) return;

        // Se precarga lo que ya sabemos del cliente, para no volver a pedírselo.
        $this->cargarDatosDelCliente($conv);

        $this->ponerLeida($conv);
    }

    /** Sin leer a cero, y las palomitas azules del lado del cliente. */
    private function ponerLeida(WaConversacion $conv): void
    {
        if ($conv->sin_leer <= 0) return;

        $conv->sin_leer = 0;
        $conv->save();

        $ultimo = WaMensaje::where('conversacion_id', $conv->id)
            ->where('direccion', 'entrante')
            ->whereNotNull('wa_message_id')
            ->orderByDesc('id')->first();

        try {
            if ($ultimo) WhatsappApi::marcarLeido($ultimo->wa_message_id);
        } catch (\Throwable $e) {
            // Que no salgan las azules no cambia nada de este lado.
        }
    }

    /**
     * "✓ Leído" desde la lista, sin abrir el chat. En la computadora la lista
     * ya muestra de qué va la plática: entrar y salir solo para que se apague
     * el número era trabajo de más.
     */
    public function marcarLeida(int $id): void
    {
        $conv = WaConversacion::find($id);
        if ($conv) $this->ponerLeida($conv);
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

        // El cursor al cuadro de escribir (lo agarra el JS de la página).
        $this->dispatch('wa-enfocar');
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

            // Listo para corregir: el cursor al final del texto traído.
            $this->dispatch('wa-enfocar');
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

        // Con el cuadro vacío no se manda nada... salvo que haya fotos de una
        // respuesta rápida esperando: esas salen solas, sin texto de pie.
        if (! $conv) return;
        if ($texto === '' && ! $this->fotoPendiente()) return;

        if (! $conv->ventanaAbierta()) {
            Notification::make()
                ->title('La ventana de 24 horas está cerrada')
                ->body('WhatsApp solo deja escribir libremente dentro de las 24 horas '
                     . 'desde el último mensaje del cliente. Fuera de eso, únicamente plantillas aprobadas.')
                ->warning()->duration(8000)->send();
            return;
        }

        // Orden de envío con un producto sin especificar ("1 calzoncito M"):
        // no sale. Quien la escribe la tiene que dejar bien antes de mandarla.
        if ($mal = static::productosSinPresentacion($texto)) {
            $lista = [];
            foreach ($mal as $renglon => $falta) $lista[] = '«' . \Illuminate\Support\Str::limit($renglon, 60) . '»: ' . str_replace('*', '', $falta) . '.';
            Notification::make()
                ->title('La orden no salió: falta especificar el producto')
                ->body(implode("\n", $lista) . "\nCorregí el renglón y mandala de nuevo.")
                ->danger()->persistent()->send();
            return;
        }

        // La orden sale acomodada: datos arriba, pedido aislado y en negrita.
        $texto = $this->formatearOrden($texto);

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

        // Si la respuesta lleva varias fotos, salen todas seguidas y el texto
        // va de pie de la primera. Se guarda si alguna falló para avisar.
        $fallidas = [];

        if ($conFoto) {
            // Con foto se manda de corrido: Meta tiene que ir a buscar la
            // imagen a nuestro sitio, así que el globo no podría dibujarse
            // antes de saber si la aceptó.
            foreach ($conFoto->urlsCompletas() as $i => $urlFoto) {
                $m = WhatsappApi::enviarImagen(
                    $conv,
                    $urlFoto,
                    $i === 0 ? $texto : null,
                    auth()->id()
                );

                if ($i === 0) $mensaje = $m;
                if ($m->estado === 'fallido') $fallidas[] = $m;
            }
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

            // Si lo que se mandó es la orden de envío, sale sola detrás la
            // pregunta de que la revise. Antes se escribía a mano cada vez
            // ("¿Puede revisar la orden…?") y a veces se olvidaba.
            if ($revisar = $this->pedirRevisarOrden($texto, $conv)) {
                $rev = WhatsappApi::anotarSaliente($conv, $revisar, auth()->id(), false, null);
                $this->dispatch('wa-despachar', id: $rev->id);
            }
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

        if ($fallidas) {
            $total = $conFoto->cuantasFotos();
            $n     = count($fallidas);

            Notification::make()
                ->title($total > 1
                    ? "No se pudieron enviar {$n} de {$total} fotos"
                    : 'No se pudo enviar')
                ->body($fallidas[0]->error ?: 'Meta rechazó el mensaje.')
                ->danger()->persistent()->send();
        }
    }

    /** Lo que sale solo después de la orden de envío. Corto, en un renglón. */
    private const PREGUNTA_REVISAR = '¿Me podría revisar la orden para identificar posibles errores?';

    /**
     * Después de mandar la orden de envío: la pregunta de que la revise.
     * Null si el texto no es una orden de envío.
     */
    /**
     * Renglones de producto de una orden de envío que no dicen bien qué es.
     * Cada renglón tiene que decir:
     *   · si es CALZONCITO o CINTA, y
     *   · cuál: calzoncito Magic / de Noche / de Día (el blanco);
     *           cinta de Noche / Jumbo / la de 50 unidades.
     * Recién nacido, talla especial (4 a 7, 8 a 14 años), toallitas y
     * natación no se piden: son una sola presentación.
     * Devuelve [renglón => qué falta]. Vacío = está bien (o no es una orden).
     */
    public static function productosSinPresentacion(string $texto): array
    {
        $renglones = array_values(array_filter(array_map('trim', preg_split('/\R/u', $texto)),
            fn ($r) => preg_match('/\p{L}/u', $r)));
        if (! $renglones || ! preg_match('/orden de env[ií]o/iu', $renglones[0])) return [];
        if (! preg_match('/^\W*(producto|su pedido)/imu', $texto)) return [];

        // Los renglones entre "Producto(s):" (o "SU PEDIDO") y el envío/total.
        $lineas = [];
        $dentro = false;
        foreach (preg_split('/\R/u', str_replace('*', '', $texto)) as $l) {
            $limpio = trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', trim($l)));
            if (preg_match('/^(producto|su pedido)/iu', $limpio)) {
                $dentro = true;
                $resto = trim(preg_replace('/^(producto\(?s?\)?|su pedido)\s*:?/iu', '', $limpio));
                if ($resto !== '') $lineas[] = $resto;
                continue;
            }
            if (! $dentro) continue;
            if (preg_match('/^(costo|env[ií]o|total|💰|d[ií]a de entrega|nombre|tel[eé]fono|direcci|municipio|gracias)/iu', $limpio)
                || preg_match('/total a pagar|costo de env/iu', $l)) break;
            if ($limpio !== '' && preg_match('/\p{L}/u', $limpio)) $lineas[] = $limpio;
        }

        $mal = [];
        foreach ($lineas as $l) {
            $n = \App\Services\Asistente\Entender::normalizar($l);
            // "cinta (no calzoncito)": lo que se niega no cuenta.
            $n = preg_replace('/\bno (es )?(calzon\w*|cinta|sinta)\b/u', ' ', $n);

            if (preg_match('/recien nacid|\brn\b|talla especial|\b\d{1,2} a \d{1,2}\b|\banos\b|toall|natacion|adulto/u', $n)) continue;

            // Un renglón que solo dice la talla ("TALLA: L") es la continuación
            // del de arriba, no un producto aparte.
            $tallas = '(rn|s|m|l|g|xl|xxl|xxxl|2xl|3xl|xg|xxg|xxxg)';
            if (trim(preg_replace('/\b(\d+(\.\d+)?|talla|tallas|' . $tallas . '|paquetes?|de|\$\S*)\b|\$/u', ' ', $n)) === '') continue;

            // Magic y "el blanco" solo son calzoncito; Jumbo, Comfy y "la de 50
            // unidades" solo son cinta: con eso ya se entiende cuál es.
            $esCalzon = (bool) preg_match('/\b(calzon\w*|calson\w*|calzoncit\w*|pants|pant|panta|pantalon\w*|magic|majic|magik|blanco|blancos)\b/u', $n);
            $esCinta  = (bool) preg_match('/\b(cinta\w*|sinta\w*|pegar|ajuste lateral|jumbo|yumbo|yunbo|comfy\w*|confy\w*|50 unidades)\b/u', $n);

            // Lo que no es pañal (pacha, babero, cepillo, repuestos) no se revisa.
            if (! $esCalzon && ! $esCinta
                && ! preg_match('/\b(panal\w*|pamper\w*|panper\w*|paquetes?|talla|tallas|' . $tallas . ')\b/u', $n)) continue;

            if ($esCalzon && ! $esCinta) {
                if (! preg_match('/\b(magic|majic|magi|magik|noche|nocturn\w*|dia|diurno|blanco|blancos)\b/u', $n)
                    && ! static::presentacionPorPrecio('calzon', $l)) {
                    $mal[$l] = 'decí cuál calzoncito: *Magic*, *de Noche* o *de Día* (el blanco)';
                }
            } elseif ($esCinta && ! $esCalzon) {
                if (! preg_match('/\b(noche|nocturn\w*|jumbo|yumbo|yunbo|jumb\w*|comfy\w*|confy\w*|50 unidades|50u|de 50)\b/u', $n)
                    && ! static::presentacionPorPrecio('cinta', $l)) {
                    $mal[$l] = 'decí cuál cinta: *de Noche*, *Jumbo* o *la de 50 unidades*';
                }
            } else {
                $mal[$l] = 'decí si es *calzoncito* o *cinta*, y cuál';
            }

            // Y la talla: "Cinta de noche $18" sin talla salía igual.
            if (! preg_match('/\b(talla\s+\S+|' . $tallas . ')\b/u', $n)) {
                $mal[$l] = isset($mal[$l]) ? $mal[$l] . ', y la *talla*' : 'falta la *talla* (RN, S, M, L, XL, XXL, XXXL)';
            }
        }

        return $mal;
    }

    /**
     * Acomoda la orden de envío antes de mandarla, para que se lea de un
     * vistazo: los datos arriba, el pedido aislado entre dos líneas con el
     * producto y el precio en negrita, el envío aparte y el total en negrita.
     * No cambia ningún dato: solo el orden visual, espacios y renglones.
     *
     * Si algo no cuadra (no es una orden, o al releerla el total o los
     * productos no dan igual que antes), se manda tal cual la escribieron.
     */
    private function formatearOrden(string $texto): string
    {
        $original = $texto;

        $renglones = array_values(array_filter(array_map('trim', preg_split('/\R/u', $texto)),
            fn ($r) => preg_match('/\p{L}/u', $r)));
        if (! $renglones || ! preg_match('/orden de env[ií]o/iu', $renglones[0])) return $original;
        if (! preg_match('/^\W*(producto|su pedido)/imu', $texto) || ! preg_match('/^\W*total/imu', $texto)) return $original;

        $etiquetas = [
            'nombre'    => '/^(nombre completo|nombre)\b/iu',
            'telefono'  => '/^(tel[eé]fono|tel|celular|whatsapp|n[uú]mero)\b/iu',
            'municipio' => '/^(municipio)\b/iu',
            'direccion' => '/^(direcci[oó]n exacta|direcci[oó]n)\b/iu',
            'entrega'   => '/^(d[ií]a de entrega)\b/iu',
            'productos' => '/^(producto\(s\)|productos|producto|su pedido)/iu',
            'envio'     => '/^(costo de env[ií]o|env[ií]o)\b/iu',
            'total'     => '/^(total a pagar|total)\b/iu',
        ];
        $campos = array_fill_keys(array_keys($etiquetas), []);
        $arriba = [];      // renglones sueltos antes de la primera etiqueta
        $cierre = [];      // "¡Gracias por tu preferencia!…"
        $actual = null;
        $encabezado = false;

        foreach (preg_split('/\R/u', str_replace('*', '', $texto)) as $linea) {
            $cruda = trim($linea);
            if ($cruda === '' || ! preg_match('/[\p{L}\p{N}]/u', $cruda)) continue;   // vacíos y ━━━
            $l = trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', $cruda));

            if (! $encabezado && preg_match('/orden de env[ií]o/iu', $l)) { $encabezado = true; continue; }

            if (preg_match('/gracias por (tu|su) preferencia|estar\p{L}? en camino/iu', $l)) {
                $cierre[] = $cruda; $actual = 'cierre'; continue;
            }
            if ($actual === 'cierre') { $cierre[] = $cruda; continue; }

            $es = null; $mm = [];
            foreach ($etiquetas as $k => $rx) {
                if (preg_match($rx, $l, $mm)) { $es = $k; break; }
            }
            if ($es) {
                $actual = $es;
                // Lo que sigue a la etiqueta, con o sin dos puntos
                // ("Costo de envío$2.50", "Nombre completo: Karla").
                $valor = trim((string) preg_replace('/^\s*[:：]?\s*/u', '', mb_substr($l, mb_strlen($mm[0]))));
                $valor = trim((string) preg_replace('/^[:：]\s*/u', '', $valor));
                if ($valor !== '') $campos[$es][] = $valor;
                continue;
            }

            $l = trim((string) preg_replace('/^[•·▪◦\-–—]+\s*/u', '', $l));
            if ($actual === null) $arriba[] = $l; else $campos[$actual][] = $l;
        }

        $productos = $campos['productos'];
        if (! $productos || ! $campos['total']) return $original;

        // El teléfono escrito adentro del nombre va a su renglón.
        $nombre = trim(implode(' ', $campos['nombre']));
        $tel = trim(implode(' ', $campos['telefono']));
        $rxTel = '/(?<!\d)(?:\+?503[\s.-]*)?([267]\d{3})[\s.-]?(\d{4})(?!\d)/u';
        if (preg_match($rxTel, $nombre, $mt)) {
            if ($tel === '') $tel = $mt[1] . ' ' . $mt[2];
            $sinTel = trim(preg_replace('/\s{2,}/u', ' ', preg_replace($rxTel, ' ', $nombre)));
            if ($sinTel !== '') $nombre = $sinTel;
        }

        $junta = fn (array $v, string $sep) => trim((string) preg_replace('/\s{2,}/u', ' ', implode($sep, array_filter(array_map('trim', $v), fn ($x) => $x !== ''))));
        $dinero = fn (string $v) => trim((string) preg_replace('/\$\s+(?=\d)/u', '$', $v));

        $out = ['📦 *ORDEN DE ENVÍO* 🚚', ''];
        foreach ($arriba as $a) $out[] = $a;
        if ($nombre !== '')                      $out[] = '👤 *Nombre:* ' . $nombre;
        if ($tel !== '')                         $out[] = '📱 *Teléfono:* ' . $tel;
        if ($m = $junta($campos['municipio'], ', ')) $out[] = '📍 *Municipio:* ' . $m;
        if ($d = $junta($campos['direccion'], ', ')) $out[] = '🏠 *Dirección:* ' . $d;
        if ($e = $junta($campos['entrega'], ' '))    $out[] = '📅 *Día de entrega:* ' . $e;

        $out[] = '';
        $out[] = '━━━━━━━━━━━━━━';
        $out[] = '🛍️ *SU PEDIDO*';
        // "TALLA: L" en su propio renglón es parte del producto de arriba.
        $junt = [];
        foreach ($productos as $p) {
            $p = trim(preg_replace('/[✅☑✔]\x{FE0F}?/u', '', $p));
            if ($p === '') continue;
            if ($junt && ! preg_match('/^\d/u', $p) && ! str_contains($p, '$') && preg_match('/^talla/iu', $p)) {
                $junt[count($junt) - 1] .= ' ' . $p;
                continue;
            }
            $junt[] = $p;
        }
        $productos = $junt;
        if (! $productos) return $original;

        foreach ($productos as $p) {
            $p = trim(preg_replace('/\s{2,}/u', ' ', $p));
            $pos = mb_strpos($p, '$');
            if ($pos !== false && $pos > 0) {
                $desc = rtrim(trim(mb_substr($p, 0, $pos)), " -–—:");
                $precio = $dinero(mb_substr($p, $pos));
                $out[] = '• *' . $desc . '* — *' . $precio . '*';
            } else {
                $out[] = '• *' . $p . '*';
            }
        }
        $out[] = '━━━━━━━━━━━━━━';
        $out[] = '';
        if ($v = $junta($campos['envio'], ' ')) $out[] = '🚚 Envío: ' . $dinero($v);
        $out[] = '💰 *TOTAL A PAGAR: ' . $dinero($junta($campos['total'], ' ')) . '*';
        if ($cierre) { $out[] = ''; foreach ($cierre as $c) $out[] = $c; }

        $nuevo = implode("\n", $out);

        // Red de seguridad: releída, tiene que decir lo mismo que la original.
        try {
            $a = $this->leerOrden($original);
            $b = $this->leerOrden($nuevo);
            $prodA = preg_replace('/[^\p{L}\p{N}$.]/u', '', mb_strtolower((string) $a['productos']));
            $prodB = preg_replace('/[^\p{L}\p{N}$.]/u', '', mb_strtolower((string) $b['productos']));
            // (Si la original no se dejaba leer el envío o el total —"Costo de
            // envío$2.50" sin dos puntos—, la acomodada sí: eso no es error.)
            $tA = static::montoDe($a['total']); $tB = static::montoDe($b['total']);
            $eA = static::montoDe($a['envio']); $eB = static::montoDe($b['envio']);
            if (($tA > 0 && $tA !== $tB)
                || ($eA > 0 && $eA !== $eB)
                || $prodA !== $prodB) {
                return $original;
            }
        } catch (\Throwable $e) {
            return $original;
        }

        return $nuevo;
    }

    /**
     * ¿El precio ya dice cuál es? "Cinta XL $23" → en el catálogo, de las
     * cintas (de esa talla, si la dice) solo una vale $23: la Jumbo. Si dos
     * valen lo mismo (calzoncito de día y de noche, $20), no alcanza.
     */
    private static function presentacionPorPrecio(string $tipo, string $renglon): bool
    {
        static $catalogo = null;

        try {
            if ($catalogo === null) {
                $catalogo = \App\Models\ProductSize::with('product:id,name')->get(['id', 'product_id', 'size', 'price'])
                    ->map(fn ($s) => ['prod' => (int) $s->product_id, 'nombre' => mb_strtolower((string) $s->product?->name),
                                      'talla' => mb_strtoupper(trim((string) $s->size)), 'precio' => round((float) $s->price, 2)])
                    ->all();
            }
        } catch (\Throwable $e) {
            return false;
        }

        // El precio por paquete: el que no está entre paréntesis ("$17 ($34)").
        $sinParen = preg_replace('/\([^)]*\)/u', ' ', $renglon);
        if (! preg_match_all('/\$\s*(\d+(?:[.,]\d{1,2})?)/u', $sinParen, $m) || ! $m[1]) return false;
        $precio = (float) str_replace(',', '.', $m[1][0]);
        $cant = preg_match('/^\D{0,3}(\d{1,2})\b/u', $renglon, $c) ? max(1, (int) $c[1]) : 1;
        $precios = array_unique([round($precio, 2), round($precio / $cant, 2)]);

        // La talla, si la dice.
        $alias = ['2XL' => 'XXL', '3XL' => 'XXXL', 'G' => 'L', 'XG' => 'XL', 'XXG' => 'XXL', 'XXXG' => 'XXXL'];
        $talla = preg_match('/\b(xxxl|xxl|2xl|3xl|xl|rn|xxxg|xxg|xg|s|m|l|g)\b/iu', $sinParen, $t) ? mb_strtoupper($t[1]) : null;
        if ($talla) $talla = $alias[$talla] ?? $talla;

        $esDelTipo = fn ($nombre) => $tipo === 'calzon'
            ? (bool) preg_match('/calzon/u', $nombre) && ! preg_match('/especial|natac/u', $nombre)
            : (bool) preg_match('/cinta|comfy|ajuste lateral|pegar/u', $nombre) && ! preg_match('/reci[eé]n nacid/u', $nombre);

        $prods = [];
        foreach ($catalogo as $s) {
            if (! $esDelTipo($s['nombre'])) continue;
            if ($talla && $s['talla'] !== $talla) continue;
            if (! in_array($s['precio'], $precios, true)) continue;
            $prods[$s['prod']] = true;
        }

        return count($prods) === 1;
    }

    private function pedirRevisarOrden(string $texto, ?WaConversacion $conv = null): ?string
    {
        /*
         * Tiene que ser LA ORDEN, no un mensaje que la nombra ("ya le hago la
         * orden de envío", "desde la página le da enviar orden de envío").
         *
         *  1. El primer renglón con letras es el encabezado "Orden de envío".
         *  2. Trae los renglones de la plantilla: Producto y Total sí o sí, y
         *     al menos dos de nombre / teléfono / municipio / dirección.
         *  3. No pregunta ya en el mismo mensaje si está bien ("revise…").
         *  4. No se mandó la misma pregunta en este chat hace menos de 3
         *     minutos (si se manda la orden dos veces seguidas, va una sola).
         */
        $renglones = array_values(array_filter(array_map('trim', preg_split('/\R/u', $texto)),
            fn ($r) => preg_match('/\p{L}/u', $r)));
        if (! $renglones || ! preg_match('/orden de env[ií]o/iu', $renglones[0])) return null;

        if (! preg_match('/^\W*(producto|su pedido)/imu', $texto)) return null;
        if (! preg_match('/^\W*total/imu', $texto)) return null;

        $datos = 0;
        foreach (['nombre', 'tel[eé]fono', 'municipio', 'direcci[oó]n'] as $c) {
            if (preg_match('/^\W*' . $c . '/imu', $texto)) $datos++;   // con o sin dos puntos
        }
        if ($datos < 2) return null;

        if (preg_match('/\b(revis[ae]|revisar|est[aá] correct[ao]|est[aá]n correctos)\b/iu', $texto)) return null;

        if ($conv && WaMensaje::where('conversacion_id', $conv->id)
                ->where('direccion', 'saliente')
                ->where('created_at', '>=', now()->subMinutes(3))
                ->where('texto', self::PREGUNTA_REVISAR)
                ->exists()) {
            return null;
        }

        return self::PREGUNTA_REVISAR;
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
                ->warning()->duration(8000)->send();
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

    /**
     * Vuelve a mandar un mensaje que falló, sin escribirlo de nuevo.
     *
     * El texto se reintenta en el mismo globo. La foto sale de nuevo (Meta la
     * vuelve a buscar) y el globo viejo queda marcado como reenviado.
     */
    public function reintentarEnvio(int $id): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        $m = \App\Models\WaMensaje::where('conversacion_id', $conv->id)->whereKey($id)->first();
        if (! $m || $m->estado !== 'fallido' || $m->direccion !== 'saliente') return;

        if (! $conv->ventanaAbierta()) {
            Notification::make()->title('La ventana de 24 horas está cerrada')
                ->body('Hay que esperar a que el cliente escriba de nuevo.')->warning()->send();
            return;
        }

        if ($m->tipo === 'text') {
            $m->update(['estado' => 'enviando', 'error' => null]);
            $m = WhatsappApi::despacharTexto($m->fresh());
        } elseif ($m->tipo === 'image' && str_starts_with((string) $m->media_ruta, 'http')) {
            $nuevo = WhatsappApi::enviarImagen($conv, (string) $m->media_ruta, $m->texto ?: null, auth()->id());
            if ($nuevo->estado !== 'fallido') {
                $m->update(['error' => 'Se volvió a mandar abajo ↓']);
            }
            $m = $nuevo;
        } else {
            return;
        }

        $m->estado === 'fallido'
            ? Notification::make()->title('Tampoco salió')->body((string) $m->error)->danger()->duration(8000)->send()
            : Notification::make()->title('Mensaje enviado')->success()->send();
    }

    // ── El asistente de ventas ───────────────────────────────────────────────

    /**
     * Lo que muestra la franja del asistente arriba del chat, o null si en
     * este chat no puede atender (mientras se prueba: todos menos los números
     * de prueba).
     */
    public function asistenteEnChat(): ?array
    {
        $conv = $this->conversacion();
        if (! $conv || ! \App\Models\AsistenteFicha::hayTabla()) return null;

        try {
            if (! config('asistente.activo')) return null;

            $f = \App\Models\AsistenteFicha::de($conv);

            // Apagado en general (o fuera de los números de prueba): igual se
            // puede encender en este chat.
            if (! \App\Services\Asistente\Asistente::numeroPermitido($conv)) {
                return [
                    'estado'    => 'Apagado (el botón general está apagado)',
                    'paso'      => null,
                    'motivo'    => null,
                    'encendido' => false,
                    'prueba'    => false,
                    'clase'     => 'off',
                ];
            }

            $estado = ($f ? $f->estadoLegible() : 'Esperando') . ($f && $f->a_mano && ! \App\Services\Asistente\Asistente::encendidoGeneral() ? ' (encendido solo en este chat)' : '');
            $encendido = ! $f || in_array($f->estado, ['listo', 'activo', 'terminado'], true);

            if (! $f || $f->estado === 'listo') {
                $porQue = \App\Services\Asistente\Asistente::porQueNoEmpieza($conv, $f);
                if ($porQue) $estado = 'No arranca: ' . $porQue;
            }

            return [
                'estado'    => $estado,
                'paso'      => $f && $f->estado === 'activo' ? $f->pasoLegible() : null,
                'motivo'    => $f && in_array($f->estado, ['wil', 'apagado'], true) ? $f->motivo : null,
                'encendido' => $encendido,
                'prueba'    => \App\Services\Asistente\Asistente::enPrueba(),
                'clase'     => $f && $f->estado === 'wil' ? 'wil' : ($encendido ? '' : 'off'),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Lo vuelve a dejar listo para arrancar con el próximo mensaje del cliente. */
    public function asistenteEncender(): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        \App\Models\AsistenteFicha::updateOrCreate(
            ['conversacion_id' => $conv->id],
            [
                'estado' => 'listo', 'paso' => null, 'datos' => [], 'motivo' => null, 'a_mano' => true,
                // Lo que ya se habló no se vuelve a leer, y lo que escribiste
                // antes de encenderlo no lo frena.
                'ultimo_mensaje_id' => \App\Models\WaMensaje::where('conversacion_id', $conv->id)->max('id'),
                'desde' => now(),
            ]
        );

        // Ya no es pendiente del equipo: sale de "🤖 Revisar".
        try {
            $rev = \App\Models\WaEtiqueta::porRol('asistente');
            if ($rev) $conv->etiquetas()->detach($rev->id);
        } catch (\Throwable $e) {
        }

        Notification::make()->title('Asistente encendido en este chat')
            ->body('Arranca cuando el cliente escriba, aunque el botón general esté apagado.')->success()->send();
    }

    // ── El botón general: encender o apagar el asistente para todos ─────────

    public function asistenteGeneralEncendido(): bool
    {
        return \App\Services\Asistente\Asistente::encendidoGeneral();
    }

    public function asistenteGeneralAlternar(): void
    {
        if (! config('asistente.activo')) {
            Notification::make()->title('El asistente está desactivado en Railway')
                ->body('Falta la variable ASISTENTE=true.')->danger()->send();
            return;
        }

        $encender = ! $this->asistenteGeneralEncendido();
        \App\Models\Setting::put('asistente_encendido', $encender ? '1' : '0');

        if ($encender) {
            Notification::make()->title('🤖 Asistente encendido')
                ->body('Contesta a los clientes que escriban. No entra en chats que el equipo esté atendiendo.')
                ->success()->send();
            return;
        }

        $n = \App\Services\Asistente\Asistente::apagarTodo();
        Notification::make()->title('Asistente apagado')
            ->body($n > 0 ? "{$n} chats que tenía a medias quedaron en 🤖 Revisar." : 'No tenía chats a medias.')
            ->success()->send();
    }

    public function asistenteApagar(): void
    {
        $conv = $this->conversacion();
        if (! $conv) return;

        \App\Models\AsistenteFicha::updateOrCreate(
            ['conversacion_id' => $conv->id],
            ['estado' => 'apagado', 'motivo' => 'lo apagaste', 'a_mano' => false]
        );

        Notification::make()->title('Asistente apagado en este chat')->success()->send();
    }

    /**
     * Empezar la prueba de cero: se borra la ficha y se quitan las etiquetas del
     * recorrido del pedido, que si no, lo frenarían ("tiene un pedido abierto").
     * Solo en modo prueba.
     */
    public function asistenteReiniciar(): void
    {
        $conv = $this->conversacion();
        if (! $conv || ! \App\Services\Asistente\Asistente::enPrueba()) return;
        if (! \App\Services\Asistente\Asistente::numeroPermitido($conv)) return;

        foreach (['pedido', 'procesada', 'entregada', 'sin_cobro', 'agotado'] as $rol) {
            $e = \App\Models\WaEtiqueta::porRol($rol);
            if ($e) $conv->etiquetas()->detach($e->id);
        }

        \App\Models\AsistenteFicha::where('conversacion_id', $conv->id)->delete();

        $this->asistenteEncender();
    }

    // ── El banco de ejemplos del asistente ──────────────────────────────────

    public function cuantosEjemplos(): int
    {
        return \App\Services\Asistente\Ejemplos::cuantos();
    }

    /** Vuelve a juntar tus respuestas reales de los chats. */
    public function actualizarEjemplos(): void
    {
        $n = \App\Services\Asistente\Ejemplos::reconstruir();

        \Filament\Notifications\Notification::make()
            ->title($n > 0 ? "Listo: {$n} ejemplos de tus respuestas" : 'No se pudieron juntar ejemplos')
            ->body($n > 0 ? 'El asistente ya contesta mirando cómo lo hacés vos.' : 'Revisá que la tabla exista (php artisan migrate).')
            ->{$n > 0 ? 'success' : 'warning'}()
            ->send();
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

    /**
     * El botón de una talla, en el renglón de abajo del chat.
     *
     * Abre el catálogo YA parado en esa talla, con sus presentaciones
     * marcadas. Antes eran dos toques —"Catálogo" y después la talla en la
     * grilla— para algo que se hace decenas de veces al día. Ahora es uno.
     */
    public function catalogoDeTalla(string $talla): void
    {
        $this->abrirCatalogo();
        $this->elegirTalla($talla);
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

    /**
     * El catálogo para la barra "/": escribir /m muestra las presentaciones
     * con existencia de la talla M, con su foto y precio, para mandarlas de
     * un toque. Clave: la talla en mayúsculas.
     */
    public function catalogoParaSlash(): array
    {
        try {
            $filas = \App\Models\ProductSize::with('product')
                ->where('price', '>', 0)
                ->where('quantity', '>', 0)
                ->whereHas('product', fn ($q) => $q->where('active', true))
                ->get()
                ->sortBy(fn ($s) => $s->product->orden ?? 0);
        } catch (\Throwable $e) {
            return [];
        }

        $cat = [];
        foreach ($filas as $s) {
            $talla = trim((string) $s->size);
            if ($talla === '' || preg_match('/\s/u', $talla)) continue;   // "4 a 7 años" no se escribe con la barra
            $k = mb_strtoupper($talla);
            $cat[$k]['talla'] = $talla;
            $cat[$k]['items'][] = [
                'id'       => (string) $s->id,
                'nombre'   => trim((string) preg_replace('/\s*\(.*$/u', '', (string) $s->product->name)),
                'precio'   => number_format((float) $s->price, 2),
                'unidades' => (int) ($s->unidades ?? 0),
                'foto'     => $this->fotoDe($s, $s->product),
            ];
        }

        return $cat;
    }

    /** Desde la barra "/": manda esas presentaciones de la talla (foto, precio y enlace). */
    public function mandarDeTalla(string $talla, array $ids): void
    {
        $validos = $this->presentacionesDe($talla)->pluck('id')->map(fn ($i) => (string) $i)->all();
        $ids = array_values(array_intersect(array_map('strval', $ids), $validos));

        if (! $ids) {
            Notification::make()->title('Esa presentación ya no tiene existencia')->warning()->send();
            return;
        }

        $this->texto = '';
        $this->tallaElegida = $talla;
        $this->elegidas = $ids;
        $this->enviarElegidas();
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

            /*
             * Cada producto sale como MENSAJE CON SU ENLACE, no como foto.
             *
             * Mandado como foto, el cliente tocaba la imagen y WhatsApp la
             * abría en grande — y ahí se quedaba: una foto en WhatsApp nunca
             * lleva a una página. Mandado como texto con el enlace, WhatsApp
             * arma la tarjeta con la foto del producto (la de esa talla, por
             * el ?t=) y tocarla abre la página, donde puede pedir.
             *
             * UN producto por mensaje, y eso no es casualidad: WhatsApp arma
             * una sola tarjeta por mensaje, la del primer enlace. Con dos
             * productos en el mismo mensaje, el segundo se quedaba sin foto.
             *
             * Para volver a mandarlo como foto: WHATSAPP_CATALOGO_COMO_FOTO=true
             * en Railway.
             */
            // Ahora va como FOTO grande, con los datos y el enlace abajo (Wil:
            // la tarjetita del enlace se veía muy pequeña). El enlace sigue en
            // el texto para que pueda entrar a la página. Si la foto no está
            // en JPG/PNG se pasa a JPG; si no se puede, va como antes.
            $absoluta = config('whatsapp.catalogo_como_foto', true)
                ? \App\Services\FotoWhatsapp::url($this->fotoDe($s, $p), (int) $s->id)
                : null;

            if (! $absoluta) {
                $sinFoto[] = trim((string) $p->name);
                $m = WhatsappApi::enviarTexto($conv, $pie, auth()->id());
            } else {
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
                ->warning()->duration(8000)->send();
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
            // Cada 30 segundos basta: es un aviso, no un contador en vivo.
            $p['sin_enlace'] = (int) \Illuminate\Support\Facades\Cache::remember('wa:sin_enlace', 30, fn () =>
                \App\Models\WaConversacion::columnaExiste('guias_borrador', 'enviado_at')
                    ? \App\Models\GuiaBorrador::whereNull('enviado_at')->count()
                    : 0
            );
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
                    ->warning()->duration(8000)->send();
                return;
            }

            \Illuminate\Support\Facades\Artisan::call('entregas:revisar', ['--limite' => 15]);

            Notification::make()
                ->title('Revisión terminada')
                ->body(trim(\Illuminate\Support\Facades\Artisan::output()) ?: 'Sin novedades.')
                ->success()->duration(8000)->send();
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
                ->warning()->duration(8000)->send();
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

        // (string): una respuesta de solo fotos no tiene texto, y el cuadro de
        // escribir no acepta "nada" — acepta un texto vacío.
        $this->texto = (string) $r->texto;
        $this->pestana = 'chat';
        $this->abrirCajaSiHaceFalta((string) $r->texto);

        // Si la respuesta lleva foto, la foto NO se manda todavía: queda
        // esperando pegada al cuadro de texto. Así seguís pudiendo corregir el
        // texto —agregar el nombre, cambiar un precio— y cuando le des a
        // Enviar sale una sola cosa: la foto con ese texto de pie.
        $this->rapidaFoto = $r->tieneFoto() ? $r->id : null;

        /*
         * Solo texto → sale de una, sin buscar el botón Enviar. Ya se sabe qué
         * se va a mandar; dejarla en el cuadro era un paso de más.
         *
         * Menos las que son para LLENAR: la orden de envío y cualquier otra
         * con renglones vacíos ("Nombre:", "Teléfono:" sin nada al lado).
         * Esas se quedan en el cuadro para completarlas. Las de foto también,
         * como siempre, para poder corregir el texto de pie.
         */
        $t = trim((string) $r->texto);
        $paraLlenar = preg_match('/orden de env[ií]o/iu', $t) || preg_match('/:[ \t]*$/mu', $t);

        if (! $r->tieneFoto() && $t !== '' && ! $paraLlenar) {
            $this->enviar();
        }
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

    // ═══ Mandar una foto de la computadora o del teléfono ════════════════════

    /**
     * La foto que se está subiendo desde el aparato.
     *
     * Hasta ahora solo se podían mandar fotos que YA estuvieran cargadas en el
     * admin: las del catálogo y las de las respuestas rápidas. Eso sirve para
     * lo que se repite, y no sirve para lo que pasa una vez — mandarle la foto
     * de cómo quedó su paquete, o la del producto que preguntó y no está
     * cargado.
     */
    public $fotoSuelta = null;

    /**
     * Apenas se elige el archivo, se manda.
     *
     * Sin paso intermedio de confirmar a propósito: elegir una foto para
     * mandársela a alguien YA es la confirmación. Un botón más solo agrega un
     * toque en un teléfono, que es donde más cuesta.
     *
     * El pie es lo que haya escrito en el cuadro, si escribió algo. Así se
     * puede mandar la foto con su explicación de una sola vez, como en
     * WhatsApp.
     */
    public function updatedFotoSuelta(): void
    {
        $conv = $this->conversacion();

        if (! $conv) { $this->fotoSuelta = null; return; }

        /*
         * Una o varias, da igual.
         *
         * Antes el selector aceptaba UNA sola foto, y para mandar tres había
         * que repetir el camino tres veces. Ahora se eligen todas juntas y
         * salen en el orden en que se eligieron.
         *
         * Con "multiple" Livewire entrega una lista; sin él, un archivo
         * suelto. Se trata todo como lista para no tener dos caminos.
         */
        $fotos = is_array($this->fotoSuelta) ? array_values($this->fotoSuelta) : [$this->fotoSuelta];
        $fotos = array_values(array_filter($fotos));

        if (! $fotos) { $this->fotoSuelta = null; return; }

        // 5 MB cada una. WhatsApp aguanta bastante más, pero una foto de
        // teléfono sin achicar son 8 o 10 MB y tarda una eternidad en subir
        // por datos móviles, que es como trabajás vos. Y hasta 10 de una vez:
        // más que eso ya es un álbum, y en el chat del cliente se vuelve una
        // pared de fotos.
        try {
            $this->validate([
                'fotoSuelta'   => ['required'],
                'fotoSuelta.*' => ['image', 'max:5120'],
            ], [
                'fotoSuelta.*.image' => 'Una de las fotos no es una imagen.',
                'fotoSuelta.*.max'   => 'Una de las fotos pasa de 5 MB. Achicala y probá de nuevo.',
            ]);

            if (count($fotos) > 10) {
                throw new \RuntimeException('Son más de 10 fotos. Mandalas en dos tandas.');
            }
        } catch (\Throwable $e) {
            $this->fotoSuelta = null;

            Notification::make()
                ->title('No se pudo mandar')
                ->body($e instanceof \Illuminate\Validation\ValidationException
                    ? implode(' ', array_unique(\Illuminate\Support\Arr::flatten($e->errors())))
                    : ($e->getMessage() ?: 'Revisá el archivo.'))
                ->warning()->send();
            return;
        }

        if (! $conv->ventanaAbierta()) {
            $this->fotoSuelta = null;

            Notification::make()
                ->title('La ventana de 24 horas está cerrada')
                ->body('Hay que esperar a que el cliente escriba de nuevo.')
                ->warning()->send();
            return;
        }

        // El texto del cuadro va de pie SOLO en la primera. Repetido debajo de
        // cada foto se leería tres veces lo mismo; así es como lo hace
        // WhatsApp cuando mandás un grupo.
        $pie = trim($this->texto) !== '' ? trim($this->texto) : null;

        $salieron = 0;
        $fallos   = [];

        foreach ($fotos as $i => $archivo) {
            try {
                // Al disco público, que es de donde Meta la va a descargar. Se
                // guarda con las de WhatsApp para que la limpieza automática de
                // fotos viejas también las alcance y no llenen el volumen.
                $ruta = $this->guardarComoAcepta($archivo);

                if (! $ruta) {
                    throw new \RuntimeException(
                        'formato que WhatsApp no acepta y no se pudo convertir (guardala como JPG)'
                    );
                }

                $m = WhatsappApi::enviarImagen(
                    $conv,
                    url('/storage/' . $ruta),
                    $i === 0 ? $pie : null,
                    auth()->id()
                );

                if ($m->estado === 'fallido') {
                    $fallos[] = 'foto ' . ($i + 1) . ': ' . ($m->error ?: 'WhatsApp la rechazó');
                } else {
                    $salieron++;
                }
            } catch (\Throwable $e) {
                // Una que falla no frena a las demás: se anota y se sigue.
                $fallos[] = 'foto ' . ($i + 1) . ': ' . $e->getMessage();
            }
        }

        // El cuadro se limpia solo si el texto se fue como pie de la primera:
        // si esa falló, el texto se queda para no perderlo.
        if ($pie !== null && $salieron > 0 && ! str_starts_with($fallos[0] ?? '', 'foto 1:')) {
            $this->texto = '';
        }

        if ($fallos) {
            Notification::make()
                ->title($salieron
                    ? "Salieron {$salieron} de " . count($fotos)
                    : (count($fotos) === 1 ? 'La foto no salió' : 'No salió ninguna'))
                ->body(implode(' · ', $fallos))
                ->danger()->persistent()->send();
        }

        $this->fotoSuelta = null;
    }

    /**
     * Guarda la foto en un formato que WhatsApp acepte y devuelve su ruta.
     *
     * ACÁ ESTABA EL "Media upload error". WhatsApp solo acepta JPG y PNG para
     * imágenes. Ni WEBP, ni HEIC, ni AVIF, ni JFIF. Y son justo los formatos
     * en los que caen las fotos hoy: WEBP es lo que se baja de cualquier
     * página web, HEIC es lo que saca un iPhone de fábrica.
     *
     * Desde el panel se ven bien —el navegador los muestra sin problema— así
     * que no hay ninguna señal de que algo esté mal hasta que Meta lo rechaza.
     *
     * Rechazarlos y pedirte que los conviertas sería pasarte el trabajo. Se
     * convierten acá, a JPG, si el servidor tiene con qué. Los que ya vienen
     * bien no se tocan: recomprimir un JPG solo le baja calidad.
     *
     * Devuelve null si no hay forma, y ahí sí se avisa.
     */
    private function guardarComoAcepta($archivo): ?string
    {
        $mime = (string) $archivo->getMimeType();

        // Ya viene bien: se guarda tal cual.
        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return $archivo->store('whatsapp/sueltas', 'public') ?: null;
        }

        // GD es lo que convierte. Suele estar, pero no se da por sentado.
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        try {
            $imagen = @imagecreatefromstring(file_get_contents($archivo->getRealPath()));
            if (! $imagen) return null;

            // Fondo blanco antes de aplanar: un PNG o un WEBP con transparencia
            // pasado a JPG sin esto queda con el fondo negro.
            $ancho = imagesx($imagen);
            $alto  = imagesy($imagen);

            $plano = imagecreatetruecolor($ancho, $alto);
            imagefill($plano, 0, 0, imagecolorallocate($plano, 255, 255, 255));
            imagecopy($plano, $imagen, 0, 0, 0, 0, $ancho, $alto);

            ob_start();
            imagejpeg($plano, null, 88);
            $bytes = ob_get_clean();

            imagedestroy($imagen);
            imagedestroy($plano);

            if (! $bytes) return null;

            $ruta = 'whatsapp/sueltas/' . \Illuminate\Support\Str::random(40) . '.jpg';

            \Illuminate\Support\Facades\Storage::disk('public')->put($ruta, $bytes);

            return $ruta;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Convirtiendo foto suelta: ' . $e->getMessage());
            return null;
        }
    }

    // ═══ Pestaña "Tomar pedido" ═════════════════════════════════════════════

    /** Lo que ya sabemos de quien está escribiendo. */
    private function cargarDatosDelCliente(WaConversacion $conv): void
    {
        // Desde un número de afuera (+1 281 707 6729): ese mismo, completo. Es
        // el WhatsApp del cliente. Si alguien recibe acá, va en el otro campo.
        $this->pedTelefono = $conv->esExtranjero()
            ? WaConversacion::internacional((string) $conv->wa_id)
            : ($conv->telefono ?: '');
        // El nombre del perfil de WhatsApp no sirve para la guía: en blanco
        // hasta que salga de la orden o de la libreta.
        $this->pedNombre   = '';

        // Que el segundo teléfono de un pedido no se cuele en el siguiente.
        $this->pedTelefonoRecibe = '';

        $cliente = $conv->cliente();
        if ($cliente) {
            // Sin el "AIWIBI": es la marca de que el cliente es de ellos. Si
            // ahora te compra a vos, el nombre guardado no lo tiene que traer
            // (si no, la guía contaría como de AIWIBI). Si sí es de ellos, lo
            // escribís en la orden y ese se respeta.
            $this->pedNombre       = static::sinAiwibi($this->limpiarNombre($cliente['nombre'] ?? '')) ?: $this->pedNombre;
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
        $this->pedLineas[] = ['size_id' => '', 'cantidad' => 1, 'precio' => ''];
    }

    /** El precio unitario de un renglón: el escrito, o el del catálogo. */
    public function precioLinea(array $l): float
    {
        // Techo más bajo que el del total: un renglón suelto de pañales no
        // pasa de unas decenas. Si sale más, se leyó mal.
        $escrito = static::montoDe($l['precio'] ?? '', 500.0);

        if ($escrito > 0) return $escrito;

        $s = $this->presentacion($l['size_id'] ?? null);

        return $s ? round((float) $s->price, 2) : 0.0;
    }

    /** El del catálogo, para poder mostrarlo al lado cuando difiere. */
    public function precioCatalogo(array $l): float
    {
        $s = $this->presentacion($l['size_id'] ?? null);

        return $s ? round((float) $s->price, 2) : 0.0;
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
            // Sin producto elegido no hay renglón que sumar, aunque tenga
            // precio escrito: no se sabría qué se está cobrando.
            if (! $this->presentacion($l['size_id'] ?? null)) continue;

            $total += $this->precioLinea($l) * max(1, (int) ($l['cantidad'] ?? 1));
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
            // Por montoDe() y no por (float) a secas: "$34.00" casteado
            // directo da 0, y "34 y pico" da 34 por casualidad. Acá se lee el
            // número de verdad, y lo absurdo se descarta.
            return static::montoDe($this->pedCobrarManual);
        }

        // Sin total escrito y sin productos del catálogo no hay nada que
        // cobrar. Mostrar el costo de envío suelto solo confundiría.
        if (empty($this->pedLineas)) return 0.0;

        return round($this->subtotalPedido() + $this->envioPedido(), 2);
    }

    /**
     * El primer monto que hay en un texto, como número.
     *
     * ACÁ ESTABA EL TOTAL DE TRES MIL MILLONES, y el error era mío.
     *
     * Yo venía sacando el número así:
     *
     *     preg_replace('/[^\d.]/', '', $texto)
     *
     * Eso no extrae un número: borra todo lo que no sea dígito y PEGA lo que
     * queda. Con "34 y el tel 72837585" devuelve "3472837585". Con "$17($34)"
     * devuelve "1734". Nunca falla ruidosamente — siempre devuelve algo que
     * parece un número, y por eso pasó hasta la pantalla.
     *
     * Lo correcto es buscar el PRIMER monto y quedarse con ese.
     *
     * Y un techo, porque un error así no puede volver a llegar a una guía: un
     * pedido de pañales no pasa de unos cientos de dólares. Lo que pase de
     * $2000 es basura de lectura, no una venta.
     */
    public static function montoDe($texto, float $techo = 2000.0): float
    {
        $t = (string) $texto;

        if (! preg_match('/\d+(?:[.,]\d{1,2})?/', $t, $m)) return 0.0;

        $n = (float) str_replace(',', '.', $m[0]);

        return ($n > 0 && $n <= $techo) ? round($n, 2) : 0.0;
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

        // Base: lo que ya sabemos de la libreta, de entregas anteriores.
        $cliente = $conv->cliente();

        // El nombre del perfil de WhatsApp NO: suele ser un apodo, un emoji o el
        // de otra persona. Si no se sabe, queda en blanco para escribirlo.
        $nombre    = static::sinAiwibi((string) ($cliente['nombre'] ?? ''));
        $telefono  = $conv->esExtranjero() ? '' : $conv->telefono;
        $municipio = $cliente['municipio'] ?? '';
        $direccion = $cliente['direccion'] ?? '';
        $productos = '';
        // El envío que se le cobró la última vez ($2, $2.50, $2.90…): depende
        // de dónde vive, y casi siempre es el mismo de nuevo.
        $envio     = $this->envioAnterior($conv);
        $total     = '';

        /*
         * ACÁ SÍ ENTRA LA IA, y es el lugar donde sirve de verdad.
         *
         * La diferencia con el otro botón es exacta: allá la orden YA está
         * escrita y solo hay que transcribirla, así que leer con reglas es
         * mejor. Acá la orden NO existe todavía — la estás por escribir vos, y
         * los datos están repartidos en la conversación: el nombre en un
         * mensaje, la dirección cuatro después, el precio que le cotizaste en
         * el medio.
         *
         * Eso es justo lo que las reglas no pueden hacer y la IA sí.
         *
         * La libreta se respeta cuando la IA no encuentra nada: los datos de
         * la última entrega son reales y ya se usaron. Pero si en ESTA
         * conversación dio una dirección nueva, esa manda — la gente se muda,
         * o manda el paquete a otro lado.
         */
        $leyo = false;

        if (\App\Services\LeerOrdenIA::disponible()) {
            try {
                $ia = \App\Services\LeerOrdenIA::de($conv);

                foreach (['nombre', 'municipio', 'direccion', 'productos', 'total'] as $c) {
                    if (filled($ia[$c] ?? null)) $leyo = true;
                }

                // Si la IA devolvió el nombre del perfil (y el cliente nunca lo
                // escribió en el chat), no cuenta.
                if (filled($ia['nombre']) && ! $this->esNombreDelPerfil($conv, (string) $ia['nombre'])) $nombre = $ia['nombre'];
                $municipio = filled($ia['municipio']) ? $ia['municipio'] : $municipio;
                $direccion = filled($ia['direccion']) ? $ia['direccion'] : $direccion;
                // Con dos decimales, como se escribe la plata. montoDe()
                // devuelve 0 cuando lo leído no es un monto creíble, y ahí el
                // campo queda vacío en vez de salir un "$0" que se manda.
                $m = static::montoDe($ia['total'] ?? '');
                $total = $m > 0 ? number_format($m, 2) : '';

                // Los productos, uno por renglón y con su precio, como los
                // escribís vos. Si la IA no trajo precio para alguno, va sin
                // precio: en blanco se ve y se completa, inventado no.
                $renglones = [];

                foreach (($ia['lineas'] ?? []) as $l) {
                    $r = $l['cantidad'] . ' ' . $l['producto']
                       . ($l['talla'] !== '' ? ' talla ' . $l['talla'] : '');

                    if (($l['precio'] ?? 0) > 0) {
                        $r .= ' $' . number_format($l['precio'], 2);

                        if ($l['cantidad'] > 1) {
                            $r .= ' ($' . number_format($l['precio'] * $l['cantidad'], 2) . ')';
                        }
                    }

                    $renglones[] = $r;
                }

                $productos = $renglones
                    ? "\n" . implode("\n", $renglones)
                    : $ia['productos'];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Plantilla con IA: ' . $e->getMessage());
            }
        }

        /*
         * Lo que el cliente escribió en el chat MANDA sobre todo lo demás.
         *
         * Se lee al instante, sin IA: los mensajes del cliente pasan por el
         * mismo lector de reglas de "Procesar orden", uno por uno, del más
         * nuevo al más viejo. Si escribió "Nombre: …", "Dirección: …",
         * "Municipio: …", eso sale literal — más seguro que la IA, que
         * interpreta, y que la libreta, que es de la entrega anterior.
         *
         * Y si no usó etiquetas, se busca el municipio en lo que escribió: el
         * mensaje donde nombra un municipio casi siempre ES la dirección.
         */
        $delChat = $this->datosDelChat($conv);

        foreach (['nombre', 'municipio', 'direccion'] as $c) {
            if (filled($delChat[$c] ?? null)) {
                $$c = $delChat[$c];
                $leyo = true;
            }
        }

        // El nombre que dio en el chat sin "Nombre:" delante: el que anotó el
        // asistente, o lo que contestó cuando se le pidió el nombre. Manda
        // sobre la IA y la libreta (la libreta es de una entrega anterior).
        if (blank($delChat['nombre'] ?? null) && ($dicho = $this->nombreDichoEnChat($conv)) !== '') {
            $nombre = $dicho;
            $leyo = true;
        }

        $this->texto = "\u{1F4E6} Orden de Env\u{ED}o: \u{1F69A}\n"
            . "\u{2705} Nombre completo: {$nombre}\n"
            . "\u{2705} Tel\u{E9}fono: {$telefono}\n"
            . "\u{2705} Municipio: {$municipio}\n"
            . "\u{2705} Direcci\u{F3}n exacta: {$direccion}\n"
            . "\u{2705} Producto(s): {$productos}\n"
            . "\u{2705} Costo de env\u{ED}o: \${$envio}\n"
            . "\u{1F4B0} Total a pagar: \${$total}\n\n"
            // Sin enlace de rastreo a propósito: ese sale solo al guardar la
            // guía en la cola. Así, mirando el chat, se sabe de un vistazo
            // cuáles órdenes ya se procesaron y cuáles quedaron a medias.
            . "\u{2728} \u{A1}Gracias por tu preferencia! Tu pedido estar\u{E1} en camino muy pronto";

        $this->pestana = 'chat';

        // El cuadro se abre: una orden son ocho renglones y en tres no se lee.
        $this->cajaGrande = true;

        // La IA no contestó a tiempo: se dice, en vez de dejar creer que el
        // chat se leyó entero. Lo que haya salido de las reglas y de la
        // libreta igual quedó puesto.
        if (\App\Services\LeerOrdenIA::$fallo) {
            Notification::make()
                ->title('La IA no contestó a tiempo')
                ->body('Se llenó con lo que el cliente escribió con etiquetas y con la libreta. '
                     . 'Los productos y el total completalos vos.')
                ->warning()->send();
            return;
        }

        if ($leyo) {
            Notification::make()
                ->title('Orden armada leyendo la conversación')
                ->body('Revisala ANTES de mandarla: la dirección y los montos salieron de '
                     . 'entender el chat, no de copiarlo. Lo que quedó en blanco es porque '
                     . 'no se dijo — completalo vos.')
                ->warning()->send();
            return;
        }

        Notification::make()
            ->title($cliente
                ? 'Orden armada con los datos que ya teníamos'
                : 'Orden en blanco')
            ->body('Completá los productos y los montos antes de mandarla.')
            ->success()->send();
    }

    /**
     * Nombre, dirección y municipio sacados de lo que escribió el cliente.
     *
     * Sin IA, en el acto. Dos pasadas:
     *
     *  1. Con etiquetas: cada mensaje del cliente pasa por el lector de
     *     órdenes. Del más nuevo al más viejo, y el primero que trae un dato
     *     gana — si corrigió la dirección, vale la corrección.
     *  2. Sin etiquetas: si todavía falta el municipio, se busca un nombre de
     *     municipio en sus mensajes. El mensaje donde aparece, si es largo,
     *     se toma como dirección: "col. Las Brisas pasaje 3 casa 12
     *     Soyapango" es eso.
     */
    private function datosDelChat(WaConversacion $conv): array
    {
        $salida = ['nombre' => '', 'direccion' => '', 'municipio' => ''];

        try {
            $mensajes = WaMensaje::where('conversacion_id', $conv->id)
                ->where('direccion', 'entrante')
                ->whereNotNull('texto')
                ->orderByDesc('id')
                ->limit(20)
                ->pluck('texto');
        } catch (\Throwable $e) {
            return $salida;
        }

        foreach ($mensajes as $texto) {
            $d = $this->leerOrden((string) $texto);

            foreach (array_keys($salida) as $c) {
                if ($salida[$c] === '' && filled($d[$c] ?? null)) {
                    $salida[$c] = trim((string) $d[$c]);
                }
            }
        }

        if ($salida['municipio'] === '') {
            foreach ($mensajes as $texto) {
                $mun = \App\Services\Municipios::buscarEn((string) $texto);
                if (! $mun) continue;

                $salida['municipio'] = $mun;

                if ($salida['direccion'] === '' && mb_strlen(trim((string) $texto)) >= 15) {
                    $salida['direccion'] = trim((string) $texto);
                }
                break;
            }
        }

        // El nombre no puede quedar con el teléfono pegado, como a veces lo
        // escriben: "Maria Lopez 7123 4567".
        if ($salida['nombre'] !== '') $salida['nombre'] = $this->limpiarNombre($salida['nombre']);

        return $salida;
    }

    // ── Procesar una orden de envío escrita en el chat ───────────────────────

    /**
     * Toma el mensaje de la orden y llena el formulario con lo que dice.
     *
     * El texto original queda pegado arriba del formulario a propósito: la
     * gracia es poder comparar renglón por renglón sin cambiar de ventana, que
     * es justo lo que no se podía hacer antes.
     */
    public function procesarOrden(int $mensajeId, bool $conProductos = false): void
    {
        $m = WaMensaje::find($mensajeId);
        if (! $m || blank($m->texto)) return;

        $this->pedOrigen = $m->texto;
        $datos = $this->leerOrden($m->texto);

        /*
         * El BOTÓN no trae producto ni total (Wil). Si el cliente cambió de
         * talla, procesar una orden vieja metía la talla equivocada en la guía.
         * El producto y el total se escriben a mano, preguntándole al cliente.
         * Solo el lote y el pase automático (que exigen la orden más nueva,
         * revisada y confirmada) los leen de la orden: ahí $conProductos = true.
         */
        if (! $conProductos) {
            $datos['productos'] = '';
            $datos['total'] = '';
            $datos['lineas'] = [];
            $this->pedProductosTexto = '';
            $this->pedCobrarManual = '';
            $this->pedLineas = [];
        }

        // Procesar solo llena el formulario: no mueve de pestaña. El paso a
        // Preparados es al GUARDAR en la cola (ver guardarPedido), porque
        // recién ahí la guía existe y va a salir en el Excel. Abrir una orden
        // para mirarla y descartarla no tiene que mover nada.

        /*
         * ACÁ NO ENTRA LA IA. Y no es por miedo: es que sobra.
         *
         * Este botón solo aparece sobre un mensaje que YA es una orden de
         * envío escrita con la plantilla. Ahí el lector de reglas es lo
         * correcto: el texto tiene las etiquetas, lee literal, es gratis, es
         * instantáneo y no puede alucinar.
         *
         * Lo tuve llamando a la IA cuando quedaba algún campo vacío, y estuvo
         * mal. Sobre una orden ya escrita, la IA no tiene nada que aportar y
         * sí mucho que romper: en la primera prueba puso un total de tres mil
         * millones. Meter una lectura que interpreta encima de una que solo
         * transcribe es cambiar certeza por conveniencia.
         *
         * La IA sigue existiendo (LeerOrdenIA) para el caso que sí la
         * necesita: una conversación suelta, sin orden escrita. Ese es otro
         * camino y va aparte.
         */
        $conIA = false;

        // El nombre de la orden; si no lo trae, queda el de su pedido anterior,
        // sin el "AIWIBI" (ver cargarDatosDelCliente).
        if (filled($datos['nombre'])) $this->pedNombre = $this->limpiarNombre($datos['nombre']);
        else $this->pedNombre = static::sinAiwibi($this->pedNombre);
        $this->pedTelefonoRecibe = '';

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

        /*
         * MANDA LO QUE SE HABLÓ EN EL CHAT. El catálogo revisa.
         *
         * Lo tenía al revés y estaba mal. El precio que le dijiste al cliente
         * es un compromiso con él: si le cotizaste $17 y el admin tiene $18,
         * cobrarle $18 al entregar es un problema con el cliente. Que el
         * catálogo esté desactualizado es asunto del negocio, no suyo.
         *
         * Entonces: si en la conversación se cerró un monto, ese va a la guía.
         *
         * Pero las líneas se cargan igual, aunque no decidan el total. Sirven
         * para dos cosas: arman la descripción con los nombres bien escritos,
         * y ponen el precio del catálogo al lado del hablado. Si no coinciden,
         * se avisa — ahí es donde aparece el precio viejo cargado en el admin,
         * o la cotización que salió de menos.
         *
         * Lo que no se reconoce no se borra: queda escrito en el texto de
         * abajo, sin precio y a la vista. Desaparecer de la suma sería peor
         * que aparecer sin precio.
         */
        $reconocido = ['items' => [], 'dudosos' => []];

        if (filled($datos['productos'] ?? null)) {
            $reconocido = \App\Services\ReconocerProductos::enTexto($datos['productos']);

            if ($reconocido['items']) {
                // Los precios que la IA sacó del chat, indexados por producto
                // y talla para poder pegarlos al renglón que corresponde.
                $precios = [];

                foreach (($datos['lineas'] ?? []) as $l) {
                    if (($l['precio'] ?? 0) <= 0) continue;

                    $clave = \App\Services\ReconocerProductos::normalizar(
                        ($l['producto'] ?? '') . ' ' . ($l['talla'] ?? '')
                    );

                    if ($clave !== '') $precios[$clave] = (float) $l['precio'];
                }

                $this->pedLineas = array_map(function ($i) use ($precios) {
                    $clave = \App\Services\ReconocerProductos::normalizar(
                        $i['producto'] . ' ' . $i['talla']
                    );

                    return [
                        'size_id'  => (string) $i['size_id'],
                        'cantidad' => (int) $i['cantidad'],
                        // Vacío si en el chat no se habló de precio para este:
                        // ahí manda el del catálogo. Poner el del catálogo acá
                        // sería mentir sobre de dónde salió el número.
                        'precio'   => isset($precios[$clave])
                            ? number_format($precios[$clave], 2, '.', '')
                            : '',
                    ];
                }, $reconocido['items']);

                // El total hablado se queda puesto y manda sobre el cálculo.
                // Solo cuando NO se habló ninguno toma el mando el catálogo:
                // ahí no hay compromiso con nadie y el precio cargado es lo
                // mejor que tenemos.
            }
        }

        $this->pestana = 'pedido';

        $leidos = count(array_filter($datos, fn ($v) => filled($v)));

        /*
         * El monto que se habló contra el que sale del catálogo.
         *
         * Va el del chat, que es el que el cliente espera pagar. Pero la
         * diferencia se avisa igual, porque significa algo y nunca es buena:
         * si el catálogo dice más, cotizaste de menos y esa plata la ponés
         * vos; si dice menos, hay un precio viejo cargado en el admin y el
         * próximo que cotice mirando ahí se va a equivocar.
         *
         * Cobrarle lo del chat resuelve el pedido de hoy. El aviso es para que
         * mañana no se repita.
         */
        $dicho = static::montoDe($datos['total'] ?? '');

        if ($dicho > 0 && $reconocido['items']) {
            // El envío que dice la orden ($2, $2.50, $2.90 según dónde vive);
            // si no lo dice, el de siempre.
            $envioOrden = static::montoDe($datos['envio'] ?? '');

            $porCatalogo = round(
                (float) ($reconocido['total'] ?? 0) + ($envioOrden > 0 ? $envioOrden : $this->envioPedido()),
                2
            );

            // Medio dólar de tolerancia: menos que eso es redondeo, no un
            // precio distinto, y un aviso que salta por nada se deja de leer.
            if ($porCatalogo > 0 && abs($porCatalogo - $dicho) >= 0.5) {
                $mas = $porCatalogo > $dicho;

                Notification::make()
                    ->title('Se cobra lo del chat: $' . number_format($dicho, 2))
                    ->body('El admin daría $' . number_format($porCatalogo, 2) . '. '
                         . ($mas ? 'Cotizaste de menos.' : 'Revisá el precio en el admin.'))
                    ->warning()->duration(8000)->send();
            }
        }

        // Lo que se nombró pero no está en el catálogo. Queda escrito abajo
        // sin precio; avisar es lo que impide que se cuele en la suma como si
        // valiera cero.
        if (! empty($reconocido['dudosos'])) {
            Notification::make()
                ->title('Hay algo que no reconocí del catálogo')
                ->body('Quedó sin precio: ' . implode(' · ', array_slice($reconocido['dudosos'], 0, 4))
                     . '. Agregalo a mano abajo o corregí el nombre.')
                ->warning()->duration(8000)->send();
        }

        // Qué se vació porque la orden no lo traía, y qué había antes ahí.
        // Decirlo importa: el campo que quedó en blanco es justo el que hay
        // que mirar, y si antes tenía algo, es dato de OTRO pedido.
        // Wil: si la orden NO trae la dirección o el municipio, vale usar los
        // del pedido anterior de este cliente (es cliente que vuelve). Se usa
        // solo cuando el renglón no venía; si venía, manda lo escrito.
        $perdidos = [];

        if (trim($this->pedDireccion) === '' && trim((string) $antes['direccion']) !== '' && ! filled($datos['direccion'] ?? null)) {
            $this->pedDireccion = $antes['direccion'];
            $perdidos[] = 'la dirección';
        }
        if (trim($this->pedMunicipio) === '' && trim((string) $antes['municipio']) !== '' && ! filled($datos['municipio'] ?? null)) {
            $this->pedMunicipio    = $antes['municipio'];
            $this->pedDepartamento = $antes['departamento'];
            $perdidos[] = 'el municipio';
        }

        if ($perdidos) {
            Notification::make()
                ->title('Ojo: ' . implode(' y ', $perdidos) . ' no venía en la orden')
                ->body('Se usó ' . (count($perdidos) > 1 ? 'lo' : 'el dato') . ' de su pedido anterior. '
                     . 'Revisá que siga siendo el mismo antes de guardar.')
                ->warning()->duration(8000)->send();
            return;
        }

        // Que la IA haya participado se avisa, y no es un detalle: lo que sacó
        // el lector de reglas salió del texto literal, y lo que sacó la IA
        // salió de entender la conversación. Lo segundo se revisa con más
        // cuidado, y para eso hay que saber cuándo pasó.
        if ($conIA) {
            Notification::make()
                ->title("Se completó con IA leyendo el chat ({$leidos} datos)")
                ->body('Los campos que la plantilla no traía se sacaron de la conversación. '
                     . 'Revisá con cuidado la dirección y el municipio contra lo que dijo el cliente.')
                ->warning()->send();
            return;
        }

        Notification::make()
            ->title($leidos > 0 ? "Se leyeron {$leidos} datos de la orden" : 'No se pudo leer la orden')
            ->body($leidos > 0
                ? ($conProductos
                    ? 'Revisalos contra el texto original, que quedó arriba del formulario.'
                    : 'Nombre, teléfono y dirección listos. El producto y el total escribilos a mano, confirmando con el cliente.')
                : 'Revisá que el mensaje tenga el formato de siempre, con dos puntos después de cada campo.')
            ->{$leidos > 0 ? 'success' : 'warning'}()
            ->duration(7000)
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

        // Último recurso: la dirección, SOLO si la orden no traía el renglón
        // "Municipio:". Si lo traía, manda lo escrito: sacarlo de la dirección
        // ponía otro municipio (una colonia o un cantón con nombre parecido).
        if (! $encontrado && trim($municipioCrudo) === '' && trim($direccion) !== '') {
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
    /**
     * ¿Al lector de reglas le faltó algo de lo que impide guardar?
     *
     * Solo estos tres. Los productos y el monto se escriben abajo con el
     * catálogo, que es más confiable que cualquier lectura — ahí los precios
     * salen de la base. Y el teléfono ya lo da la conversación.
     *
     * Acotarlo importa: si se llamara a la IA por cualquier campo vacío, se
     * llamaría casi siempre, y la gracia era justamente no llamarla cuando la
     * plantilla vino bien.
     */
    private static function faltanDatos(array $datos): bool
    {
        foreach (['nombre', 'direccion', 'municipio'] as $c) {
            if (blank($datos[$c] ?? null)) return true;
        }

        return false;
    }

    /**
     * El costo de envío de la última orden que se le mandó a este cliente,
     * leído del renglón "Costo de envío:" de esa orden. Vacío si nunca se le
     * mandó una (cliente nuevo): ahí se escribe a mano.
     */
    private function envioAnterior(WaConversacion $conv): string
    {
        try {
            $ordenes = WaMensaje::where('conversacion_id', $conv->id)
                ->where('direccion', 'saliente')
                ->where('texto', 'like', '%osto de env%')
                ->orderByDesc('id')
                ->limit(10)
                ->pluck('texto');

            foreach ($ordenes as $texto) {
                $m = static::montoDe($this->leerOrden((string) $texto)['envio'] ?? '');
                if ($m > 0 && $m < 20) return number_format($m, 2);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Envío anterior: ' . $e->getMessage());
        }

        return '';
    }

    private function leerOrden(string $texto): array
    {
        $campos = [
            'nombre'    => ['nombre completo', 'nombre'],
            'telefono'  => ['telefono', 'teléfono', 'tel'],
            'municipio' => ['municipio'],
            'direccion' => ['direccion exacta', 'dirección exacta', 'direccion', 'dirección'],
            'productos' => ['producto(s)', 'productos', 'producto', 'su pedido'],
            'envio'     => ['costo de envio', 'costo de envío', 'envio', 'envío'],
            'total'     => ['total a pagar', 'total'],
            // No va a la guía; está para que no se pegue a la dirección.
            'entrega'   => ['día de entrega', 'dia de entrega'],
        ];

        $salida = array_fill_keys(array_keys($campos), '');

        // El campo que se está llenando. Mientras no aparezca otra etiqueta,
        // todo lo que venga abajo pertenece a este: así los productos escritos
        // en varios renglones entran completos y no solo el primero.
        $actual = null;

        // La negrita de WhatsApp (*así*) no es parte de los datos.
        $texto = str_replace('*', '', $texto);

        foreach (preg_split('/\r\n|\n|\r/u', $texto) as $linea) {
            $cruda = rtrim($linea);

            // Separadores (━━━━) y renglones sin letras ni números: nada.
            if (! preg_match('/[\p{L}\p{N}]/u', $cruda)) continue;

            // Fuera emojis, palomitas y viñetas del principio.
            $l = preg_replace('/^[^\p{L}\p{N}]+/u', '', trim($cruda));

            // "SU PEDIDO" va solo, sin dos puntos: abre los productos.
            if (preg_match('/^su pedido\s*:?\s*$/iu', $l)) { $actual = 'productos'; continue; }

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
                            } elseif ($clave === 'productos' && trim($valor) !== '') {
                                $salida[$clave] .= "\n" . trim($valor);
                            }

                            break 2;
                        }
                    }
                }
            }

            // Etiqueta sin dos puntos: "Costo de envío$2.50", "Total a pagar $25".
            // Antes ese renglón se pegaba a los productos y la guía salía con
            // "Costo de envío$2.50" en la descripción.
            if (! $esEtiqueta && preg_match('/^(costo de env[ií]o|env[ií]o|total a pagar|producto\(s\)|productos)\s*(\$.*|$)/iu', $l, $sc)) {
                $clave = preg_match('/^total/iu', $sc[1]) ? 'total' : (preg_match('/^producto/iu', $sc[1]) ? 'productos' : 'envio');
                $actual = $clave;
                if ($salida[$clave] === '' && trim($sc[2]) !== '') $salida[$clave] = trim($sc[2], " \t$");
                continue;
            }

            if ($esEtiqueta) continue;

            // Renglón suelto: si veníamos llenando algo, se le suma.
            if ($actual === null) continue;

            $suelto = trim($cruda);
            // La viñeta del formato ("• 1 Cinta…") no va en la guía.
            $suelto = trim((string) preg_replace('/^[•·▪◦\-–—]+\s*/u', '', $suelto));
            // "1 Cinta Jumbo XL — $23": la raya es del formato, no del producto.
            $suelto = trim((string) preg_replace('/\s+[—–]\s+(?=\$)/u', ' ', $suelto));
            if ($suelto === '') continue;

            // El cierre de cortesía no es parte de ningún campo.
            if (preg_match('/gracias por (tu|su) preferencia|estar\p{L}? en camino/iu', $suelto)) {
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

    /** ¿Ese nombre es el del perfil de WhatsApp y el cliente nunca lo escribió? */
    private function esNombreDelPerfil(WaConversacion $conv, string $nombre): bool
    {
        $perfil = \App\Services\Asistente\Entender::normalizar((string) $conv->nombre);
        $n = \App\Services\Asistente\Entender::normalizar($nombre);
        if ($perfil === '' || $n === '' || ! (str_contains($perfil, $n) || str_contains($n, $perfil))) return false;

        try {
            $lo = WaMensaje::where('conversacion_id', $conv->id)->where('direccion', 'entrante')
                ->whereNotNull('texto')->orderByDesc('id')->limit(60)->pluck('texto');
            foreach ($lo as $t) {
                if (str_contains(\App\Services\Asistente\Entender::normalizar((string) $t), $n)) return false;
            }
        } catch (\Throwable $e) {
        }
        return true;
    }

    /**
     * El nombre que el cliente escribió en el chat sin etiqueta: primero el que
     * anotó el asistente; si no, lo que contestó justo después de que se le
     * pidió el nombre ("Wilfredo Herrera", aunque venga con la dirección abajo).
     */
    private function nombreDichoEnChat(WaConversacion $conv): string
    {
        try {
            $f = \App\Models\AsistenteFicha::de($conv);
            if ($f && filled($f->dato('nombre'))) return $this->limpiarNombre((string) $f->dato('nombre'));
        } catch (\Throwable $e) {
        }

        try {
            $msjs = WaMensaje::where('conversacion_id', $conv->id)->whereNotNull('texto')
                ->orderByDesc('id')->limit(40)->get(['id', 'direccion', 'texto'])->sortBy('id')->values();
        } catch (\Throwable $e) {
            return '';
        }

        $noNombre = '/\b(hola|buenas|gracias|si|ok|talla|calzoncito|cinta|pampers?|panales|paquete|colonia|casa|calle|pasaje|avenida|barrio|canton|municipio|departamento|frente|cerca|por favor|quiero|envio)\b/';
        $encontrado = '';
        $pidio = false;
        foreach ($msjs as $m) {
            $t = (string) $m->texto;
            if ($m->direccion === 'saliente') {
                $pidio = (bool) preg_match('/nombre/iu', $t);
                continue;
            }
            if (! $pidio) continue;
            foreach (preg_split('/\n+/', $t) as $linea) {
                $linea = trim(preg_replace('/^(mi nombre es|me llamo|soy|a nombre de|nombre)\s*:?\s*/iu', '', trim($linea)));
                $linea = $this->limpiarNombre($linea);   // sin el teléfono pegado
                $nom = \App\Services\Asistente\Entender::pareceNombre($linea);
                $norm = \App\Services\Asistente\Entender::normalizar((string) $nom);
                // Que no sea solo un municipio ("San Miguel"); un nombre puede
                // llevar uno adentro ("Karla Guadalupe Mejía").
                $mun = \App\Services\Municipios::buscarEn($linea);
                $soloMunicipio = $mun && \App\Services\Asistente\Entender::normalizar($mun) === $norm;
                if ($nom && count(preg_split('/\s+/', $norm)) >= 2 && ! preg_match($noNombre, $norm) && ! $soloMunicipio) {
                    $encontrado = $this->limpiarNombre($nom);
                    break;
                }
            }
        }
        return $encontrado;
    }

    /** "María López AIWIBI" → "María López" (la marca de Wil no sale al cliente ni a la guía). */
    public static function sinAiwibi(string $nombre): string
    {
        $n = preg_replace('/[\(\[\-–—\/]*\s*\b(aiwibi|aiwiby|aiwivi|aiuibi|aywibi)\b\s*[\)\]]*/iu', ' ', $nombre);
        return trim((string) preg_replace('/\s{2,}/u', ' ', (string) $n), " \t.,;:-");
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
    /** "📤 Guardar y enviar a Express": lo mismo que guardar, y la crea en Express de una vez. */
    public function guardarYEnviarPedido(): void
    {
        $this->guardarPedido(true);
    }

    public function guardarPedido(bool $enviarExpress = false): void
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
                ->warning()->duration(8000)->send();
            return;
        }

        // Si quedó vacío y se puede deducir, se completa sin molestar.
        if (trim($this->pedDepartamento) === '' && $zona['sugerido']) {
            $this->pedDepartamento = $zona['sugerido'];
        }

        // Teléfono con un dígito distinto al del WhatsApp: casi siempre es un
        // error al escribirlo. Se avisa una vez; si está bien, se guarda al
        // presionar otra vez.
        $convTel = $this->conversacion();
        if ($convTel && ($dudoso = $this->telefonoDudoso($convTel))) {
            $clave = $convTel->id . '|' . trim($this->pedTelefono) . '|' . trim($this->pedTelefonoRecibe);
            if ($this->telDudosoVisto !== $clave) {
                $this->telDudosoVisto = $clave;
                Notification::make()
                    ->title('¿El teléfono está bien?')
                    ->body($dudoso . ' Si está bien, presioná guardar otra vez.')
                    ->warning()->persistent()->send();
                return;
            }
        }
        $this->telDudosoVisto = null;

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
        $avisoCobro = '';

        // A la libreta de clientes, con el teléfono de la orden Y con el del
        // WhatsApp: así la próxima vez el asistente la reconoce como clienta
        // activa aunque la orden haya ido con el número de otra persona.
        try {
            $tels = array_unique(array_filter([
                \App\Models\Cliente::normalizar($fila['telefono']),
                $conv ? \App\Models\Cliente::normalizar((string) $conv->telefono) : '',
            ], fn ($t) => strlen($t) === 8));
            foreach ($tels as $t) {
                \App\Models\Cliente::recordar([
                    'telefono' => $t, 'nombre' => $fila['nombre'], 'direccion' => $fila['direccion'],
                    'municipio' => $fila['municipio'], 'departamento' => $fila['departamento'],
                ]);
            }
        } catch (\Throwable $e) {
        }

        /*
         * Guardada en la cola → Preparados. Siempre, en este momento.
         *
         * Es el punto exacto en que el pedido queda armado: la guía existe y va
         * a salir en el próximo Excel. Antes el paso dependía de que saliera el
         * enlace de rastreo, y con la ventana de 24 horas cerrada no salía — la
         * conversación se quedaba en Pedidos con la guía ya en la cola.
         */
        if ($conv) {
            try {
                \App\Services\Etiquetado::marcarProcesada($conv);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Marcando preparada al guardar: ' . $e->getMessage());
            }
        }

        /*
         * Guía sin cobro al entregar → a la lista de "Sin cobro".
         *
         * Una guía que sale en cero significa que ese dinero no llega con el
         * paquete. Puede estar bien —el cliente ya pagó por transferencia— o
         * puede ser que quedó pendiente y nadie se acordó. Las dos se ven
         * IGUAL en la guía, y esa es toda la razón de que exista esta lista:
         * hasta que alguien mire, no hay forma de distinguirlas.
         *
         * La etiqueta se pone sola y NO se quita sola. Sacarla es decir "este
         * ya me pagó", y eso solo lo sabés vos. Es lo que la convierte en una
         * lista de cobro en vez de un adorno.
         */
        if ($conv && (float) $fila['cobrar'] <= 0) {
            try {
                $sinCobro = \App\Models\WaEtiqueta::porRol('sin_cobro');

                if ($sinCobro) {
                    $conv->etiquetas()->syncWithoutDetaching([$sinCobro->id]);

                    // Aparte de $aviso: ese lo pisan los avisos del rastreo de
                    // más abajo, y este no se puede perder — es el que dice
                    // que esa plata todavía no entró.
                    $avisoCobro = ' Se marcó como SIN COBRO: la guía sale en cero. '
                                . 'Quitale la etiqueta cuando te pague.';
                } else {
                    // Sin etiqueta asignada esto no se puede marcar, y quedarse
                    // callado sería peor: la guía igual sale sin cobrar.
                    Notification::make()
                        ->title('Esta guía sale SIN cobro')
                        ->body('No hay ninguna etiqueta con el papel "sin_cobro" asignada, '
                             . 'así que no se pudo marcar. Creala en el admin, en Etiquetas, '
                             . 'para llevar el control de quién pagó.')
                        ->warning()->duration(8000)->send();
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Marcando sin cobro: ' . $e->getMessage());
            }
        }

        if ($conv && $conv->ventanaAbierta()) {
            $m = WhatsappApi::enviarTexto(
                $conv,
                \App\Models\GuiaBorrador::mensajeCliente($fila + ['id' => $guia->id]),
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
            /*
             * Fuera de las 24 horas el enlace no puede salir ahora. Lo mandás
             * vos a mano: queda escrito en el cuadro, listo para cuando el
             * cliente vuelva a escribir. (El paso a Preparados ya se hizo
             * arriba, al guardar.)
             */
            $this->texto = \App\Models\GuiaBorrador::mensajeCliente($fila + ['id' => $guia->id]);

            $aviso = ' Ventana cerrada: el rastreo quedó en el cuadro, mandalo cuando escriba.';
        }

        $this->limpiarPedido();
        $this->pestana = 'chat';

        // Directo a Express: la orden se crea ya y vuelve con su número de guía.
        // Si Express la rechaza, la guía igual quedó en la cola (no se pierde).
        if ($enviarExpress && \App\Services\ExpressApi::configurada()) {
            $r = ['ok' => false, 'error' => 'Hay datos por corregir (míralos en Crear guías).', 'guia' => null, 'cobroMal' => null];
            try {
                if (! \App\Services\RevisarGuia::tieneError($guia->aFila())) {
                    $r = \App\Services\ExpressEnvio::enviarUna($guia, \App\Services\ExpressEnvio::loteDelDia());
                }
            } catch (\Throwable $e) {
                $r = ['ok' => false, 'error' => $e->getMessage(), 'guia' => null, 'cobroMal' => null];
            }

            if ($r['ok']) {
                Notification::make()
                    ->title('📤 Guía ' . $r['guia'] . ' creada en Express')
                    ->body(($r['cobroMal'] ? '⚠️ ' . $r['cobroMal'] . ' ' : '')
                        . 'Ya tiene seguimiento.' . $avisoCobro . $aviso . ' Imprima la etiqueta en Express.')
                    ->{$r['cobroMal'] || $avisoCobro || $aviso ? 'warning' : 'success'}()
                    ->duration(9000)->send();
            } else {
                Notification::make()
                    ->title('Se guardó en la cola, pero Express no la aceptó')
                    ->body($r['error'] . ' Corrígela en Crear guías y envíala desde ahí.' . $avisoCobro . $aviso)
                    ->danger()->persistent()->send();
            }

            return;
        }

        $cola = $this->enCola();

        // Corto: es una confirmación, se lee de pasada. Cómo bajar el Excel ya
        // lo sabés; repetirlo en cada guía solo alargaba la tarjeta.
        $cuerpo = "Ya hay {$cola} " . ($cola == 1 ? 'guía esperando.' : 'guías esperando.');

        $n = Notification::make()->title('Guardado en la cola');

        if ($aviso === '' && $avisoCobro === '') {
            $n->body($cuerpo . ' El rastreo ya le salió.')->success();
        } else {
            // El de sin cobro va primero: es plata, y lo del rastreo se puede
            // resolver después.
            $n->body($cuerpo . $avisoCobro . $aviso)->warning()->duration(8000);
        }

        $n->send();
    }

    /** El teléfono dudoso que ya se avisó (al segundo guardar, pasa). */
    public ?string $telDudosoVisto = null;

    /**
     * ¿El teléfono de la orden es el del WhatsApp con un dígito mal escrito?
     * (6143 8058 en vez de 6143 8057, o dos dígitos cambiados de lugar.)
     * Un número totalmente distinto es de otra persona y está bien.
     */
    private function telefonoDudoso(WaConversacion $conv): ?string
    {
        $wa = \App\Models\Cliente::normalizar((string) $conv->telefono);
        if (strlen($wa) !== 8) return null;

        foreach (['teléfono' => $this->pedTelefono, 'teléfono de quien recibe' => $this->pedTelefonoRecibe] as $campo => $valor) {
            preg_match_all('/(?<!\d)(?:\+?503[\s.-]*)?([267]\d{3})[\s.-]*(\d{4})(?!\d)/', (string) $valor, $mm, PREG_SET_ORDER);
            foreach ($mm as $m) {
                $t = $m[1] . $m[2];
                if ($t === $wa) continue;
                $dif = [];
                for ($i = 0; $i < 8; $i++) if ($t[$i] !== $wa[$i]) $dif[] = $i;
                $unDigito = count($dif) === 1;
                $cambiados = count($dif) === 2 && $dif[1] === $dif[0] + 1
                    && $t[$dif[0]] === $wa[$dif[1]] && $t[$dif[1]] === $wa[$dif[0]];
                if ($unDigito || $cambiados) {
                    $a = substr($t, 0, 4) . ' ' . substr($t, 4);
                    $b = substr($wa, 0, 4) . ' ' . substr($wa, 4);
                    return "El {$campo} de la orden es {$a} y el WhatsApp es {$b}: parece un dígito mal escrito.";
                }
            }
        }

        return null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // 📤 Mandar a Express todos los de una pestaña (la de "OK.")
    // ════════════════════════════════════════════════════════════════════════
    //
    // Los chats de esa pestaña ya los leíste y les pasaste el filtro. Antes
    // había que entrar uno por uno, "Procesar esta orden" y guardar. Acá se
    // hace lo mismo con todos, en dos pasos:
    //
    //   1. prepararLote(): arma cada orden igual que el botón de procesar
    //      (sin IA, leyendo la plantilla) y separa lo que sale de lo que hay
    //      que mirar. No manda nada.
    //   2. enviarDelLote(): el navegador lo llama de a uno, así 30 guías no
    //      chocan con el tiempo máximo de una petición, y cada renglón se va
    //      poniendo ✅ o ❌ a la vista.
    //
    // Reglas (las tuyas):
    //   · teléfono vacío → el del WhatsApp que escribe; nombre vacío → "Cliente".
    //   · SIN cobro → no sale solo: alarma, y un botón para mandarla igual.
    //   · municipio que no cuadra, producto que no está en el catálogo, orden
    //     que no se encuentra, guía ya hecha, San Miguel o cliente que dijo que
    //     está mal → a revisar, con el motivo y el botón de abrir el chat.

    /** El lote abierto: ['etiqueta' => id, 'nombre' => …, 'listos' => [...], 'revisar' => [...]]. */
    public ?array $lote = null;

    /** Los avisos que se juntaron desde $desde, y se sacan de la pantalla. */
    private function notifsTomar(int $desde): array
    {
        $todas  = (array) session('filament.notifications', []);
        $nuevas = array_slice($todas, $desde);
        session()->put('filament.notifications', array_slice($todas, 0, $desde));

        return array_map(fn ($n) => trim((string) ($n['title'] ?? '')) . ' ' . trim((string) ($n['body'] ?? '')), $nuevas);
    }

    /** La última orden de envío escrita en el chat (la corregida, si hubo dos). */
    private function ultimaOrdenDe(WaConversacion $conv): ?WaMensaje
    {
        return WaMensaje::where('conversacion_id', $conv->id)
            ->orderByDesc('id')->limit(80)->get()
            ->first(fn ($m) => \App\Services\Etiquetado::pareceOrden($m->texto)
                && preg_match('/producto|su pedido/iu', (string) $m->texto));
    }

    /**
     * Arma el pedido de un chat en el formulario, como "Procesar esta orden",
     * y dice si sale solo o qué hay que mirar. Deja el formulario lleno.
     */
    private function evaluarParaLote(WaConversacion $conv): array
    {
        $r = ['id' => $conv->id, 'tel' => $conv->titulo(), 'ok' => false, 'sinCobro' => false,
              'motivo' => '', 'municipio' => '', 'producto' => '', 'cobro' => 0.0];

        /*
         * Revendedores: mandan varias órdenes seguidas desde su número, una
         * por cada cliente suyo. Acá solo se toma la última, así que el resto
         * se perdería: esas se hacen a mano. Dos formas de reconocerlos:
         *   · el número está en REVENDEDORES (Railway), separado por comas;
         *   · el propio chat mandó 2 o más órdenes escritas en la última semana.
         */
        $tel8 = substr(preg_replace('/\D/', '', (string) $conv->telefono), -8);
        $revendedores = array_filter(array_map(
            fn ($n) => substr(preg_replace('/\D/', '', $n), -8),
            explode(',', (string) env('REVENDEDORES', '63006371'))
        ));
        if ($tel8 !== '' && in_array($tel8, $revendedores, true)) {
            return ['motivo' => 'Es revendedor (varias órdenes en el mismo chat): hacelas a mano'] + $r;
        }
        $suyas = WaMensaje::where('conversacion_id', $conv->id)->where('direccion', 'entrante')
            ->where('created_at', '>=', now()->subDays(7))->orderByDesc('id')->limit(200)->pluck('texto')
            ->filter(fn ($t) => \App\Services\Etiquetado::pareceOrden($t) && preg_match('/producto|su pedido/iu', (string) $t))
            ->count();
        if ($suyas >= 2) {
            return ['motivo' => "Mandó {$suyas} órdenes en la semana (¿revendedor?): hacelas a mano"] + $r;
        }

        $orden = $this->ultimaOrdenDe($conv);
        if (! $orden) return ['motivo' => 'No encontré la orden de envío en el chat'] + $r;

        // Ya se hizo la guía después de esta orden: no se manda dos veces.
        try {
            if ($tel8 !== '' && \App\Models\GuiaBorrador::where('created_at', '>=', $orden->created_at)
                    ->where('telefono', 'like', '%' . $tel8)->exists()) {
                return ['motivo' => 'Ya tiene guía hecha después de esta orden'] + $r;
            }
        } catch (\Throwable $e) {}

        if ($conv->etiquetas->contains(fn ($e) => mb_stripos((string) $e->nombre, 'san miguel') !== false)) {
            return ['motivo' => 'Es de San Miguel: la entregás vos'] + $r;
        }

        $dijoMal = WaMensaje::where('conversacion_id', $conv->id)->where('id', '>', $orden->id)
            ->where('direccion', 'entrante')->pluck('texto')
            ->contains(fn ($t) => preg_match('/no est[aá] (correct|bien)|est[aá] mal|incorrect|equivoc/iu', (string) $t));
        if ($dijoMal) return ['motivo' => 'El cliente dijo que la orden no está bien'] + $r;

        if ($sinPres = static::productosSinPresentacion((string) $orden->texto)) {
            return ['motivo' => 'Producto sin especificar: «' . \Illuminate\Support\Str::limit((string) array_key_first($sinPres), 50) . '»'] + $r;
        }

        // Igual que tocar "Procesar esta orden" en ese chat.
        $this->abierta = $conv->id;
        $this->limpiarPedido();
        $n0 = count((array) session('filament.notifications', []));
        $this->procesarOrden($orden->id, true);
        $avisos = $this->notifsTomar($n0);

        if (trim($this->pedNombre) === '') $this->pedNombre = 'Cliente';
        if (trim($this->pedTelefono) === '') {
            $this->pedTelefono = $conv->esExtranjero()
                ? WaConversacion::internacional((string) $conv->wa_id)
                : (string) ($conv->telefono ?: '');
        }

        $r['municipio'] = trim($this->pedMunicipio);
        $r['producto']  = $this->descripcionPedido();
        $r['cobro']     = round($this->totalPedido(), 2);
        $r['orden']     = $orden->id;

        $faltan = [];
        if (trim($this->pedTelefono) === '')  $faltan[] = 'el teléfono';
        if (trim($this->pedDireccion) === '') $faltan[] = 'la dirección';
        if (trim($this->pedMunicipio) === '') $faltan[] = 'el municipio';
        if ($r['producto'] === '')            $faltan[] = 'el producto';
        if ($faltan) return ['motivo' => 'Falta ' . implode(', ', $faltan)] + $r;

        if ($dudoso = $this->telefonoDudoso($conv)) return ['motivo' => $dudoso] + $r;

        $zona = $this->revisionZona();
        if (in_array($zona['estado'], ['error', 'ambiguo', 'desconocido'], true)) {
            return ['motivo' => 'Municipio: ' . strip_tags((string) $zona['mensaje'])] + $r;
        }

        /*
         * EN EL LOTE NO SE ADIVINA NADA.
         *
         * A mano, el formulario ayuda: si el municipio no venía claro lo busca
         * en la dirección o en el texto, y vos lo ves antes de guardar. En el
         * lote no lo ve nadie, y así pasó con "cara sucia": no es municipio
         * (es de San Francisco Menéndez) y salía con otro sacado del texto.
         *
         * Acá solo sale si la orden trae ESCRITOS:
         *   · el municipio, tal cual uno de la lista (antes de la coma);
         *   · el total a pagar, con un monto (o que diga que ya está pagado).
         * Si no, a revisar, y lo hacés vos mirando el chat.
         */
        $leida = $this->leerOrden((string) $orden->texto);
        $M = \App\Services\Municipios::class;
        $muniEscrito = trim(explode(',', (string) ($leida['municipio'] ?? ''))[0]);

        if ($muniEscrito === '') {
            return ['motivo' => 'La orden no trae el municipio escrito (no lo adivino de la dirección)'] + $r;
        }
        if (! $M::existe($muniEscrito)) {
            return ['motivo' => "Municipio «{$muniEscrito}» no es un municipio de la lista (¿es un cantón o colonia?)"] + $r;
        }
        if ($M::normalizar($M::nombreBueno($muniEscrito)) !== $M::normalizar($this->pedMunicipio)
            || trim($this->pedDepartamento) === '') {
            return ['motivo' => "Municipio «{$muniEscrito}»: no quedó claro el departamento"] + $r;
        }

        $totalEscrito = static::montoDe((string) ($leida['total'] ?? ''));
        $diceSinCobro = (bool) preg_match('/pagad|cancelad|transferencia|ya pag/iu', (string) $orden->texto);

        if ($totalEscrito <= 0 && ! $diceSinCobro) {
            return ['motivo' => 'La orden no trae el total a pagar escrito (no lo calculo del catálogo)'] + $r;
        }

        // Que un producto no esté en el catálogo (o esté agotado) NO frena la
        // guía: la descripción va igual con lo que dice la orden, y el cobro
        // sale del total escrito. Si por eso el cobro quedara en cero, lo
        // agarra la alarma de "sin cobro" de abajo.

        if ($r['cobro'] <= 0) {
            return ['motivo' => 'Sale SIN cobro: ¿ya pagó?', 'sinCobro' => true] + $r;
        }

        return ['ok' => true] + $r;
    }

    /** Paso 1: revisa todos los chats de la pestaña puesta. No manda nada. */
    public function prepararLote(): void
    {
        if (! $this->filtroEtiqueta) {
            Notification::make()->title('Primero abrí la pestaña (por ejemplo OK.)')->warning()->send();
            return;
        }

        $antes = ['abierta' => $this->abierta, 'pestana' => $this->pestana, 'texto' => $this->texto];

        $listos = [];
        $revisar = [];

        try {
            $convs = $this->consultaConversaciones(false)->with('etiquetas')
                ->orderBy('ultimo_mensaje_at')->limit(80)->get();

            foreach ($convs as $c) {
                $e = $this->evaluarParaLote($c);
                if ($e['ok']) $listos[] = $e; else $revisar[] = $e;
            }
        } catch (\Throwable $e) {
            Notification::make()->title('No se pudo revisar la pestaña')->body($e->getMessage())->danger()->send();
        }

        $this->abierta = $antes['abierta'];
        $this->limpiarPedido();
        $this->pestana = $antes['pestana'];
        $this->texto   = $antes['texto'];

        $etq = \App\Models\WaEtiqueta::find($this->filtroEtiqueta);
        $this->lote = [
            'etiqueta' => $this->filtroEtiqueta,
            'nombre'   => $etq?->nombre ?? 'esta pestaña',
            'listos'   => $listos,
            'revisar'  => $revisar,
        ];
    }

    /** Paso 2: manda UNA guía del lote. Lo llama el navegador, de a una. */
    public function enviarDelLote(int $convId, bool $aunSinCobro = false): array
    {
        $antes = ['abierta' => $this->abierta, 'pestana' => $this->pestana, 'texto' => $this->texto];

        $volver = function () use ($antes) {
            $this->abierta = $antes['abierta'];
            $this->limpiarPedido();
            $this->pestana = $antes['pestana'];
            $this->texto   = $antes['texto'];
        };

        $conv = WaConversacion::with('etiquetas')->find($convId);
        if (! $conv) { $volver(); return ['ok' => false, 'error' => 'No encontré el chat']; }

        $ev = $this->evaluarParaLote($conv);
        if (! $ev['ok'] && ! ($aunSinCobro && $ev['sinCobro'])) {
            $volver();
            return ['ok' => false, 'error' => $ev['motivo'] ?: 'No se pudo armar'];
        }

        /*
         * A LA COLA, no a Express. Express reinicia sus contadores pasada la
         * medianoche: si se mandan de noche, a la mañana ya no se imprimen
         * todas juntas. Así quedan en Crear guías y a la mañana salen todas de
         * una con "📤 Enviar N a Express".
         */
        $this->texto = '';
        $n0 = count((array) session('filament.notifications', []));
        $this->guardarPedido(false);
        $avisos = $this->notifsTomar($n0);
        $ventanaCerrada = trim($this->texto) !== '';   // el rastreo quedó en el cuadro

        $todo = implode(' | ', $avisos);
        $ok = str_contains($todo, 'Guardado en la cola');
        $g = [1 => ''];

        if ($ok && ! empty($this->lote['etiqueta'])) {
            try { $conv->etiquetas()->detach($this->lote['etiqueta']); } catch (\Throwable $e) {}
        }

        $volver();

        return $ok
            ? ['ok' => true, 'guia' => $g[1], 'nota' => $ventanaCerrada ? 'ventana cerrada: el rastreo no le salió' : '']
            : ['ok' => false, 'error' => trim(preg_replace('/\s+/u', ' ', $avisos[0] ?? 'Express no la aceptó'))];
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⏱️ A la cola sola: orden escrita por vos + "sí" limpio del cliente
    // ════════════════════════════════════════════════════════════════════════
    //
    // Vos escribís la orden de envío. Si el cliente contesta que está bien
    // ("sí", "perfecto", "está correcta", o un 👍 a la orden) y pasan 10
    // minutos sin que escriba otra cosa, la guía entra a la cola sola, con
    // las mismas reglas estrictas del lote, más:
    //   · el envío escrito es el de siempre ($2.50) o el de su orden anterior
    //     en este chat — envío gratis o $0 nunca sale solo;
    //   · la suma de productos + envío da el total escrito;
    //   · después de la orden, el cliente solo dijo que sí (o gracias): nada
    //     de "pero", "mejor", otra cantidad, audios ni fotos;
    //   · vos no escribiste nada más (salvo la pregunta de revisar la orden).
    // A Express NO se manda nada: queda en la cola como siempre.
    //
    // Red de seguridad: si el cliente escribe algo que no sea un gracias
    // después de que pasó a la cola, el chat vuelve a Pedidos con 🤖 Revisar
    // y te avisa, para que mires la guía antes de mandarla.
    //
    // Corre mientras el panel está abierto (con el pulso de cada 3 segundos,
    // una vez por minuto). Se apaga con AUTO_COLA=false en Railway.

    private const AUTO_COLA_MIN = 10;

    /** "Sí", "perfecto", "está correcta gracias": confirma y nada más. */
    private static function siLimpio(string $t): ?string
    {
        $n = \App\Services\Asistente\Entender::normalizar($t);
        $n = trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $n));
        if ($n === '') return preg_match('/👍|✅|👌/u', $t) ? 'si' : 'cortesia';   // solo emojis

        $si = '(si+|sip|ok+|okay|okey|oki|va|vaya|dale|listo|lista|perfecto|perfecta|correcto|correcta|excelente|exacto|claro|de acuerdo|todo bien|esta bien|esta perfecta|esta perfecto|esta correcta|esta correcto|asi esta bien|todo esta bien|todo correcto|todo perfecto|bien|muy bien|asi es|genial|super)';
        $relleno = '(gr+a+[cs]+i+a+s*|informacion|seria|eso|feliz|tarde|noche|dia|buen|muchas|mil|muy|amable|bendiciones|dios|le|la|lo|los|bendiga|porfa|por|favor|hola|buenas|buenos|dias|tardes|noches|ya|entonces|quedo|quedamos|pendiente|atenta|atento|igualmente|usted|ud|todo|y|esta|asi|es|a|la orden|con gusto|espero|esperamos)';

        $sinSi = trim(preg_replace('/\b' . $si . '\b/u', ' ', $n));
        $resto = trim(preg_replace('/\s+/u', ' ', preg_replace('/\b' . $relleno . '\b/u', ' ', $sinSi)));
        if ($resto !== '') return null;

        return $sinSi === $n ? 'cortesia' : 'si';
    }

    /** Suma de los productos de la orden (con el "($34)" si lo trae). */
    private static function sumaProductos(string $productos): ?array
    {
        $simple = 0.0; $porCant = 0.0; $alguna = false;
        foreach (preg_split('/\r\n|\n|\r/u', $productos) as $l) {
            $l = trim($l);
            if ($l === '' || ! preg_match('/\d/', $l)) continue;
            if (preg_match_all('/\(\s*\$?\s*(\d+(?:[.,]\d{1,2})?)\s*\)/u', $l, $p) && $p[1]) {
                $v = (float) str_replace(',', '.', end($p[1]));
                $simple += $v; $porCant += $v; $alguna = true;
                continue;
            }
            if (! preg_match_all('/\$\s*(\d+(?:[.,]\d{1,2})?)/u', $l, $m) || ! $m[1]) return null;   // renglón sin precio
            if (count($m[1]) > 1) return null;   // dos precios en un renglón: lo mirás vos
            $v = (float) str_replace(',', '.', $m[1][0]);
            $cant = preg_match('/^\D{0,3}(\d{1,2})\b/u', $l, $c) ? max(1, (int) $c[1]) : 1;
            $simple += $v; $porCant += $v * $cant; $alguna = true;
        }
        return $alguna ? [$simple, $porCant] : null;
    }

    /** Revisa los chats con orden reciente y pasa a la cola los confirmados. */
    private function autoCola(): bool
    {
        if (! filter_var(env('AUTO_COLA', true), FILTER_VALIDATE_BOOLEAN)) return false;
        if ($this->lote) return false;                            // el lote está abierto
        if (! \Illuminate\Support\Facades\Cache::add('wa:autocola:tick', 1, 60)) return false;

        $hizo = $this->autoColaRed();

        $ids = WaMensaje::where('direccion', 'saliente')->where('automatico', false)
            // Solo órdenes de las últimas 6 horas: una vieja que dejaste
            // quieta (para el lunes, por ejemplo) no se manda sola.
            ->where('created_at', '>=', now()->subHours(6))
            ->where('texto', 'like', '%rden de env%')
            ->distinct()->limit(40)->pluck('conversacion_id');

        foreach ($ids as $id) {
            $conv = WaConversacion::with('etiquetas')->find($id);
            if (! $conv) continue;

            $ultimo = (int) WaMensaje::where('conversacion_id', $conv->id)->max('id');
            $clave = 'wa:autocola:visto:' . $conv->id;
            if ((int) \Illuminate\Support\Facades\Cache::get($clave) === $ultimo) continue;   // ya se miró así

            $res = $this->autoColaUna($conv);
            if ($res === 'esperar') continue;                       // todavía no pasan los 10 min
            \Illuminate\Support\Facades\Cache::put($clave, $ultimo, now()->addDays(2));
            if ($res === 'ok') $hizo = true;
        }

        return $hizo;
    }

    /** 'ok' (pasó a la cola), 'esperar' (faltan minutos) o 'no' (lo hacés vos). */
    private function autoColaUna(WaConversacion $conv): string
    {
        $orden = $this->ultimaOrdenDe($conv);
        if (! $orden || $orden->direccion !== 'saliente' || $orden->automatico) return 'no';

        $despues = WaMensaje::where('conversacion_id', $conv->id)->where('id', '>', $orden->id)
            ->orderBy('id')->get(['id', 'direccion', 'tipo', 'texto', 'automatico', 'created_at']);

        // Vos: nada más que la pregunta de revisar (o un "ok" corto).
        foreach ($despues->where('direccion', 'saliente') as $m) {
            $t = trim((string) $m->texto);
            if ($t === self::PREGUNTA_REVISAR) continue;
            if (mb_strlen($t) <= 15 && self::siLimpio($t) !== null) continue;
            return 'no';
        }

        // El cliente: un sí claro, y nada más que cortesías.
        $confirmo = in_array(trim((string) $orden->reaccion), ['👍', '👍🏻', '👍🏼', '👍🏽', '👍🏾', '👍🏿', '✅', '👌', '👌🏻', '👌🏽', '❤️', '🙏', '🙏🏻'], true);
        $ultimaCliente = $confirmo ? $orden->updated_at : null;
        foreach ($despues->where('direccion', 'entrante') as $m) {
            if ($m->tipo !== 'text') return 'no';                   // audio, foto, ubicación: lo mirás vos
            $k = self::siLimpio((string) $m->texto);
            if ($k === null) return 'no';
            if ($k === 'si') $confirmo = true;
            $ultimaCliente = $m->created_at;
        }
        if (! $confirmo) return 'no';
        if ($ultimaCliente && $ultimaCliente->gt(now()->subMinutes(self::AUTO_COLA_MIN))) return 'esperar';

        // El envío: el de siempre, o el mismo de su orden anterior. Nunca gratis.
        $leida = $this->leerOrden((string) $orden->texto);
        $envio = static::montoDe((string) ($leida['envio'] ?? ''));
        if ($envio <= 0 || preg_match('/gratis|gratuit/iu', (string) ($leida['envio'] ?? ''))) return 'no';
        $base = (float) \App\Models\Setting::envio();
        if (abs($envio - $base) > 0.009) {
            $previa = WaMensaje::where('conversacion_id', $conv->id)->where('id', '<', $orden->id)
                ->where('direccion', 'saliente')->where('texto', 'like', '%rden de env%')
                ->orderByDesc('id')->first();
            $envAntes = $previa ? static::montoDe((string) ($this->leerOrden((string) $previa->texto)['envio'] ?? '')) : 0.0;
            if ($envAntes <= 0 || abs($envio - $envAntes) > 0.009) return 'no';
        }

        // San Miguel lo entregás vos.
        $muni = \App\Services\Municipios::normalizar(trim(explode(',', (string) ($leida['municipio'] ?? ''))[0]));
        if (str_contains($muni, 'san miguel')) return 'no';

        // Productos + envío = total escrito.
        $total = static::montoDe((string) ($leida['total'] ?? ''));
        $suma = self::sumaProductos((string) ($leida['productos'] ?? ''));
        if ($total <= 0 || ! $suma) return 'no';
        if (abs($suma[0] + $envio - $total) > 0.009 && abs($suma[1] + $envio - $total) > 0.009) return 'no';

        // Que no la agarren dos paneles a la vez.
        if (! \Illuminate\Support\Facades\Cache::add('wa:autocola:conv:' . $conv->id, 1, 120)) return 'esperar';

        // Lo que tenías en pantalla se guarda y se devuelve tal cual.
        $antes = ['abierta' => $this->abierta, 'pestana' => $this->pestana, 'texto' => $this->texto];
        foreach (get_object_vars($this) as $k => $v) if (str_starts_with($k, 'ped')) $antes['ped'][$k] = $v;
        $volver = function () use ($antes) {
            $this->abierta = $antes['abierta'];
            $this->pestana = $antes['pestana'];
            $this->texto   = $antes['texto'];
            foreach ((array) ($antes['ped'] ?? []) as $k => $v) $this->{$k} = $v;
        };

        $n0 = count((array) session('filament.notifications', []));
        $ok = false;
        try {
            $ev = $this->evaluarParaLote($conv);
            if ($ev['ok']) {
                $this->texto = '';
                $this->guardarPedido(false);
                $ok = str_contains(implode(' | ', $this->notifsTomar($n0)), 'Guardado en la cola');
            } else {
                $this->notifsTomar($n0);
            }
        } catch (\Throwable $e) {
            $this->notifsTomar($n0);
        }
        $volver();

        if (! $ok) return 'no';

        // Para la red de seguridad.
        $hechas = (array) \Illuminate\Support\Facades\Cache::get('wa:autocola:hechas', []);
        $hechas[$conv->id] = ['desde' => (int) WaMensaje::where('conversacion_id', $conv->id)->max('id'), 'at' => now()->timestamp];
        \Illuminate\Support\Facades\Cache::put('wa:autocola:hechas', $hechas, now()->addDays(2));

        Notification::make()
            ->title('⏱️ A la cola sola: ' . $conv->titulo())
            ->body('Confirmó la orden y pasaron ' . self::AUTO_COLA_MIN . ' minutos. Cobro $' . number_format($total, 2) . '.')
            ->success()->duration(8000)->send();

        return 'ok';
    }

    /** Escribió algo (que no es un gracias) después de pasar a la cola: vuelve a Pedidos. */
    private function autoColaRed(): bool
    {
        $hechas = (array) \Illuminate\Support\Facades\Cache::get('wa:autocola:hechas', []);
        if (! $hechas) return false;

        $hizo = false;
        foreach ($hechas as $convId => $h) {
            if (now()->timestamp - (int) ($h['at'] ?? 0) > 6 * 3600) { unset($hechas[$convId]); continue; }

            $nuevos = WaMensaje::where('conversacion_id', $convId)->where('id', '>', (int) ($h['desde'] ?? 0))
                ->where('direccion', 'entrante')->orderBy('id')->get(['tipo', 'texto']);
            $raro = $nuevos->first(fn ($m) => $m->tipo !== 'text' || self::siLimpio((string) $m->texto) === null);
            if (! $raro) continue;

            unset($hechas[$convId]);
            $conv = WaConversacion::find($convId);
            if (! $conv) continue;

            try {
                $proc = \App\Models\WaEtiqueta::porRol('procesada');
                $ped  = \App\Models\WaEtiqueta::porRol('pedido');
                $rev  = \App\Models\WaEtiqueta::porRol('asistente');
                if ($proc) $conv->etiquetas()->detach($proc->id);
                $conv->etiquetas()->syncWithoutDetaching(array_values(array_filter([$ped?->id, $rev?->id])));
                \Illuminate\Support\Facades\Cache::forget('wa:cuenta_etiquetas');
            } catch (\Throwable $e) {
            }

            Notification::make()
                ->title('🤖 Ojo: ' . $conv->titulo() . ' escribió después de pasar a la cola')
                ->body('«' . \Illuminate\Support\Str::limit(trim((string) $raro->texto) ?: '(' . $raro->tipo . ')', 90)
                    . '». Volvió a Pedidos. Revisá su guía en Crear guías antes de mandarla a Express.')
                ->warning()->persistent()->send();
            $hizo = true;
        }

        \Illuminate\Support\Facades\Cache::put('wa:autocola:hechas', $hechas, now()->addDays(2));
        return $hizo;
    }

    /** Cierra el lote y abre ese chat con la orden ya cargada, para corregirla. */
    public function abrirDelLote(int $convId): void
    {
        $this->lote = null;
        $this->abrir($convId);

        $conv = $this->conversacion();
        $orden = $conv ? $this->ultimaOrdenDe($conv) : null;
        if ($orden) $this->procesarOrden($orden->id);
    }

    public function cerrarLote(): void
    {
        $this->lote = null;
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

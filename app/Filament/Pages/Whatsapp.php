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

    public function mount(): void
    {
        $id = request()->integer('chat');
        if ($id) $this->abrir($id);
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

        $conv = $this->conversacion();
        if (! $conv) return;

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

<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use App\Services\OrdenWhatsappParser;
use App\Services\SistrackExcel;

/**
 * Todo el trabajo de guías en una sola pantalla, con tres secciones:
 *   ✍️  Crear guía  → pegar la orden de WhatsApp, autocompletar por teléfono y armar el lote
 *   📷  Fotos       → subir las etiquetas (lee el QR) para que el cliente vea su paquete
 *   👥  Clientes    → libreta que se llena sola, para no volver a escribir los datos
 *
 * La lista de guías se guarda en la base, así no se pierde si se cae el internet.
 */
class CrearGuia extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Guías';
    protected static ?string $title = 'Guías';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.crear-guia';

    public ?array $data = [];
    public array $lista = [];

    /** Sección visible: crear | fotos | clientes */
    public string $seccion = 'crear';

    /** ID de la guía que se está corrigiendo (null = se está creando una nueva). */
    public ?int $editando = null;

    /** Buscador de la libreta de clientes */
    public string $buscaCliente = '';

    /**
     * Dirección de la lista de guías.
     *
     * Ese recurso solo vive en el panel de administración. Desde el panel de
     * mensajes hay que pedirlo apuntando a 'admin' a mano, porque si no
     * Filament lo busca donde no está y tumba la pantalla. Y a los
     * colaboradores de solo chat no se les muestra: no pueden entrar ahí.
     */
    public function enlaceGuias(): ?string
    {
        if ((bool) (auth()->user()?->solo_chat ?? false)) return null;

        try {
            return \App\Filament\Resources\GuiaFotoResource::getUrl('index', [], true, 'admin');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ═══ Mandarle al cliente la foto de su paquete ═══════════════════════════

    /**
     * Las fotos subidas que todavía no se le mandaron a nadie, emparejadas.
     *
     * Se recalcula en cada dibujado a propósito: la ventana de 24 horas se
     * mueve sola con el reloj, así que una lista guardada envejece mal — diría
     * "lista para mandar" de algo que ya no se puede mandar.
     */
    public function fotosPorLote(): array
    {
        return \App\Services\FotosAlChat::porLotes();
    }

    /**
     * Cuántas fotos entraron hoy y cuántas de esas están en la lista de arriba.
     *
     * Es una red de seguridad, no un dato bonito. Si subís diez y arriba hay
     * ocho, faltan dos — y ahora eso se VE, en vez de tener que acordarse de
     * cuántas eran. Una foto que desaparece sin dejar rastro es lo peor que
     * puede pasar acá: el cliente se queda sin su comprobante y nadie se
     * entera.
     *
     * @return array{subidas:int, en_lista:int}
     */
    public function fotosDeHoy(): array
    {
        try {
            $subidas = \App\Models\GuiaFoto::whereNotNull('ruta')
                ->whereDate('created_at', '>=', now()->startOfDay())
                ->count();
        } catch (\Throwable $e) {
            return ['subidas' => 0, 'en_lista' => 0];
        }

        $enLista = 0;

        foreach ($this->fotosPorLote() as $t) {
            $enLista += count($t['filas']);
        }

        return ['subidas' => $subidas, 'en_lista' => $enLista];
    }

    /**
     * El botón de una tanda: sale de golpe lo que se pueda de ESA subida.
     *
     * Las que no se pueden mandar ni se tocan. Vuelven a aparecer en la lista
     * la próxima vez, que es justo lo que se quiere: nada se pierde por no
     * haber podido salir hoy.
     */
    public function mandarLoteAlChat(string $lote = ''): void
    {
        $r = \App\Services\FotosAlChat::mandarLote($lote, auth()->id());

        if ($r['mandadas'] === 0 && $r['fallaron'] === 0) {
            Notification::make()
                ->title('No había ninguna lista en esa tanda')
                ->body('Las que quedan están esperando que el cliente escriba, o no tienen chat.')
                ->warning()->send();
            return;
        }

        $cuerpo = $r['fallaron'] > 0
            ? "{$r['fallaron']} no salieron. El motivo queda escrito en cada una."
            : 'Les llegó la foto de su etiqueta con el enlace de rastreo.';

        Notification::make()
            ->title($r['mandadas'] === 1 ? 'Se mandó 1 foto' : "Se mandaron {$r['mandadas']} fotos")
            ->body($cuerpo)
            ->{$r['fallaron'] > 0 ? 'warning' : 'success'}()
            ->send();
    }

    /**
     * El teléfono que se está escribiendo a mano, por foto.
     *
     * Uno por fila: ['12' => '70551234']. Si fuera uno solo para toda la
     * pantalla, escribir en una borraría lo tecleado en otra.
     */
    public array $telManual = [];

    /**
     * Ponerle el teléfono a mano a una foto que quedó sin él.
     *
     * Lo que decide si la foto se puede mandar es el TELÉFONO, no el número de
     * guía. La guía sirve para el rastreo; el teléfono es lo que dice a quién
     * mandársela. Cuando el lector de la etiqueta no logra sacarlo —sale
     * movido, con brillo, o el texto quedó chico— esa foto se queda trabada sin
     * que haya forma de destrabarla desde acá. Con esto, se escribe y sale.
     *
     * Sirve igual para corregir uno leído mal, que pasa: el lector confunde
     * un 6 con un 5 y la foto le llegaría a otra persona.
     */
    public function guardarTelefono(int $id): void
    {
        $crudo = (string) ($this->telManual[$id] ?? '');
        $solo  = preg_replace('/\D/', '', $crudo);

        // Con el código de país adelante, se lo saca: en la base viven los
        // últimos ocho, que es como se identifica a la gente acá.
        if (strlen($solo) === 11 && str_starts_with($solo, '503')) {
            $solo = substr($solo, 3);
        }

        if (strlen($solo) !== 8) {
            Notification::make()
                ->title('Ese número no sirve')
                ->body('Tienen que ser 8 dígitos, como 7055 1234.')
                ->warning()->send();
            return;
        }

        $f = \App\Models\GuiaFoto::find($id);
        if (! $f) return;

        $f->telefono = $solo;
        $f->save();

        unset($this->telManual[$id]);

        // Se dice adónde va a ir, no solo que se guardó. Es la última
        // oportunidad de ver que el número es de otra persona antes de que la
        // foto salga.
        $conv = \App\Services\FotosAlChat::conversacionDe($solo);

        Notification::make()
            ->title('Teléfono guardado: ' . substr($solo, 0, 4) . ' ' . substr($solo, 4))
            ->body($conv
                ? ($conv->ventanaAbierta()
                    ? 'Ya quedó lista para mandar.'
                    : 'Hay chat, pero escribió hace más de 24 horas: queda esperando.')
                : 'Ese número nunca escribió a este WhatsApp, así que no hay chat adonde mandarla.')
            ->{$conv && $conv->ventanaAbierta() ? 'success' : 'warning'}()
            ->send();
    }

    /**
     * Limpiar una tanda entera de la lista, sin mandar nada.
     *
     * Es lo que evita equivocarse de tanda. Cuando quedan tres tandas viejas
     * arriba de la de hoy —porque tenían la ventana cerrada y nunca salieron—
     * hay que leer el título de cada una antes de apretar, y ahí es donde uno
     * manda la de anteayer creyendo que es la de hoy.
     *
     * Las fotos NO se borran: siguen en el rastreo del cliente. Lo único que
     * se dice es "de estas ya me ocupé".
     */
    public function limpiarLoteAlChat(string $lote = ''): void
    {
        $n = \App\Services\FotosAlChat::omitirLote($lote);

        Notification::make()
            ->title($n === 1 ? 'Se quitó 1 foto de la lista' : "Se quitaron {$n} fotos de la lista")
            ->body('Las fotos no se borraron: siguen en el rastreo del cliente.')
            ->success()->send();
    }

    /** Sacar una de la lista sin mandarla: ya se la pasaste por otro lado. */
    public function omitirFotoChat(int $id): void
    {
        $f = \App\Models\GuiaFoto::find($id);
        if (! $f) return;

        \App\Services\FotosAlChat::omitir($f);

        Notification::make()->title('Quitada de la lista')->success()->send();
    }

    /**
     * Las fotos se suben por fuera de Livewire (con JavaScript, para poder leer
     * el QR en el navegador), así que el panel no se entera solo. El propio
     * subidor avisa al terminar y acá se vuelve a dibujar la lista.
     */
    #[\Livewire\Attributes\On('fotos-subidas')]
    public function refrescarFotosChat(): void
    {
        // El solo hecho de atender el evento redibuja el componente, y
        // fotosParaChat() se vuelve a calcular. No hay nada más que hacer.
    }

    public function mount(): void
    {
        // Se puede llegar directo a una pestaña: /admin/crear-guia?seccion=fotos
        $pedida = (string) request()->query('seccion', '');
        if (in_array($pedida, ['crear', 'pdf', 'fotos'], true)) {
            $this->seccion = $pedida;
        }

        $this->recargarLista();
        $this->form->fill();

        // Si se llega desde el panel de WhatsApp (?wa=123), se trae el teléfono
        // del chat y el último mensaje largo, que casi siempre ES el pedido.
        // Así no hay que copiar y pegar nada entre pantallas.
        $this->desdeWhatsapp(request()->integer('wa'));
    }

    /**
     * Precarga el formulario con lo que hay en una conversación de WhatsApp.
     *
     * Nada se guarda automáticamente: solo se llenan los campos para que el
     * agente revise y corrija antes de agregar la guía a la lista.
     */
    private function desdeWhatsapp(?int $conversacionId): void
    {
        if (! $conversacionId) return;

        try {
            if (! \App\Models\WaConversacion::hayTabla()) return;

            $conv = \App\Models\WaConversacion::find($conversacionId);
            if (! $conv) return;
        } catch (\Throwable $e) {
            return;
        }

        $this->seccion = 'crear';

        // El mensaje más largo de los últimos que mandó el cliente: los pedidos
        // vienen en un bloque de texto, no en un "hola".
        $pegar = '';
        try {
            $pegar = (string) \App\Models\WaMensaje::where('conversacion_id', $conv->id)
                ->where('direccion', 'entrante')
                ->where('tipo', 'text')
                ->orderByDesc('id')->limit(12)->get()
                ->sortByDesc(fn ($m) => mb_strlen((string) $m->texto))
                ->first()?->texto;
        } catch (\Throwable $e) {
        }

        $datos = ['telefono' => $conv->telefono];

        // Si ya compró antes, se completan sus datos con la misma memoria de
        // clientes que usa la pantalla normalmente.
        $cliente = $conv->cliente();
        if ($cliente) {
            foreach (['nombre', 'direccion', 'departamento', 'municipio'] as $campo) {
                if (! empty($cliente[$campo])) $datos[$campo] = $cliente[$campo];
            }
        } elseif (trim((string) $conv->nombre) !== '') {
            $datos['nombre'] = $conv->nombre;
        }

        // Y se pasa el texto por el mismo intérprete de siempre.
        if (mb_strlen(trim($pegar)) > 25) {
            $r = OrdenWhatsappParser::parsear($pegar);

            if (! empty($r['nombre']) && empty($datos['nombre'])) $datos['nombre'] = $r['nombre'];
            if (! empty($r['telefono']))    $datos['telefono_recibe'] = $r['telefono'];
            if (! empty($r['direccion']) && empty($datos['direccion'])) $datos['direccion'] = $r['direccion'];

            if (! empty($r['departamento'])) {
                $datos['departamento'] = $r['departamento'];
                $datos['municipio']    = $r['municipio_nombre'] ?: ($datos['municipio'] ?? null);
            }

            if (! empty($r['items'])) {
                $datos['descripcion'] = collect($r['items'])
                    ->map(fn ($i) => ((int) ($i['cantidad'] ?? 1)) . ' ' . trim((string) ($i['producto'] ?? '')))
                    ->implode(', ');

                $datos['cobrar'] = number_format(
                    collect($r['items'])->sum(fn ($i) => ((int) ($i['cantidad'] ?? 1)) * (float) ($i['precio'] ?? 0))
                    + (float) ($r['envio'] ?? 0),
                    2, '.', ''
                );
            }
        }

        $this->form->fill($datos);

        Notification::make()
            ->title('📲 Pedido traído del chat')
            ->body('Revisá los datos y corregí lo que haga falta antes de agregarlo a la lista.')
            ->success()->send();
    }

    /** Botón para traer la libreta de clientes exportada de Sistrack. */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('importar_clientes')
                ->label('Importar clientes')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Importar clientes desde Sistrack')
                ->modalDescription('Subí el Excel de contactos que descargaste de Sistrack. Los que ya estén guardados no se duplican.')
                ->modalSubmitActionLabel('Importar')
                ->form([
                    Forms\Components\FileUpload::make('archivo')
                        ->label('Archivo de contactos (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required()
                        ->disk('local')
                        ->directory('importaciones'),
                ])
                ->action(function (array $data) {
                    $ruta = \Illuminate\Support\Facades\Storage::disk('local')->path($data['archivo']);

                    try {
                        $r = \App\Services\ImportadorClientes::importar($ruta);

                        Notification::make()
                            ->title('✅ Clientes importados')
                            ->body("Nuevos: {$r['nuevos']} · Actualizados: {$r['actualizados']} · Sin teléfono: {$r['sin_telefono']}")
                            ->success()->persistent()->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo importar')
                            ->body($e->getMessage())
                            ->danger()->persistent()->send();
                    } finally {
                        try { \Illuminate\Support\Facades\Storage::disk('local')->delete($data['archivo']); } catch (\Throwable $e) {}
                    }
                }),
        ];
    }

    /** Clientes de la libreta (filtrados por el buscador). */
    public function getClientesProperty()
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('clientes')) return collect();

            $q = \App\Models\Cliente::query()->orderByDesc('updated_at');

            if (trim($this->buscaCliente) !== '') {
                $t = trim($this->buscaCliente);
                $d = preg_replace('/\D/', '', $t);
                $q->where(function ($w) use ($t, $d) {
                    $w->where('nombre', 'like', '%' . $t . '%');
                    if ($d !== '') $w->orWhere('telefono', 'like', '%' . $d . '%');
                });
            }

            return $q->limit(40)->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** Trae los datos de un cliente al formulario de la guía. */
    public function usarCliente(int $id): void
    {
        $c = \App\Models\Cliente::find($id);
        if (! $c) return;

        $this->form->fill([
            'telefono'     => $c->telefono,
            'nombre'       => $c->nombre,
            'direccion'    => $c->direccion,
            'departamento' => $c->departamento,
            'municipio'    => $c->municipio,
        ]);

        $this->seccion = 'crear';
        $this->buscaCliente = '';   // se cierra el buscador al elegirlo

        Notification::make()
            ->title('👤 ' . ($c->nombre ?: $c->telefono))
            ->body('Datos cargados. Solo falta qué lleva el paquete.')
            ->success()->send();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('pegar')->label('1. Pega aquí la orden de WhatsApp')
                ->rows(4)->dehydrated(false)->live(debounce: 700)
                ->placeholder("Orden de envío:🚚\n✅Nombre completo:\n...\n✅Dirección:\n...\n✅producto:\n...")
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $r = OrdenWhatsappParser::parsear((string) $state);
                    if ($r['nombre'])      $set('nombre', $r['nombre']);
                    // El de junto al nombre identifica al cliente; el de la línea "Teléfono:"
                    // es al que llama el repartidor.
                    if ($r['telefono_id']) $set('telefono', $r['telefono_id']);
                    if ($r['telefono'])    $set('telefono_recibe', $r['telefono']);
                    if ($r['direccion']) $set('direccion', $r['direccion']);
                    // Municipio y departamento detectados del catálogo (se pueden corregir a mano).
                    if (! empty($r['departamento'])) {
                        $set('departamento', $r['departamento']);
                        $set('municipio', $r['municipio_nombre'] ?: null);
                    } else {
                        // Segunda opinión: el buscador de municipios del panel
                        // barre TODO el texto, no solo el renglón "Municipio:".
                        // Casi siempre el municipio está repetido dentro de la
                        // dirección — "caserío los chilamates nueva concepción" —
                        // y ahí es donde aparece cuando el renglón vino vacío.
                        $hallado = \App\Services\Municipios::buscarEn((string) $state);
                        $depto   = $hallado ? \App\Services\Municipios::departamentoSeguro($hallado) : null;

                        // Los dos catálogos escriben algunos nombres distinto,
                        // y el desplegable solo acepta los suyos: se pone
                        // únicamente si existe también del otro lado.
                        $deSistrack = $depto ? (config('municipios_sv', [])[$depto] ?? []) : [];

                        if ($hallado && $depto && in_array($hallado, $deSistrack, true)) {
                            $set('departamento', $depto);
                            $set('municipio', $hallado);
                        }
                    }
                    // ── Los productos y la suma ──────────────────────────────
                    //
                    // Primero se intenta reconocerlos contra el catálogo real.
                    // Es mejor que leer los precios del texto: el texto lo
                    // escribió alguien apurado y si puso $17 donde eran $18,
                    // esa diferencia la termina pagando el negocio.
                    $cat = \App\Services\ReconocerProductos::enTexto((string) $state);

                    if (! empty($cat['items'])) {
                        $set('descripcion', \App\Services\ReconocerProductos::descripcion($cat['items']));
                        $set('cobrar', number_format(
                            $cat['total'] + (float) ($r['envio'] ?? 0), 2, '.', ''
                        ));

                        // Lo que no reconoció no se calla: quedó fuera de la
                        // suma y hay que agregarlo a mano.
                        if (! empty($cat['dudosos'])) {
                            Notification::make()
                                ->title('⚠️ Hay renglones que no reconocí')
                                ->body('«' . implode('» · «', array_slice($cat['dudosos'], 0, 3)) . '». '
                                     . 'No entraron en la suma. Revisá el total antes de guardar.')
                                ->warning()->persistent()->send();
                        }
                    } elseif (! empty($r['items'])) {
                        // No reconoció nada del catálogo: se usa lo que decía el
                        // texto, que es lo que se hacía antes. Mejor eso que nada.
                        $set('descripcion', collect($r['items'])
                            ->map(fn ($i) => ((int) ($i['cantidad'] ?? 1)) . ' ' . trim((string) ($i['producto'] ?? '')))
                            ->implode(', '));
                        $set('cobrar', number_format(collect($r['items'])
                            ->sum(fn ($i) => ((int) ($i['cantidad'] ?? 1)) * (float) ($i['precio'] ?? 0))
                            + (float) ($r['envio'] ?? 0), 2, '.', ''));
                    }
                }),

            Forms\Components\TextInput::make('nombre')->label('2. Nombre del cliente')->required(),
            // Al escribir el teléfono, si el cliente ya está en la libreta se llena todo solo.
            Forms\Components\TextInput::make('telefono')->label('3. Teléfono junto al nombre (ID del cliente)')
                ->tel()->required()->placeholder('7777-7777')
                ->helperText('El de quien pide. Si ya le enviaste antes, se llenan sus datos solos.')
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                    $c = \App\Models\Cliente::buscar($state);
                    if (! $c) return;

                    // Solo rellena lo que esté vacío: no pisa lo que ya escribiste.
                    foreach (['nombre', 'direccion', 'departamento'] as $campo) {
                        if (trim((string) $get($campo)) === '' && ! empty($c[$campo])) {
                            $set($campo, $c[$campo]);
                        }
                    }
                    if (trim((string) $get('municipio')) === '' && ! empty($c['municipio'])) {
                        $set('municipio', $c['municipio']);
                    }

                    Notification::make()
                        ->title('👤 Cliente encontrado: ' . ($c['nombre'] ?: 'sin nombre'))
                        ->body('Se llenaron sus datos. Revisá que la dirección siga igual.')
                        ->success()->send();
                }),

            Forms\Components\TextInput::make('telefono_recibe')->label('4. Teléfono para el repartidor')
                ->tel()->placeholder('Al que debe llamar para entregar')
                ->helperText('El de la línea "Teléfono:" de la orden. Si va vacío, se usa el del cliente.'),

            Forms\Components\Select::make('departamento')->label('4. Departamento')
                ->options(fn () => array_combine(array_keys(config('municipios_sv', [])), array_keys(config('municipios_sv', []))))
                ->searchable()->live()->required()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('municipio', null)),

            Forms\Components\Select::make('municipio')->label('5. Municipio')
                ->options(function (Forms\Get $get) {
                    $m = config('municipios_sv', [])[$get('departamento')] ?? [];
                    return $m ? array_combine($m, $m) : [];
                })
                ->searchable()->required()->placeholder('Elige primero el departamento'),

            Forms\Components\Textarea::make('direccion')->label('6. Dirección')->rows(2)->required(),
            Forms\Components\Textarea::make('descripcion')->label('7. Qué lleva el paquete')->rows(2)->required()
                ->placeholder('Ej: 2 Calzoncito Magic M'),

            Forms\Components\TextInput::make('cobrar')->label('8. Cobrar al entregar ($)')
                ->numeric()->prefix('$')->default('0')
                ->helperText('Pon 0 si ya está pagado.'),
        ])->columns(1)->statePath('data');
    }

    // Solo los dígitos del teléfono, para comparar sin importar cómo se escribió.
    private static function soloDigitos(?string $tel): string
    {
        $d = preg_replace('/\D/', '', (string) $tel);
        if (strlen($d) === 11 && str_starts_with($d, '503')) $d = substr($d, 3);
        return $d;
    }

    #[\Livewire\Attributes\On('agregar-forzado')]
    public function agregarForzado(): void
    {
        $this->agregar(true);
    }

    /** "Bajalo igual": ya se le avisó qué estaba mal y decidió seguir. */
    #[\Livewire\Attributes\On('bajar-aun-asi')]
    public function bajarAunAsi()
    {
        return $this->descargar(true);
    }

    /** Deja el formulario en blanco para empezar otra guía de cero. */
    public function limpiarCampos(): void
    {
        $this->form->fill();
        $this->buscaCliente = '';
        $this->editando = null;

        Notification::make()->title('🧹 Campos limpios')->success()->send();
    }

    /** Sube una guía de la lista al formulario para corregirla. */
    public function editar(int $id): void
    {
        $g = \App\Models\GuiaBorrador::find($id);
        if (! $g) {
            Notification::make()->title('Esa guía ya no está en la lista')->warning()->send();
            $this->recargarLista();
            return;
        }

        $this->editando = $g->id;
        $this->form->fill([
            'nombre'          => $g->nombre,
            'telefono'        => $g->telefono,
            'telefono_recibe' => $g->telefono_recibe,
            'direccion'       => $g->direccion,
            'municipio'       => $g->municipio,
            'departamento'    => $g->departamento,
            'descripcion'     => $g->descripcion,
            'cobrar'          => (float) $g->cobrar,
        ]);

        $this->dispatch('ir-al-formulario');

        Notification::make()
            ->title('✏️ Corrigiendo la guía de ' . $g->nombre)
            ->body('Cambiá lo que necesités y tocá "Guardar cambios".')
            ->info()->send();
    }

    /** Sale del modo corrección sin tocar nada. */
    public function cancelarEdicion(): void
    {
        $this->editando = null;
        $this->form->fill();

        Notification::make()->title('Corrección cancelada')->success()->send();
    }

    public function agregar(bool $forzar = false): void
    {
        // Si falta algo, avisa y salta al primer campo vacío (no solo un mensaje).
        $faltantes = [
            'nombre'      => 'el nombre del cliente',
            'telefono'    => 'el teléfono',
            'departamento'=> 'el departamento',
            'municipio'   => 'el municipio',
            'direccion'   => 'la dirección',
            'descripcion' => 'qué lleva el paquete',
        ];
        foreach ($faltantes as $campo => $etiqueta) {
            if (trim((string) ($this->data[$campo] ?? '')) === '') {
                Notification::make()->title('Falta ' . $etiqueta)->warning()->send();
                $this->dispatch('enfocar-campo', campo: $campo);
                return;
            }
        }

        $d = $this->form->getState();

        // ---- Modo corrección: se actualiza la guía en vez de crear otra ----
        if ($this->editando) {
            $g = \App\Models\GuiaBorrador::find($this->editando);
            if (! $g) {
                $this->editando = null;
                Notification::make()->title('Esa guía ya no está en la lista')->warning()->send();
                $this->recargarLista();
                return;
            }

            // Se guarda el teléfono que tenía ANTES: si lo estamos corrigiendo,
            // hay que actualizar el pedido pendiente de ese número, no crear otro.
            $telefonoAnterior = $g->telefono;

            $g->fill([
                'nombre'          => $d['nombre'],
                'telefono'        => $d['telefono'],
                'telefono_recibe' => $d['telefono_recibe'] ?? null,
                'direccion'       => $d['direccion'],
                'municipio'       => $d['municipio'],
                'departamento'    => $d['departamento'],
                'descripcion'     => $d['descripcion'],
                'cobrar'          => (float) ($d['cobrar'] ?? 0),
            ])->save();

            // Se actualiza también lo que ve el cliente al rastrear.
            \App\Models\GuiaFoto::registrarPendiente([
                'telefono'          => $d['telefono'] ?? '',
                'nombre'            => $d['nombre'] ?? '',
                'contenido'         => $d['descripcion'] ?? '',
                'cobrar'            => $d['cobrar'] ?? null,
                'telefono_anterior' => $telefonoAnterior,
            ]);

            \App\Models\Cliente::recordar([
                'telefono'     => $d['telefono'] ?? '',
                'nombre'       => $d['nombre'] ?? '',
                'direccion'    => $d['direccion'] ?? '',
                'municipio'    => $d['municipio'] ?? '',
                'departamento' => $d['departamento'] ?? '',
            ]);

            $this->editando = null;
            $this->recargarLista();
            $this->form->fill();

            Notification::make()->title('✅ Guía corregida')->success()->send();
            return;
        }

        // Evita meter dos veces la misma guía en el lote. Se compara por el ID del
        // cliente (su teléfono). Si es el mismo cliente pero otro pedido, se puede forzar.
        $idNuevo = self::soloDigitos($d['telefono'] ?? '');
        if (! $forzar && $idNuevo !== '') {
            foreach ($this->lista as $g) {
                if (self::soloDigitos($g['telefono'] ?? '') !== $idNuevo) continue;

                $mismaDir  = mb_strtolower(trim((string) ($g['direccion'] ?? ''))) === mb_strtolower(trim((string) ($d['direccion'] ?? '')));
                $mismoProd = mb_strtolower(trim((string) ($g['descripcion'] ?? ''))) === mb_strtolower(trim((string) ($d['descripcion'] ?? '')));

                if ($mismaDir && $mismoProd) {
                    Notification::make()
                        ->title('⚠️ Guía repetida')
                        ->body('Ya tienes esta misma guía en el lote (' . ($g['nombre'] ?? '') . ' · ' . $idNuevo . '). No se agregó.')
                        ->danger()->persistent()->send();
                    return;
                }

                Notification::make()
                    ->title('⚠️ Ese cliente ya está en el lote')
                    ->body(($g['nombre'] ?? '') . ' · ' . $idNuevo . '. Si es otro pedido distinto, confirma para agregarlo.')
                    ->warning()->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('forzar')
                            ->label('Sí, agregar de todos modos')
                            ->button()->close()->dispatch('agregar-forzado'),
                    ])
                    ->send();
                return;
            }
        }

        // Se guarda en la base: así no se pierde aunque se caiga el internet,
        // se reinicie la página o se cambie de aplicación.
        \App\Models\GuiaBorrador::create([
            'nombre'          => $d['nombre'],
            'telefono'        => $d['telefono'],
            'telefono_recibe' => $d['telefono_recibe'] ?? null,
            'direccion'       => $d['direccion'],
            'municipio'       => $d['municipio'],
            'departamento'    => $d['departamento'],
            'descripcion'     => $d['descripcion'],
            'cobrar'          => (float) ($d['cobrar'] ?? 0),
            // Quién la armó: con varias personas metiendo guías en la misma
            // cola, hace falta saber de quién es cada una.
            'user_id'         => auth()->id(),
        ]);

        // El cliente ya puede rastrear con su teléfono en este mismo momento:
        // verá "pedido confirmado". El número de guía se rellena solo cuando
        // se importe el PDF de etiquetas.
        \App\Models\GuiaFoto::registrarPendiente([
            'telefono'  => $d['telefono'] ?? '',
            'nombre'    => $d['nombre'] ?? '',
            'contenido' => $d['descripcion'] ?? '',
            'cobrar'    => $d['cobrar'] ?? null,
        ]);

        // Se recuerda al cliente para la próxima vez (basta el teléfono).
        \App\Models\Cliente::recordar([
            'telefono'     => $d['telefono'] ?? '',
            'nombre'       => $d['nombre'] ?? '',
            'direccion'    => $d['direccion'] ?? '',
            'municipio'    => $d['municipio'] ?? '',
            'departamento' => $d['departamento'] ?? '',
        ]);

        $this->recargarLista();
        $this->form->fill();

        Notification::make()->title('✅ Guardada (' . count($this->lista) . ' en la lista)')->success()->send();
    }

    /** Vuelve a leer la lista desde la base. */
    public function recargarLista(): void
    {
        $this->lista = \App\Models\GuiaBorrador::lista()
            ->map(fn ($g) => $g->aFila() + [
                'id'      => $g->id,
                'enviado' => $g->yaEnviado(),
            ])
            ->all();
    }

    /** Deja marcado que ya se le mandó el enlace de rastreo a ese cliente. */
    public function marcarEnviado(int $id): void
    {
        $g = \App\Models\GuiaBorrador::find($id);
        if (! $g) return;

        // Siempre queda marcado como enviado: si vuelve a copiar el mensaje
        // (por ejemplo para reenviarlo), no debe volverse a poner en rojo.
        $g->enviado_at = now();
        $g->save();

        $this->recargarLista();
    }

    /**
     * Lo último que se borró, por si hay que traerlo de vuelta.
     *
     * Se guarda el contenido entero de las filas, no los identificadores: la
     * fila ya no existe, así que hay que poder volver a crearla tal cual.
     */
    public array $borradas = [];

    public function quitar(int $id): void
    {
        $g = \App\Models\GuiaBorrador::find($id);
        if (! $g) return;

        $this->recordarBorradas([$g]);

        $nombre = trim((string) $g->nombre) ?: 'la guía';
        $g->delete();
        $this->recargarLista();

        $this->avisarConDeshacer("Se quitó {$nombre}", 1);
    }

    public function vaciar(): void
    {
        try {
            $todas = \App\Models\GuiaBorrador::all();
            $cuantas = $todas->count();

            if ($cuantas === 0) {
                Notification::make()->title('La lista ya estaba vacía')->warning()->send();
                return;
            }

            $this->recordarBorradas($todas->all());

            \App\Models\GuiaBorrador::query()->delete();
        } catch (\Throwable $e) {
            Notification::make()->title('No se pudo vaciar')->body($e->getMessage())->danger()->send();
            return;
        }

        $this->recargarLista();

        $this->avisarConDeshacer(
            $cuantas === 1 ? 'Se vació la lista (1 guía)' : "Se vació la lista ({$cuantas} guías)",
            $cuantas
        );
    }

    /** Se queda con los datos de lo borrado, listos para volver a crearse. */
    private function recordarBorradas(array $filas): void
    {
        $this->borradas = array_map(function ($g) {
            return [
                'nombre'          => $g->nombre,
                'telefono'        => $g->telefono,
                'telefono_recibe' => $g->telefono_recibe,
                'direccion'       => $g->direccion,
                'municipio'       => $g->municipio,
                'departamento'    => $g->departamento,
                'descripcion'     => $g->descripcion,
                'cobrar'          => (float) $g->cobrar,
                'enviado_at'      => $g->enviado_at?->toDateTimeString(),
                'user_id'         => $g->user_id ?? null,
            ];
        }, $filas);
    }

    /**
     * El aviso con el botón de volver atrás.
     *
     * Persistente a propósito: si se fuera solo a los cinco segundos, la mitad
     * de las veces uno se da cuenta del error cuando ya se fue. Se queda hasta
     * que lo cierres.
     */
    private function avisarConDeshacer(string $titulo, int $cuantas): void
    {
        Notification::make()
            ->title($titulo)
            ->body($cuantas === 1
                ? 'Si fue sin querer, se puede traer de vuelta.'
                : 'Si fue sin querer, se pueden traer las ' . $cuantas . ' de vuelta.')
            ->warning()->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('deshacer')
                    ->label('↶ Deshacer')
                    ->button()->close()->dispatch('deshacer-borrado'),
            ])
            ->send();
    }

    /** Vuelve a crear lo último que se borró. */
    #[\Livewire\Attributes\On('deshacer-borrado')]
    public function deshacerBorrado(): void
    {
        if (empty($this->borradas)) {
            Notification::make()->title('Ya no hay nada que deshacer')->warning()->send();
            return;
        }

        $vueltas = 0;

        foreach ($this->borradas as $fila) {
            try {
                \App\Models\GuiaBorrador::create($fila);
                $vueltas++;
            } catch (\Throwable $e) {
            }
        }

        $this->borradas = [];
        $this->recargarLista();

        Notification::make()
            ->title($vueltas === 1 ? '↶ Volvió la guía' : "↶ Volvieron las {$vueltas} guías")
            ->success()->send();
    }

    /**
     * Baja el Excel del lote que está esperando y lo cierra.
     *
     * Solo salen las guías sin número de lote, que son las que todavía no se
     * han bajado nunca. Al terminar quedan marcadas con su número y su fecha,
     * así que las que entren después arrancan el lote siguiente y la próxima
     * bajada no las repite.
     *
     * Ese era el riesgo antes: la lista no se vaciaba —a propósito, por si hay
     * que repetir el archivo— y la segunda bajada traía otra vez las primeras.
     * Sistrack las habría recibido dos veces.
     */
    public function descargar(bool $aunAsi = false)
    {
        $pendientes = collect($this->lista)->filter(fn ($g) => $g['pendiente'] ?? true)->values();

        if ($pendientes->isEmpty()) {
            Notification::make()
                ->title('No hay guías esperando')
                ->body(empty($this->lista)
                    ? 'La lista está vacía.'
                    : 'Todas las de la lista ya se bajaron. Las nuevas van a formar el lote siguiente.')
                ->warning()->send();
            return null;
        }

        // Última parada antes de que salga el Excel. Si algo va a salir mal, se
        // dice ahora: después el paquete ya va en camino.
        if (! $aunAsi) {
            $malas = [];

            foreach ($pendientes as $g) {
                if (\App\Services\RevisarGuia::tieneError($g)) {
                    $malas[] = trim((string) ($g['nombre'] ?? '')) ?: 'sin nombre';
                }
            }

            if ($malas) {
                Notification::make()
                    ->title('⚠️ ' . count($malas) . (count($malas) === 1 ? ' guía sale mal' : ' guías salen mal'))
                    ->body(implode(' · ', array_slice($malas, 0, 4))
                        . (count($malas) > 4 ? ' y ' . (count($malas) - 4) . ' más' : '')
                        . '. Mirá el detalle en rojo debajo de cada una.')
                    ->danger()->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('aunAsi')
                            ->label('Bajar el Excel de todos modos')
                            ->button()->close()->dispatch('bajar-aun-asi'),
                    ])
                    ->send();

                return null;
            }
        }

        $numero = \App\Models\GuiaBorrador::proximoNumero();

        $nombre = 'sistrack_lote' . $numero . '_' . now()->format('Y-m-d_Hi') . '.xlsx';
        $path   = storage_path('app/' . $nombre);

        // En pantalla el último va arriba; en el Excel se exporta en el orden en que
        // se fueron agregando.
        SistrackExcel::generarDesdeLista(array_reverse($pendientes->all()), $path);

        // Recién con el archivo en la mano se cierra el lote. Si la generación
        // fallara arriba, nada queda marcado y se puede volver a intentar.
        try {
            if (\App\Models\GuiaBorrador::hayLotes()) {
                \App\Models\GuiaBorrador::whereNull('lote')->update([
                    'lote'          => $numero,
                    'descargado_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('El Excel se bajó, pero no se pudo cerrar el lote')
                ->body('Ojo: la próxima descarga podría repetir estas guías. ' . $e->getMessage())
                ->danger()->persistent()->send();
        }

        $this->recargarLista();

        // La lista queda guardada por si hay que volver a bajar el archivo.
        return response()->download($path, $nombre)->deleteFileAfterSend();
    }

    /**
     * Vuelve a bajar un lote ya cerrado.
     *
     * Pasa: se pierde el archivo, o Sistrack rechaza la carga y hay que
     * repetirla. No reabre nada — el lote sigue cerrado y las guías nuevas
     * siguen esperando en el siguiente.
     */
    public function bajarLote(int $numero)
    {
        try {
            $filas = \App\Models\GuiaBorrador::where('lote', $numero)
                ->orderBy('id')->get()->map(fn ($g) => $g->aFila())->all();
        } catch (\Throwable $e) {
            Notification::make()->title('No se pudo leer ese lote')->danger()->send();
            return null;
        }

        if (! $filas) {
            Notification::make()->title('Ese lote ya no tiene guías')->warning()->send();
            return null;
        }

        $nombre = 'sistrack_lote' . $numero . '.xlsx';
        $path   = storage_path('app/' . $nombre);

        SistrackExcel::generarDesdeLista($filas, $path);

        return response()->download($path, $nombre)->deleteFileAfterSend();
    }
}

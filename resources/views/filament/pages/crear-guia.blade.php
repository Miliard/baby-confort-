<x-filament-panels::page>
    <div class="mx-auto w-full max-w-xl">

        {{-- Secciones: crear guía · fotos · clientes --}}
        <x-filament::tabs class="mb-5">
            <x-filament::tabs.item :active="$seccion === 'crear'" wire:click="$set('seccion','crear')" icon="heroicon-m-pencil-square">
                Crear guía
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$seccion === 'pdf'" wire:click="$set('seccion','pdf')" icon="heroicon-m-document-text">
                PDF guías
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$seccion === 'fotos'" wire:click="$set('seccion','fotos')" icon="heroicon-m-camera">
                Fotos
            </x-filament::tabs.item>
        </x-filament::tabs>

        {{-- ─────────── CREAR GUÍA ─────────── --}}
        <div @class(['hidden' => $seccion !== 'crear'])>

            {{-- Buscar un cliente ya guardado (si no está, se llena a mano abajo) --}}
            <div class="mb-5">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input type="text" wire:model.live.debounce.400ms="buscaCliente"
                        placeholder="¿Cliente conocido? Buscá por teléfono o nombre" />
                </x-filament::input.wrapper>

                @if(trim($buscaCliente) !== '')
                    <div class="mt-2 space-y-2">
                        @forelse($this->clientes->take(6) as $c)
                            <button type="button" wire:click="usarCliente({{ $c->id }})"
                                class="flex w-full items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 text-left shadow-sm transition hover:border-primary-400 dark:border-white/10 dark:bg-gray-900">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $c->nombre ?: 'Sin nombre' }}</span>
                                        <x-filament::badge color="success" size="xs">{{ $c->veces }}</x-filament::badge>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ $c->telefono }} · {{ $c->municipio }}{{ $c->departamento ? ', ' . $c->departamento : '' }}
                                    </p>
                                </div>
                                <x-filament::icon icon="heroicon-m-arrow-right-circle" class="h-5 w-5 flex-none text-primary-600" />
                            </button>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 p-4 text-center text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                                No está guardado. Llená los datos abajo y quedará para la próxima. 👇
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>

            <form wire:submit="agregar" id="bc-formulario">
                @if($editando)
                    <div class="mb-3 flex items-center gap-2 rounded-xl border border-warning-300 bg-warning-50 px-3 py-2 text-sm font-semibold text-warning-700 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-400">
                        ✏️ Estás corrigiendo una guía de la lista
                    </div>
                @endif

                {{ $this->form }}

                <div class="mt-4 flex gap-2">
                    <x-filament::button type="submit" size="lg"
                        :color="$editando ? 'warning' : 'success'" class="flex-1 justify-center">
                        {{ $editando ? '💾 Guardar cambios' : '➕ Agregar a la lista' }}
                    </x-filament::button>

                    @if($editando)
                        <x-filament::button type="button" size="lg" color="gray" outlined
                            icon="heroicon-m-x-mark" wire:click="cancelarEdicion" title="Cancelar la corrección">
                            Cancelar
                        </x-filament::button>
                    @else
                        <x-filament::button type="button" size="lg" color="gray" outlined
                            icon="heroicon-m-arrow-path" wire:click="limpiarCampos" title="Limpiar los campos">
                            Limpiar
                        </x-filament::button>
                    @endif
                </div>
            </form>

            {{-- Lista acumulada (guardada en la base: no se pierde) --}}
            <div class="mt-8">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-950 dark:text-white">
                        📦 Guías listas
                        <span class="ml-1 rounded-full bg-primary-600 px-2 py-0.5 text-xs font-bold text-white">
                            {{ count($lista) }}
                        </span>
                    </h3>
                    @if(count($lista))
                        <x-filament::link color="danger" tag="button" wire:click="vaciar" wire:confirm="¿Vaciar toda la lista?" size="sm">
                            Vaciar
                        </x-filament::link>
                    @endif
                </div>

                @php
                    $conteoTel = collect($lista)
                        ->map(fn ($x) => preg_replace('/\D/', '', (string) ($x['telefono'] ?? '')))
                        ->countBy();
                    $faltanMensaje = collect($lista)->reject(fn ($x) => $x['enviado'] ?? false)->count();
                @endphp

                @if(count($lista))
                    <div @class([
                        'mb-2 rounded-lg px-3 py-2 text-xs font-semibold',
                        'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400'    => $faltanMensaje > 0,
                        'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $faltanMensaje === 0,
                    ])>
                        @if($faltanMensaje > 0)
                            📩 Falta mandarle el enlace a {{ $faltanMensaje }}
                            {{ $faltanMensaje === 1 ? 'cliente' : 'clientes' }}
                        @else
                            ✅ Ya les mandaste el enlace a todos
                        @endif
                    </div>
                @endif

                <div class="space-y-2">
                    @forelse($lista as $g)
                        @php
                            $tel = preg_replace('/\D/', '', (string) ($g['telefono'] ?? ''));
                            $rep = $tel !== '' && ($conteoTel[$tel] ?? 0) > 1;
                        @endphp

                        <div wire:key="guia-{{ $g['id'] ?? $loop->index }}" @class([
                            'flex items-start gap-3 rounded-xl border p-3 shadow-sm transition',
                            'bg-white dark:bg-gray-900',
                            'border-danger-300 dark:border-danger-500/50'   => $rep,
                            'border-primary-300 dark:border-primary-500/50' => $loop->first && ! $rep,
                            'border-gray-200 dark:border-white/10'          => ! $loop->first && ! $rep,
                        ])>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $g['nombre'] }}
                                    </span>
                                    @if($loop->first)
                                        <x-filament::badge color="primary" size="xs">última</x-filament::badge>
                                    @endif
                                    @if($rep)
                                        <x-filament::badge color="danger" size="xs">repetido</x-filament::badge>
                                    @endif
                                </div>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $g['telefono'] }} · {{ $g['municipio'] }}, {{ $g['departamento'] }}
                                </p>

                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $g['descripcion'] }}
                                    @if($g['cobrar'] > 0)
                                        · <span class="font-semibold text-success-600 dark:text-success-400">Cobrar ${{ number_format($g['cobrar'], 2) }}</span>
                                    @else
                                        · <span class="font-medium">Pagado</span>
                                    @endif
                                </p>
                            </div>

                            <div class="flex flex-none items-center gap-1.5">
                                {{-- Mensaje listo para el cliente: se puede mandar YA, sin esperar el PDF.
                                     Rojo = falta mandarlo · Verde = ya enviado. --}}
                                {{-- El mensaje viaja en un atributo, NO en el estado de Alpine.
                                     Alpine solo llena su estado la primera vez que dibuja la fila:
                                     si se guardara ahí, al corregir la guía el botón seguiría
                                     copiando el texto viejo. En el atributo, Livewire lo refresca. --}}
                                <div x-data="{}">
                                    <x-filament::button
                                        size="xs"
                                        :color="($g['enviado'] ?? false) ? 'success' : 'danger'"
                                        data-msg="{{ \App\Models\GuiaBorrador::mensajeCliente($g) }}"
                                        x-on:click.stop="bcCopiarGuia($el, {{ $g['id'] ?? 0 }}, $wire)">
                                        {{ ($g['enviado'] ?? false) ? '✅ Enlace enviado' : '📋 Copiar mensaje' }}
                                    </x-filament::button>
                                </div>

                                <x-filament::icon-button
                                    icon="heroicon-m-pencil-square" color="warning" size="sm"
                                    wire:click="editar({{ $g['id'] ?? 0 }})" label="Corregir esta guía" />

                                <x-filament::icon-button
                                    icon="heroicon-m-x-mark" color="danger" size="sm"
                                    wire:click="quitar({{ $g['id'] ?? 0 }})"
                                    wire:confirm="¿Quitar esta guía de la lista?" label="Quitar de la lista" />
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                            Aún no has agregado guías.<br>
                            Pegá una orden arriba y tocá <span class="font-semibold">"Agregar a la lista"</span>.
                        </div>
                    @endforelse
                </div>

                @if(count($lista))
                    <x-filament::button wire:click="descargar" size="lg" icon="heroicon-m-arrow-down-tray"
                        class="mt-4 w-full justify-center">
                        Descargar Excel ({{ count($lista) }})
                    </x-filament::button>

                    <p class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400">
                        Subí este archivo en Sistrack → <span class="font-semibold">Importación masiva</span>
                        para crear todas las guías de una vez.
                    </p>
                @endif
            </div>
        </div>

        {{-- ─────────── PDF DE GUÍAS ─────────── --}}
        <div @class(['hidden' => $seccion !== 'pdf'])>
            @include('filament.partials.importar-pdf')
        </div>

        {{-- ─────────── FOTOS ─────────── --}}
        <div @class(['hidden' => $seccion !== 'fotos'])>
            @include('filament.partials.subir-fotos')
        </div>

    </div>
</x-filament-panels::page>

{{-- Si falta un campo, lo resalta y salta a él (útil en el teléfono) --}}
@push('scripts')
    <script>
    // Copia el mensaje del cliente leyéndolo del atributo en el momento del clic.
    // Así siempre sale la versión corregida más reciente, no la que había cuando
    // se dibujó la fila. Solo se marca como enviado si de verdad se copió.
    window.bcCopiarGuia = async function (boton, id, wire) {
        const texto = boton.dataset.msg || '';
        if (!texto) return;

        let copiado = false;
        try {
            await navigator.clipboard.writeText(texto);
            copiado = true;
        } catch (e) {
            // Respaldo para navegadores viejos o conexiones sin candado.
            const ta = document.createElement('textarea');
            ta.value = texto;
            ta.style.cssText = 'position:fixed;top:-1000px;opacity:0';
            document.body.appendChild(ta);
            ta.select();
            try { copiado = document.execCommand('copy'); } catch (err) {}
            ta.remove();
        }

        if (!copiado) {
            alert('No se pudo copiar. Tocá y mantené sobre el mensaje para copiarlo a mano.');
            return;
        }

        if (id && wire) wire.marcarEnviado(id);
    };
    </script>

    {{-- Lector del PDF de etiquetas de Sistrack --}}
    <script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
    <script>
    (() => {
        const input = document.getElementById('pdf-input');
        if (!input) return;

        const caja   = document.getElementById('pdf-progreso');
        const barra  = document.getElementById('pdf-barra');
        const texto  = document.getElementById('pdf-texto');
        const num    = document.getElementById('pdf-num');
        const salida = document.getElementById('pdf-resultado');
        const token  = document.querySelector('meta[name="csrf-token"]')?.content;

        // Saca los datos de una etiqueta a partir de su texto.
        function leerEtiqueta(t) {
            const g = t.match(/Orden\s*#\s*(\d+)/i);
            if (!g) return null;

            const para = t.match(/Para:\s*(.+)/i);
            let telefono = null, nombre = null, direccion = null;

            if (para) {
                const linea = para[1].trim();
                const m = linea.match(/^(?:\+?503[\s-]*)?([267]\d{3})[\s.-]?(\d{4})\s*(.*)$/);
                if (m) { telefono = m[1] + ' ' + m[2]; nombre = m[3].trim(); }
                else   { nombre = linea; }
            }

            const bloque = t.match(/Para:\s*.+\n([\s\S]*?)\nContenido del paquete/i);
            if (bloque) direccion = bloque[1].replace(/\s+/g, ' ').trim();

            let municipio = null, departamento = null;
            const md = t.match(/^\s*([A-ZÁÉÍÓÚÑ][A-Za-zÁÉÍÓÚÑáéíóúñ .'-]{2,40})\s*\|\s*([A-Za-zÁÉÍÓÚÑáéíóúñ .'-]{3,30})\s*$/m);
            if (md) { municipio = md[1].trim(); departamento = md[2].trim(); }

            // Qué lleva el paquete y cuánto se cobra al entregar.
            let contenido = null, cobrar = null;
            const c = t.match(/Contenido del paquete[^:]*:\s*(.+)/i);
            if (c) contenido = c[1].replace(/\s+/g, ' ').trim();

            const co = t.match(/COBRAR AL ENTREGAR:\s*\$?\s*([\d.,]+)/i);
            if (co) cobrar = parseFloat(co[1].replace(/,/g, ''));

            return { guia: g[1], nombre, telefono, direccion, municipio, departamento, contenido, cobrar };
        }

        input.addEventListener('change', async () => {
            const file = input.files[0];
            if (!file) return;

            caja.classList.remove('hidden');
            salida.innerHTML = '';
            barra.style.width = '0%';
            texto.textContent = 'Abriendo el PDF…';
            num.textContent = '';

            try {
                pdfjsLib.GlobalWorkerOptions.workerSrc =
                    'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';

                const datos = new Uint8Array(await file.arrayBuffer());
                const pdf   = await pdfjsLib.getDocument({ data: datos }).promise;

                const guias = [];
                for (let i = 1; i <= pdf.numPages; i++) {
                    const pagina = await pdf.getPage(i);
                    const cont   = await pagina.getTextContent();

                    let t = '', ultimaY = null;
                    for (const it of cont.items) {
                        const y = Math.round(it.transform[5]);
                        if (ultimaY !== null && Math.abs(y - ultimaY) > 2) t += '\n';
                        t += it.str + ' ';
                        ultimaY = y;
                    }

                    const e = leerEtiqueta(t);
                    if (e) guias.push(e);

                    barra.style.width = Math.round((i / pdf.numPages) * 100) + '%';
                    num.textContent = i + ' de ' + pdf.numPages;
                    texto.textContent = 'Leyendo etiqueta ' + i + '…';
                }

                if (!guias.length) {
                    salida.innerHTML = '<div class="rounded-xl border border-danger-300 bg-danger-50 p-4 text-sm text-danger-700 dark:border-danger-500/50 dark:bg-danger-500/10 dark:text-danger-400">No se encontraron guías en ese PDF. ¿Es el archivo de etiquetas de Sistrack?</div>';
                    texto.textContent = 'Sin resultados';
                    input.value = '';
                    return;
                }

                texto.textContent = 'Guardando ' + guias.length + ' guías…';

                const res = await fetch('{{ route('guias.pdf') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ guias, lote: new Date().toISOString().slice(0, 19).replace('T', ' ') }),
                });
                const r = await res.json();

                if (r.ok) {
                    texto.textContent = '¡Listo! ✅';
                    const filas = guias.slice(0, 60).map(g =>
                        '<tr class="border-t border-gray-100 dark:border-white/5">'
                        + '<td class="py-1.5 pr-3 font-semibold">' + g.guia + '</td>'
                        + '<td class="py-1.5 pr-3 text-gray-500 dark:text-gray-400">' + (g.telefono || '—') + '</td>'
                        + '<td class="py-1.5 text-gray-500 dark:text-gray-400">' + (g.nombre || '—').slice(0, 26) + '</td>'
                        + '</tr>').join('');

                    salida.innerHTML =
                      '<div class="rounded-xl border border-success-300 bg-success-50 p-4 dark:border-success-500/50 dark:bg-success-500/10">'
                      + '<div class="text-sm font-bold text-success-700 dark:text-success-400">✅ ' + r.nuevas + ' guías nuevas · ' + r.actualizadas + ' ya estaban</div>'
                      + '<div class="mt-1 text-xs text-success-700/80 dark:text-success-400/80">Sus clientes quedaron guardados en la libreta.</div>'
                      + '<a href="{{ \App\Filament\Resources\GuiaFotoResource::getUrl() }}" class="mt-3 inline-block text-sm font-bold text-primary-600 hover:underline">Ver las guías y enviar los mensajes →</a>'
                      + '</div>'
                      + '<div class="mt-3 overflow-x-auto rounded-xl border border-gray-200 p-3 dark:border-white/10"><table class="w-full text-xs"><tbody>' + filas + '</tbody></table></div>';
                } else {
                    salida.innerHTML = '<div class="rounded-xl border border-danger-300 bg-danger-50 p-4 text-sm text-danger-700">No se pudieron guardar.</div>';
                }
            } catch (e) {
                texto.textContent = 'Error';
                salida.innerHTML = '<div class="rounded-xl border border-danger-300 bg-danger-50 p-4 text-sm text-danger-700">No se pudo leer el PDF: ' + e.message + '</div>';
            }

            input.value = '';
        });
    })();
    </script>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('enfocar-campo', (e) => {
                const campo = (e && (e.campo ?? e[0]?.campo)) || null;
                if (!campo) return;
                setTimeout(() => {
                    const el = document.querySelector('[wire\\:model="data.' + campo + '"], [id$="data.' + campo + '"]')
                        || document.querySelector('[name="data.' + campo + '"]');
                    if (!el) return;
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    try { el.focus({ preventScroll: true }); } catch (err) {}
                    const caja = el.closest('.fi-fo-field-wrp') || el;
                    caja.style.transition = 'box-shadow .2s';
                    caja.style.boxShadow = '0 0 0 3px rgba(239,68,68,.45)';
                    setTimeout(() => { caja.style.boxShadow = ''; }, 1800);
                }, 60);
            });

            // Al tocar el lápiz de una guía, sube al formulario para no buscarlo.
            Livewire.on('ir-al-formulario', () => {
                setTimeout(() => {
                    const f = document.getElementById('bc-formulario');
                    if (f) f.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 120);
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <script>
    (() => {
        const input = document.getElementById('fotos-input');
        const cont  = document.getElementById('resultados');
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        let ok = 0, fallo = 0;

        // Carga la imagen a un canvas (reducida) para leer el QR.
        const aCanvas = (img, maxLado) => {
            const escala = Math.min(1, maxLado / Math.max(img.width, img.height));
            const c = document.createElement('canvas');
            c.width = Math.round(img.width * escala);
            c.height = Math.round(img.height * escala);
            c.getContext('2d', { willReadFrequently: true }).drawImage(img, 0, 0, c.width, c.height);
            return c;
        };

        const recorte = (img, x, y, w, h, escala) => {
            const c = document.createElement('canvas');
            c.width = Math.round(w * escala); c.height = Math.round(h * escala);
            c.getContext('2d', { willReadFrequently: true })
             .drawImage(img, x, y, w, h, 0, 0, c.width, c.height);
            return c;
        };

        const leerCanvas = (c) => {
            const ctx = c.getContext('2d', { willReadFrequently: true });
            const d = ctx.getImageData(0, 0, c.width, c.height);
            const r = jsQR(d.data, c.width, c.height, { inversionAttempts: 'attemptBoth' });
            return r ? r.data : null;
        };

        // Busca el QR: primero con la API nativa, luego con jsQR probando la imagen
        // completa y por zonas (el QR suele ir abajo a la derecha).
        async function leerQR(file, img) {
            try {
                if ('BarcodeDetector' in window) {
                    const det = new BarcodeDetector({ formats: ['qr_code'] });
                    const res = await det.detect(await createImageBitmap(file));
                    if (res && res.length) return res[0].rawValue;
                }
            } catch (e) {}

            // jsQR acierta más con imágenes CHICAS: se prueba de menor a mayor.
            for (const lado of [640, 520, 760, 440, 900, 1100]) {
                const t = leerCanvas(aCanvas(img, lado));
                if (t) return t;
            }

            // Respaldo: por zonas (mosaico con traslape), cada una en tamaño chico.
            const W = img.width, H = img.height;
            const tw = W * 0.55, th = H * 0.45;
            for (const fy of [0, 0.22, 0.44, 0.55]) {
                for (const fx of [0, 0.22, 0.45]) {
                    const x = W * fx, y = H * fy;
                    const w = Math.min(tw, W - x), h = Math.min(th, H - y);
                    if (w < 40 || h < 40) continue;
                    const esc = Math.min(1, 620 / Math.max(w, h));
                    const t = leerCanvas(recorte(img, x, y, w, h, esc));
                    if (t) return t;
                }
            }
            return null;
        }

        // Lee el texto de la parte de arriba de la etiqueta (donde va "Para: nombre teléfono")
        // para sacar el número del cliente. Se hace una sola vez y en zona recortada, para que sea rápido.
        let lector = null;
        async function leerDatosCliente(img) {
            try {
                if (typeof Tesseract === 'undefined') return {};
                if (!lector) lector = await Tesseract.createWorker('eng');

                const c = recorte(img, img.width * 0.03, img.height * 0.10, img.width * 0.95, img.height * 0.25,
                                  Math.min(2, 1700 / (img.width * 0.95)));
                const { data } = await lector.recognize(c);
                const t = (data.text || '').replace(/\s+/g, ' ');

                const tel = t.match(/(?:^|[^\d])([267]\d{3})[\s.-]?(\d{4})(?![\d])/);
                const nom = t.match(/Para:?\s*([A-Za-zÀ-ÿ' ]{3,60})/i);

                return {
                    telefono: tel ? (tel[1] + ' ' + tel[2]) : null,
                    nombre: nom ? nom[1].trim() : null,
                };
            } catch (e) {
                return {};
            }
        }

        const sacarGuia = (txt) => {
            if (!txt) return null;
            const m = String(txt).match(/(\d{5,10})\s*$/) || String(txt).match(/(\d{5,10})/);
            return m ? m[1] : null;
        };

        const tarjeta = (nombre, src) => {
            const d = document.createElement('div');
            d.style.cssText = 'display:flex;gap:12px;align-items:center;border:1px solid #e5e7eb;border-radius:12px;padding:10px;background:#fff';
            d.innerHTML = `<a href="${src}" target="_blank" rel="noopener" title="Abrir la foto en grande">
                  <img src="${src}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;flex:none;cursor:zoom-in">
                </a>
                <div style="flex:1;min-width:0">
                  <div style="font-size:12.5px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${nombre}</div>
                  <div class="estado" style="font-weight:700;font-size:14px;margin-top:3px">Leyendo QR…</div>
                  <div class="acciones" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:7px"></div>
                </div>`;
            cont.prepend(d);
            return d;
        };

        let loteActual = null;

        // ---- Barra de progreso ----
        const caja    = document.getElementById('progreso-caja');
        const barra   = document.getElementById('progreso-barra');
        const pTexto  = document.getElementById('progreso-texto');
        const pNum    = document.getElementById('progreso-num');
        const pDet    = document.getElementById('progreso-detalle');

        function progreso(hechas, total, texto, detalle) {
            caja.style.display = 'block';
            barra.style.width = total ? Math.round((hechas / total) * 100) + '%' : '0%';
            pNum.textContent = hechas + ' de ' + total;
            if (texto)   pTexto.textContent = texto;
            if (detalle !== undefined) pDet.innerHTML = detalle;
        }

        input.addEventListener('change', async () => {
            const files = [...input.files];
            if (!files.length) return;
            ok = 0; fallo = 0;
            cont.innerHTML = '';
            // Todas las fotos de esta tanda comparten el mismo lote.
            loteActual = new Date().toISOString().slice(0, 19).replace('T', ' ');
            progreso(0, files.length, 'Procesando fotos…', '');

            let i = 0;
            for (const file of files) {
                i++;
                progreso(i - 1, files.length, 'Leyendo foto ' + i + '…');

                const url = URL.createObjectURL(file);
                const img = new Image();
                await new Promise(r => { img.onload = r; img.onerror = r; img.src = url; });

                const texto = await leerQR(file, img);
                const guia = sacarGuia(texto);

                if (!guia) {
                    // Solo las que fallan se muestran, para escribir la guía a mano.
                    fallo++;
                    const card = tarjeta(file.name, url);
                    const est = card.querySelector('.estado');
                    const acc = card.querySelector('.acciones');
                    est.innerHTML = '<span style="color:#dc2626">✕ No se pudo leer el QR</span>';

                    const ver = document.createElement('a');
                    ver.href = url; ver.target = '_blank'; ver.rel = 'noopener';
                    ver.textContent = '🔍 Ver foto grande';
                    ver.style.cssText = 'background:#f1f5f9;border:1px solid #e5e7eb;border-radius:8px;padding:7px 12px;font-size:13px;font-weight:600;color:#334155;text-decoration:none';
                    acc.appendChild(ver);

                    const fila = document.createElement('div');
                    fila.style.cssText = 'display:flex;gap:6px;width:100%;margin-top:6px';
                    const inp = document.createElement('input');
                    inp.placeholder = 'Escribí la guía que ves en la foto';
                    inp.inputMode = 'numeric';
                    inp.style.cssText = 'flex:1;min-width:0;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:15px';
                    const btn = document.createElement('button');
                    btn.type = 'button'; btn.textContent = 'Subir';
                    btn.style.cssText = 'background:#2563eb;color:#fff;border:none;border-radius:8px;padding:8px 14px;font-weight:700;font-size:13.5px;cursor:pointer';
                    const mandar = async () => {
                        const v = inp.value.trim();
                        if (!v) return;
                        inp.disabled = true; btn.disabled = true; fila.remove();
                        const datos = await leerDatosCliente(img);
                        await subir(file, v, est, acc, datos, img);
                        try { if (window.Livewire) Livewire.dispatch('$refresh'); } catch (e) {}
                    };
                    btn.addEventListener('click', mandar);
                    inp.addEventListener('keydown', e => { if (e.key === 'Enter') mandar(); });
                    fila.append(inp, btn);
                    acc.after(fila);

                    progreso(i, files.length, 'Procesando fotos…',
                        '✅ ' + ok + ' guardadas · <b style="color:#dc2626">✕ ' + fallo + ' sin leer</b>');
                    continue;
                }

                progreso(i - 1, files.length, 'Guía ' + guia + ' · leyendo datos…');
                const datos = await leerDatosCliente(img);
                const r = await subir(file, guia, null, null, datos, img);

                // Si falló, ANTES no se veía nada: la tarjeta solo se creaba
                // cuando el QR no se leía. Ahora la falla se muestra y se puede
                // reintentar sin volver a elegir la foto.
                if (r && !r.ok) {
                    const card = tarjeta(file.name + ' · guía ' + guia, url);
                    const est2 = card.querySelector('.estado');
                    const acc2 = card.querySelector('.acciones');
                    est2.innerHTML = '<span style="color:#dc2626">✕ ' + (r.error || 'No se pudo subir') + '</span>';

                    const rb = document.createElement('button');
                    rb.type = 'button'; rb.textContent = '🔄 Reintentar';
                    rb.style.cssText = 'background:#2563eb;color:#fff;border:none;border-radius:8px;padding:8px 14px;font-weight:700;font-size:13px;cursor:pointer';
                    rb.addEventListener('click', async () => {
                        rb.disabled = true;
                        est2.textContent = 'Reintentando…';
                        const r2 = await subir(file, guia, est2, acc2, datos, img);
                        rb.disabled = false;
                        if (r2 && r2.ok) rb.remove();
                    });
                    acc2.appendChild(rb);
                }

                progreso(i, files.length, 'Procesando fotos…',
                    '✅ ' + ok + ' guardadas' + (fallo ? ' · <b style="color:#dc2626">✕ ' + fallo + ' sin subir</b>' : ''));
            }

            input.value = '';
            progreso(files.length, files.length,
                fallo ? 'Listo (faltan ' + fallo + ' por escribir a mano)' : '¡Listo! Todas guardadas ✅',
                '✅ ' + ok + ' guardadas' + (fallo ? ' · <b style="color:#dc2626">✕ ' + fallo + ' sin leer</b>' : '') +
                ' &nbsp;·&nbsp; <a href="{{ \App\Filament\Resources\GuiaFotoResource::getUrl() }}" style="color:#2563eb;font-weight:700">Ver las guías →</a>');

            try { if (window.Livewire) Livewire.dispatch('$refresh'); } catch (e) {}
        });

        // Botones de copiar de la lista guardada (funcionan aunque Livewire redibuje).
        document.addEventListener('click', async (e) => {
            const b = e.target.closest('.js-copiar');
            if (!b) return;
            const txt = b.dataset.copiar || '';
            try {
                await navigator.clipboard.writeText(txt);
            } catch (err) {
                const ta = document.createElement('textarea');
                ta.value = txt; document.body.appendChild(ta); ta.select();
                document.execCommand('copy'); ta.remove();
            }
            const antes = b.textContent;
            b.textContent = '✓ Copiado';
            setTimeout(() => { b.textContent = antes; }, 1300);
        });

        // ---- Control de "por cuál cliente voy" ----
        let enviados = 0, totalSubidas = 0;

        function actualizarContador() {
            if (!totalSubidas || !pDet) return;
            pDet.innerHTML = `📨 <b>${enviados} de ${totalSubidas}</b> enviados`
                + (fallo ? ` · <span style="color:#dc2626">✕ ${fallo} sin leer</span>` : '');
        }

        // Marca la tarjeta en la que estás trabajando ahora.
        function marcarActiva(card) {
            document.querySelectorAll('.tarjeta-activa').forEach(c => {
                c.classList.remove('tarjeta-activa');
                if (!c.classList.contains('tarjeta-lista')) c.style.opacity = '1';
                c.style.boxShadow = 'none';
                c.style.borderColor = '#e5e7eb';
            });
            if (card.classList.contains('tarjeta-lista')) return;
            card.classList.add('tarjeta-activa');
            card.style.borderColor = '#2563eb';
            card.style.boxShadow = '0 0 0 3px rgba(37,99,235,.20)';
            card.style.opacity = '1';
        }

        // Marca la tarjeta como ya enviada (se copió el enlace = último paso).
        function marcarLista(card) {
            if (card.classList.contains('tarjeta-lista')) return;
            card.classList.add('tarjeta-lista');
            card.classList.remove('tarjeta-activa');
            card.style.borderColor = '#86efac';
            card.style.background = '#f0fdf4';
            card.style.boxShadow = 'none';
            card.style.opacity = '.6';

            const est = card.querySelector('.estado');
            if (est && !est.querySelector('.sello')) {
                const s = document.createElement('span');
                s.className = 'sello';
                s.textContent = ' ✓ Enviado';
                s.style.cssText = 'color:#059669;font-size:12.5px';
                est.appendChild(s);
            }
            enviados++;
            actualizarContador();
        }

        // Botón que copia un texto al portapapeles y avisa al tocarlo.
        // 'marcaFinal' = al copiarlo, la tarjeta queda marcada como enviada.
        function botonCopiar(etiqueta, texto, color, card, marcaFinal = false) {
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = etiqueta;
            b.style.cssText = `background:${color};color:#fff;border:none;border-radius:8px;padding:8px 12px;font-weight:700;font-size:13px;cursor:pointer`;
            b.addEventListener('click', async () => {
                const original = b.textContent;
                try {
                    await navigator.clipboard.writeText(texto);
                } catch (e) {
                    const ta = document.createElement('textarea');
                    ta.value = texto; document.body.appendChild(ta); ta.select();
                    document.execCommand('copy'); ta.remove();
                }
                b.textContent = '✓ Copiado';
                setTimeout(() => { b.textContent = original; }, 1400);

                if (card) marcaFinal ? marcarLista(card) : marcarActiva(card);
            });
            return b;
        }

        // Botón verde que abre el WhatsApp del cliente con el mensaje ya escrito.
        function agregarWhatsapp(acc, datos, guia) {
            if (!acc || acc.querySelector('.wa-btn')) return;

            const d = datos.telefono.replace(/\D/g, '');
            const enlace = location.origin + '/rastreo?guia=' + guia;
            const msg = '¡Sigue tu pedido, Baby-Confort!\n\nGuía ' + guia +
                        '\nRastréalo aquí: ' + enlace +
                        '\n\nAhí podés ver la foto de tu paquete. ¡Gracias por tu preferencia!';

            const card = acc.closest('div[style*="border"]');

            const a = document.createElement('a');
            a.className = 'wa-btn';
            a.href = 'https://wa.me/503' + d + '?text=' + encodeURIComponent(msg);
            a.target = '_blank'; a.rel = 'noopener';
            a.textContent = '💬 WhatsApp ' + datos.telefono;
            a.style.cssText = 'background:#25D366;color:#fff;border-radius:8px;padding:8px 12px;font-weight:700;font-size:13px;text-decoration:none';
            // Mandar por WhatsApp también cuenta como enviado.
            a.addEventListener('click', () => { if (card) marcarLista(card); });
            acc.prepend(a);

            acc.appendChild(botonCopiar('📞 ' + datos.telefono, datos.telefono, '#059669', card));

            if (datos.nombre) {
                const n = document.createElement('div');
                n.textContent = '👤 ' + datos.nombre;
                n.style.cssText = 'width:100%;font-size:12.5px;color:#6b7280;margin-top:2px';
                acc.appendChild(n);
            }
        }

        // Achica la foto antes de mandarla. Las cámaras de teléfono sacan
        // imágenes de 4 a 10 MB y el servidor las rechaza en silencio por
        // pasarse del límite de subida de PHP. Con el lado largo en 1600 px
        // la etiqueta se sigue leyendo perfecto y pesa unos 300 KB: sube
        // rápido con datos móviles y ocupa mucho menos disco.
        // Además de achicar, SIEMPRE reescribe a JPG si el formato no es
        // JPG ni PNG: los iPhone guardan en HEIC y el servidor no lo acepta.
        function achicar(file, img) {
            return new Promise((listo) => {
                try {
                    if (!img || !img.width || !img.height) return listo(file);

                    const yaEsBuena = file.type === 'image/jpeg' || file.type === 'image/png';
                    if (yaEsBuena && file.size <= 900 * 1024) return listo(file);

                    const c = aCanvas(img, 1600);
                    if (!c.width || !c.height) return listo(file);

                    c.toBlob((blob) => {
                        if (!blob || blob.size === 0) return listo(file);
                        listo((yaEsBuena && blob.size >= file.size) ? file : blob);
                    }, 'image/jpeg', 0.82);
                } catch (e) {
                    listo(file);
                }
            });
        }

        async function subir(file, guia, est, acc, datos, img) {
            if (est) est.textContent = 'Subiendo guía ' + guia + '…';

            const liviana = await achicar(file, img);

            const fd = new FormData();
            fd.append('guia', guia);
            fd.append('foto', liviana, 'guia-' + guia + '.jpg');
            if (datos && datos.nombre)   fd.append('nombre', datos.nombre);
            if (datos && datos.telefono) fd.append('telefono', datos.telefono);
            if (loteActual)              fd.append('lote', loteActual);
            try {
                const res = await fetch('{{ route('fotos.subir') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: fd,
                });

                // El servidor no siempre responde JSON: si la foto se pasa del
                // límite de PHP devuelve una página de error, y antes eso se
                // veía como "error de conexión", que despistaba.
                const crudo = await res.text();
                let data = null;
                try { data = JSON.parse(crudo); } catch (e) {}

                if (!data) {
                    fallo++;
                    const motivo = res.status === 413 ? 'La foto pesa demasiado para el servidor'
                                 : res.status === 419 ? 'La sesión venció: recargá la página'
                                 : 'El servidor respondió mal (' + res.status + ')';
                    if (est) est.innerHTML = '<span style="color:#dc2626">✕ ' + motivo + '</span>';
                    return { ok: false, error: motivo };
                }

                if (data.ok) {
                    ok++;
                    if (est) est.innerHTML = `<span style="color:#059669">✓ Guía ${data.guia}</span>`;

                    if (acc) {
                        const card = acc.closest('div[style*="border"]');
                        totalSubidas++; actualizarContador();

                        acc.innerHTML = '';
                        acc.appendChild(botonCopiar('📋 Guía', data.guia, '#2563eb', card));
                        if (data.telefono) {
                            acc.appendChild(botonCopiar('📞 ' + data.telefono, data.telefono, '#059669', card));
                        }
                        // Copiar el enlace es el último paso: marca la tarjeta como enviada.
                        acc.appendChild(botonCopiar('🔗 Enlace', data.rastreo, '#7c3aed', card, true));

                        const ver = document.createElement('a');
                        ver.href = data.rastreo; ver.target = '_blank'; ver.rel = 'noopener';
                        ver.textContent = 'Abrir ↗';
                        ver.style.cssText = 'background:#f1f5f9;border:1px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:13px;font-weight:600;color:#334155;text-decoration:none';
                        acc.appendChild(ver);
                    }
                    return { ok: true };
                } else {
                    fallo++;
                    // Laravel manda los errores de validación en "errors".
                    const motivo = data.error
                        || (data.errors && Object.values(data.errors)[0] && Object.values(data.errors)[0][0])
                        || data.message || 'Error al subir';
                    if (est) est.innerHTML = '<span style="color:#dc2626">✕ ' + motivo + '</span>';
                    return { ok: false, error: motivo };
                }
            } catch (e) {
                fallo++;
                if (est) est.innerHTML = '<span style="color:#dc2626">✕ Error de conexión</span>';
                return { ok: false, error: 'Error de conexión' };
            }
        }
    })();
    </script>
@endpush

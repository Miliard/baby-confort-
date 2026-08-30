<x-filament-panels::page>

<style>
    .bc-tp{display:grid;grid-template-columns:280px 1fr;gap:18px;align-items:start}
    @media(max-width:1100px){ .bc-tp{grid-template-columns:1fr} }

    .bc-panel{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px}
    html.dark .bc-panel{background:#16202f;border-color:rgba(255,255,255,.10)}
    .bc-panel h3{margin:0 0 10px;font-size:13px;font-weight:800;text-transform:uppercase;
                 letter-spacing:.5px;color:#64748b}
    .bc-chk{display:flex;gap:9px;align-items:flex-start;padding:6px 4px;font-size:13.5px;
            line-height:1.35;cursor:pointer;border-radius:8px}
    .bc-chk:hover{background:rgba(120,140,170,.10)}
    .bc-chk input{margin-top:2px;flex:none;width:16px;height:16px}
    .bc-sep{height:1px;background:#e5e7eb;margin:12px 0}
    html.dark .bc-sep{background:rgba(255,255,255,.10)}
    .bc-boton{width:100%;border:none;border-radius:10px;padding:12px;font-size:14.5px;
              font-weight:800;cursor:pointer;background:#2f7fd1;color:#fff}
    .bc-pista{font-size:11.5px;color:#94a3b8;margin-top:8px;line-height:1.5}

    /* ── La tarjeta que se convierte en imagen: colores fijos, nunca modo oscuro ── */
    #bc-tarjeta{background:#fff;border-radius:18px;overflow:hidden;color:#16202f;
                font-family:'Segoe UI',system-ui,-apple-system,sans-serif}
    #bc-tarjeta .cab{background:#2f7fd1;color:#fff;padding:24px 28px;display:flex;
                     justify-content:space-between;align-items:center;gap:20px}
    #bc-tarjeta .cab h1{margin:0;font-size:27px;letter-spacing:-.4px}
    #bc-tarjeta .cab .sub{font-size:14px;opacity:.92;margin-top:3px}
    #bc-tarjeta .cab .marca{text-align:right;font-size:14px;line-height:1.5;opacity:.95}
    #bc-tarjeta table{width:100%;border-collapse:collapse}
    #bc-tarjeta thead th{background:#f1f5f9;color:#5b6b80;font-size:11px;text-transform:uppercase;
                         letter-spacing:.6px;text-align:left;padding:10px 28px}
    #bc-tarjeta thead th.der{text-align:right}
    #bc-tarjeta tr.grupo td{background:#e8f1fa;color:#1c4f80;font-weight:800;font-size:15px;padding:11px 28px}
    #bc-tarjeta tbody td{padding:10px 28px;font-size:15px;border-bottom:1px solid #eef2f6;color:#16202f}
    #bc-tarjeta td.der{text-align:right}
    #bc-tarjeta td.peso{color:#64748b;font-size:13.5px}
    #bc-tarjeta td.precio{font-weight:800;color:#1c7a4d;font-size:17px;white-space:nowrap}
    #bc-tarjeta td.cu{color:#7a8899;font-size:13px;white-space:nowrap}
    #bc-tarjeta .combo{display:inline-block;background:#fdf0d8;color:#8a5a06;font-size:11.5px;
                       font-weight:700;border-radius:6px;padding:2px 7px;margin-left:7px}
    #bc-tarjeta .agot{display:inline-block;background:#fdeaea;color:#9c2c2c;font-size:11.5px;
                      font-weight:700;border-radius:6px;padding:2px 7px;margin-left:7px}
    #bc-tarjeta .pie{padding:18px 28px 24px;background:#f8fafc;font-size:14px;color:#475569;line-height:1.6}
    #bc-tarjeta .pie b{color:#16202f}
    #bc-tarjeta .pie .fecha{margin-top:6px;font-size:12.5px;color:#94a3b8}
    #bc-tarjeta .vacio{padding:40px 28px;text-align:center;color:#94a3b8;font-size:15px}
    #bc-tarjeta td.constock{color:#1c7a4d;font-weight:700;white-space:nowrap}
    #bc-tarjeta td.sinstock{color:#9c2c2c;font-weight:700;white-space:nowrap}

    /* Al imprimir (o guardar como PDF) sale SOLO la tarjeta: nada del panel de
       la izquierda ni del menú del admin, que ahí no pintan nada. */
    @media print{
        body *{visibility:hidden}
        #bc-tarjeta, #bc-tarjeta *{visibility:visible}
        #bc-tarjeta{position:absolute;left:0;top:0;width:100%;border-radius:0}
        #bc-tarjeta thead{display:table-header-group}   /* la cabecera se repite en cada hoja */
        #bc-tarjeta tr{break-inside:avoid}
        @page{margin:12mm 10mm}
    }
</style>

<div class="bc-tp">

    {{-- IZQUIERDA: qué entra en la tabla --}}
    <div>
        <div class="bc-panel">
            <h3>Qué incluir</h3>

            @forelse($this->catalogo() as $p)
                <label class="bc-chk">
                    <input type="checkbox" wire:model.live="elegidos" value="{{ $p->id }}">
                    <span>{{ \App\Filament\Pages\TablaPrecios::limpiarNombre($p->name) }}</span>
                </label>
            @empty
                <p class="bc-pista">No hay productos activos con tallas.</p>
            @endforelse

            <div class="bc-sep"></div>

            <label class="bc-chk">
                <input type="checkbox" wire:model.live="porUnidad">
                <span>Mostrar precio por unidad</span>
            </label>
            <label class="bc-chk">
                <input type="checkbox" wire:model.live="marcarAgotados">
                <span>Mostrar existencias <b>(uso interno)</b></span>
            </label>

            <div class="bc-sep"></div>

            <button type="button" class="bc-boton" onclick="bcBajarTabla(this)">📷 Imagen</button>
            <button type="button" class="bc-boton" style="background:#5b6b80;margin-top:6px"
                    onclick="window.print()">🖨️ PDF</button>
            <p class="bc-pista">
                <b>Imagen:</b> un PNG listo para mandar por WhatsApp, se abre de un toque.<br>
                <b>PDF:</b> elegí “Guardar como PDF” en Destino. Sale solo la tabla.<br>
                Las tallas agotadas aparecen igual, con su detalle completo.
            </p>
        </div>
    </div>

    {{-- DERECHA: la tabla tal como va a salir --}}
    <div id="bc-tarjeta">
        <div class="cab">
            <div>
                <h1>Lista de precios</h1>
                <div class="sub">Presentaciones, peso, cantidad y precio</div>
            </div>
            <div class="marca">
                <b>Baby-Confort</b><br>
                Entrega a domicilio<br>
                en todo El Salvador
            </div>
        </div>

        @php $grupos = $this->grupos(); @endphp

        @if(empty($grupos))
            <div class="vacio">Marcá al menos un producto de la izquierda.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Talla</th>
                        <th>Peso</th>
                        <th class="der">Unidades</th>
                        <th class="der">Precio</th>
                        @if($porUnidad)<th class="der">Por unidad</th>@endif
                        @if($marcarAgotados)<th class="der">Existencia</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($grupos as $g)
                        <tr class="grupo">
                            <td colspan="{{ 4 + ($porUnidad ? 1 : 0) + ($marcarAgotados ? 1 : 0) }}">{{ $g['producto'] }}</td>
                        </tr>
                        @foreach($g['filas'] as $f)
                            <tr>
                                <td>
                                    {{ $f['talla'] }}
                                    @if($f['combo'])<span class="combo">Combo {{ $f['combo'] }}</span>@endif
                                    @if($marcarAgotados && $f['agotado'])<span class="agot">sin existencia</span>@endif
                                </td>
                                <td class="peso">{{ $f['peso'] ?: '—' }}</td>
                                <td class="der">{{ $f['uds'] > 0 ? $f['uds'] : '—' }}</td>
                                <td class="der precio">${{ number_format($f['precio'], 2) }}</td>
                                @if($porUnidad)
                                    <td class="der cu">{{ $f['unidad'] ? '$' . number_format($f['unidad'], 2) : '—' }}</td>
                                @endif
                                @if($marcarAgotados)
                                    <td class="der {{ $f['agotado'] ? 'sinstock' : 'constock' }}">
                                        {{ $f['agotado'] ? 'Agotado' : $f['existencia'] . ' paq.' }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="pie">
            <b>¿Cómo pedir?</b> Escribinos por WhatsApp con la talla y la cantidad, y coordinamos la entrega.
            {{-- Fecha en números: el idioma del sistema está en inglés y
                 translatedFormat sacaría "August" en vez de "agosto". --}}
            <div class="fecha">Precios vigentes al {{ now()->format('d/m/Y') }}</div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
// Convierte la tarjeta en PNG. Se hace en el navegador a propósito: el
// servidor no tiene la librería de imágenes (GD) instalada.
window.bcBajarTabla = function (boton) {
    var tarjeta = document.getElementById('bc-tarjeta');
    if (!tarjeta || typeof html2canvas === 'undefined') return;

    var original = boton.textContent;
    boton.textContent = 'Generando…';
    boton.disabled = true;

    html2canvas(tarjeta, { scale: 2, backgroundColor: '#ffffff', useCORS: true })
        .then(function (lienzo) {
            var a = document.createElement('a');
            a.download = 'precios-baby-confort.png';
            a.href = lienzo.toDataURL('image/png');
            a.click();
        })
        .catch(function () {
            alert('No se pudo generar la imagen. Probá recargando la página.');
        })
        .finally(function () {
            boton.textContent = original;
            boton.disabled = false;
        });
};
</script>
@endpush

</x-filament-panels::page>

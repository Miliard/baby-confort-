<x-filament-panels::page>
    <div style="max-width:620px;margin:0 auto;width:100%">
        <p style="margin:-8px 0 14px;color:#6b7280;font-size:13.5px;line-height:1.5">
            Subí las fotos de las etiquetas. Se lee el <b>código QR</b> de cada una para saber a qué
            guía pertenece, y la foto queda visible para el cliente en su enlace de seguimiento.
        </p>

        <label for="fotos-input"
            style="display:block;border:2px dashed #cbd5e1;border-radius:14px;padding:26px 16px;text-align:center;cursor:pointer;background:#f8fafc">
            <div style="font-size:34px;line-height:1">📷</div>
            <div style="font-weight:700;margin-top:6px;font-size:15px">Elegir fotos de las etiquetas</div>
            <div style="color:#6b7280;font-size:13px;margin-top:2px">Podés elegir varias a la vez</div>
        </label>
        <input id="fotos-input" type="file" accept="image/*" multiple style="display:none">

        <div id="resumen" style="margin-top:14px;font-size:14px;font-weight:700;display:none"></div>
        <div id="resultados" style="margin-top:12px;display:flex;flex-direction:column;gap:10px"></div>

        {{-- Guías ya guardadas: siguen aquí aunque cierres la pantalla --}}
        <div style="margin-top:26px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <b style="font-size:15px">📦 Guías guardadas ({{ $this->guardadas->count() }})</b>
                <button type="button" wire:click="$refresh" style="background:none;border:none;color:#2563eb;font-weight:600;font-size:13px;cursor:pointer">↻ Actualizar</button>
            </div>

            @if($this->guardadas->count())
                <div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff">
                    <table style="width:100%;border-collapse:collapse;font-size:13.5px">
                        <thead>
                            <tr style="background:#f8fafc;text-align:left">
                                <th style="padding:9px 12px;font-size:12px;color:#64748b;font-weight:700">GUÍA</th>
                                <th style="padding:9px 12px;font-size:12px;color:#64748b;font-weight:700">CLIENTE</th>
                                <th style="padding:9px 12px;font-size:12px;color:#64748b;font-weight:700;text-align:right">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($this->guardadas as $guia => $fotos)
                            @php $f = $fotos->first(); @endphp
                            <tr style="border-top:1px solid #f1f5f9">
                                <td style="padding:10px 12px;vertical-align:top;white-space:nowrap">
                                    <div style="font-weight:800">{{ $guia }}</div>
                                    <div style="font-size:11.5px;color:#94a3b8">{{ $f->created_at->format('d/m H:i') }}</div>
                                </td>
                                <td style="padding:10px 12px;vertical-align:top">
                                    <div style="font-weight:600">{{ $f->nombre ?: '—' }}</div>
                                    <div style="font-size:12px;color:#64748b">{{ $f->telefono ?: 'sin teléfono' }}</div>
                                </td>
                                <td style="padding:10px 12px;vertical-align:top">
                                    <div style="display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap">
                                        @if($f->whatsapp())
                                            <a href="{{ $f->whatsapp() }}" target="_blank" rel="noopener" title="Enviar por WhatsApp"
                                               style="background:#25D366;color:#fff;border-radius:7px;padding:6px 9px;font-size:13px;text-decoration:none">💬</a>
                                        @endif
                                        <button type="button" class="js-copiar" data-copiar="{{ $guia }}" title="Copiar guía"
                                            style="background:#eff6ff;border:1px solid #bfdbfe;color:#2563eb;border-radius:7px;padding:6px 9px;font-size:13px;cursor:pointer">📋</button>
                                        @if($f->telefono)
                                            <button type="button" class="js-copiar" data-copiar="{{ $f->telefono }}" title="Copiar teléfono"
                                                style="background:#ecfdf5;border:1px solid #a7f3d0;color:#059669;border-radius:7px;padding:6px 9px;font-size:13px;cursor:pointer">📞</button>
                                        @endif
                                        <button type="button" class="js-copiar" data-copiar="{{ $f->enlaceRastreo() }}" title="Copiar enlace"
                                            style="background:#f5f3ff;border:1px solid #ddd6fe;color:#7c3aed;border-radius:7px;padding:6px 9px;font-size:13px;cursor:pointer">🔗</button>
                                        <a href="{{ $f->url() }}" target="_blank" rel="noopener" title="Ver foto"
                                           style="background:#f8fafc;border:1px solid #e5e7eb;color:#475569;border-radius:7px;padding:6px 9px;font-size:13px;text-decoration:none">🖼️</a>
                                        <button type="button" wire:click="eliminarFoto({{ $f->id }})" wire:confirm="¿Borrar esta foto?" title="Borrar"
                                            style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:7px;padding:6px 9px;font-size:13px;cursor:pointer">🗑</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div style="color:#6b7280;font-size:13.5px;background:#f9fafb;border:1px dashed #e5e7eb;border-radius:10px;padding:16px;text-align:center">
                    Todavía no hay fotos guardadas. Subí las etiquetas arriba y aparecerán aquí.
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <script>
    (() => {
        const input = document.getElementById('fotos-input');
        const cont  = document.getElementById('resultados');
        const resumen = document.getElementById('resumen');
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

        input.addEventListener('change', async () => {
            const files = [...input.files];
            if (!files.length) return;
            ok = 0; fallo = 0;
            resumen.style.display = 'block';

            for (const file of files) {
                const url = URL.createObjectURL(file);
                const card = tarjeta(file.name, url);
                const est = card.querySelector('.estado');

                const img = new Image();
                await new Promise(r => { img.onload = r; img.onerror = r; img.src = url; });

                const texto = await leerQR(file, img);
                const guia = sacarGuia(texto);

                if (!guia) {
                    fallo++;
                    est.innerHTML = '<span style="color:#dc2626">✕ No se pudo leer el QR</span>';
                    const acc = card.querySelector('.acciones');

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
                    const mandar = () => {
                        const v = inp.value.trim();
                        if (!v) return;
                        inp.disabled = true; btn.disabled = true; fila.remove(); acc.innerHTML = '';
                        subir(file, v, est, acc);
                    };
                    btn.addEventListener('click', mandar);
                    inp.addEventListener('keydown', e => { if (e.key === 'Enter') mandar(); });
                    fila.append(inp, btn);
                    acc.after(fila);

                    actualizarContador();
                    continue;
                }

                const acc = card.querySelector('.acciones');

                // Lee nombre y teléfono de la etiqueta ANTES de subir, para guardarlos
                // junto con la foto (el nombre sirve para saludar al cliente en el rastreo).
                est.innerHTML = '✓ Guía ' + guia + ' <span style="font-size:12px;color:#6b7280;font-weight:500">· leyendo datos…</span>';
                const datos = await leerDatosCliente(img);

                await subir(file, guia, est, acc, datos);
                if (datos.telefono) agregarWhatsapp(acc, datos, guia);

                actualizarContador();
            }
            input.value = '';

            // Refresca la lista de guardadas, para tenerlas siempre a la mano.
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
            if (!totalSubidas) return;
            resumen.innerHTML = `📨 <b>${enviados} de ${totalSubidas}</b> enviados`
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

        async function subir(file, guia, est, acc, datos) {
            est.textContent = 'Subiendo guía ' + guia + '…';
            const fd = new FormData();
            fd.append('guia', guia);
            fd.append('foto', file);
            if (datos && datos.nombre)   fd.append('nombre', datos.nombre);
            if (datos && datos.telefono) fd.append('telefono', datos.telefono);
            try {
                const res = await fetch('{{ route('fotos.subir') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await res.json();
                if (data.ok) {
                    ok++;
                    est.innerHTML = `<span style="color:#059669">✓ Guía ${data.guia}</span>`;

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
                } else {
                    fallo++;
                    est.innerHTML = '<span style="color:#dc2626">✕ ' + (data.error || 'Error al subir') + '</span>';
                }
            } catch (e) {
                fallo++;
                est.innerHTML = '<span style="color:#dc2626">✕ Error de conexión</span>';
            }
        }
    })();
    </script>
@endpush

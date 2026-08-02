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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
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

        const sacarGuia = (txt) => {
            if (!txt) return null;
            const m = String(txt).match(/(\d{5,10})\s*$/) || String(txt).match(/(\d{5,10})/);
            return m ? m[1] : null;
        };

        const tarjeta = (nombre, src) => {
            const d = document.createElement('div');
            d.style.cssText = 'display:flex;gap:12px;align-items:center;border:1px solid #e5e7eb;border-radius:12px;padding:10px;background:#fff';
            d.innerHTML = `<img src="${src}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;flex:none">
                <div style="flex:1;min-width:0">
                  <div style="font-size:12.5px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${nombre}</div>
                  <div class="estado" style="font-weight:700;font-size:14px;margin-top:3px">Leyendo QR…</div>
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
                    const inp = document.createElement('input');
                    inp.placeholder = 'Escribí la guía y presioná Enter';
                    inp.style.cssText = 'margin-top:6px;width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px';
                    inp.addEventListener('keydown', e => {
                        if (e.key === 'Enter' && inp.value.trim()) { inp.disabled = true; subir(file, inp.value.trim(), est); }
                    });
                    est.after(inp);
                    resumen.textContent = `✅ ${ok} subidas · ✕ ${fallo} sin leer`;
                    continue;
                }

                await subir(file, guia, est);
                resumen.textContent = `✅ ${ok} subidas · ✕ ${fallo} sin leer`;
            }
            input.value = '';
        });

        async function subir(file, guia, est) {
            est.textContent = 'Subiendo guía ' + guia + '…';
            const fd = new FormData();
            fd.append('guia', guia);
            fd.append('foto', file);
            try {
                const res = await fetch('{{ route('fotos.subir') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await res.json();
                if (data.ok) {
                    ok++;
                    est.innerHTML = `<span style="color:#059669">✓ Guía ${data.guia}</span>
                        <a href="${data.rastreo}" target="_blank" style="display:block;font-size:12.5px;color:#2563eb;margin-top:2px">Ver enlace de seguimiento ↗</a>`;
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
</x-filament-panels::page>

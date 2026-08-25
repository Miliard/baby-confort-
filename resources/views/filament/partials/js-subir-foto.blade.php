{{--
    Subida manual de fotos de paquetes, SIN pasar por Livewire.

    Livewire guarda el archivo en una carpeta temporal y después lo va a buscar.
    En este servidor ese paso falla ("Unable to retrieve the file_size ...
    livewire-tmp") porque PHP descarta la foto por peso antes de que llegue.

    Acá se hace lo mismo que en Guías → Fotos, que sí funciona: la imagen se
    achica en el navegador y se manda directo a /fotos-paquete. Así nunca se
    topa con el límite de subida de PHP.
--}}
<script>
(() => {
    if (window.bcSubirFotoGuia) return;

    const TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;
    const RUTA  = @js(route('fotos.subir'));

    // Achica la foto a 1600 px de lado largo. Una foto de teléfono pasa de
    // unos 6 MB a unos 300 KB, y la etiqueta se sigue leyendo perfecto.
    function achicar(file) {
        return new Promise((listo) => {
            try {
                if (file.size <= 900 * 1024) return listo(file);

                const url = URL.createObjectURL(file);
                const img = new Image();
                img.onload = () => {
                    try {
                        const escala = Math.min(1, 1600 / Math.max(img.width, img.height));
                        const c = document.createElement('canvas');
                        c.width  = Math.round(img.width * escala);
                        c.height = Math.round(img.height * escala);
                        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                        c.toBlob((blob) => {
                            URL.revokeObjectURL(url);
                            listo(blob && blob.size > 0 && blob.size < file.size ? blob : file);
                        }, 'image/jpeg', 0.82);
                    } catch (e) {
                        URL.revokeObjectURL(url);
                        listo(file);
                    }
                };
                img.onerror = () => { URL.revokeObjectURL(url); listo(file); };
                img.src = url;
            } catch (e) {
                listo(file);
            }
        });
    }

    window.bcSubirFotoGuia = async function (caja) {
        const input  = caja.querySelector('.bc-sf-archivo');
        const campo  = caja.querySelector('.bc-sf-guia');
        const boton  = caja.querySelector('.bc-sf-boton');
        const aviso  = caja.querySelector('.bc-sf-aviso');

        const guia = (campo ? campo.value : caja.dataset.guia || '').replace(/\D/g, '');
        const file = input && input.files ? input.files[0] : null;

        const decir = (texto, color) => {
            aviso.textContent = texto;
            aviso.style.color = color;
        };

        if (!guia)  return decir('Escribí el número de guía.', '#dc2626');
        if (!file)  return decir('Elegí una foto.', '#dc2626');

        boton.disabled = true;
        decir('Achicando la foto…', '#6b7280');

        const liviana = await achicar(file);

        decir('Subiendo (' + Math.round(liviana.size / 1024) + ' KB)…', '#6b7280');

        const fd = new FormData();
        fd.append('guia', guia);
        fd.append('foto', liviana, 'guia-' + guia + '.jpg');

        try {
            const res   = await fetch(RUTA, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': TOKEN, 'Accept': 'application/json' },
                body: fd,
            });
            const crudo = await res.text();

            let data = null;
            try { data = JSON.parse(crudo); } catch (e) {}

            if (!data) {
                boton.disabled = false;
                return decir(
                    res.status === 413 ? 'La foto pesa demasiado para el servidor.'
                    : res.status === 419 ? 'La sesión venció: recargá la página.'
                    : 'El servidor respondió mal (' + res.status + ').',
                    '#dc2626'
                );
            }

            if (!data.ok) {
                boton.disabled = false;
                const motivo = data.error
                    || (data.errors && Object.values(data.errors)[0] && Object.values(data.errors)[0][0])
                    || 'No se pudo guardar';
                return decir('✕ ' + motivo, '#dc2626');
            }

            decir('✓ Guardada. Actualizando la lista…', '#059669');
            setTimeout(() => window.location.reload(), 700);
        } catch (e) {
            boton.disabled = false;
            decir('✕ Error de conexión.', '#dc2626');
        }
    };
})();
</script>

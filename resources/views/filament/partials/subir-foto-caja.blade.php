{{-- Cajita para subir una foto a mano. $guia = número fijo, o null para escribirlo. --}}
@php $guia = $guia ?? null; @endphp

<div class="bc-sf" data-guia="{{ $guia }}"
     style="display:flex;flex-direction:column;gap:12px">

    @if(! $guia)
        <label style="display:block">
            <span style="display:block;font-size:12.5px;font-weight:600;color:#6b7280;margin-bottom:5px">
                Número de guía
            </span>
            <input type="text" inputmode="numeric" class="bc-sf-guia"
                   placeholder="El que aparece impreso en la etiqueta"
                   style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:9px;
                          font-size:15px;background:transparent;color:inherit">
        </label>
    @else
        <div style="font-size:13.5px;color:#6b7280">
            Se guardará en la guía <b style="color:inherit">{{ $guia }}</b>.
        </div>
    @endif

    <label style="display:block">
        <span style="display:block;font-size:12.5px;font-weight:600;color:#6b7280;margin-bottom:5px">
            Foto del paquete
        </span>
        <input type="file" accept="image/*" class="bc-sf-archivo"
               style="width:100%;font-size:14px">
    </label>

    <div class="bc-sf-aviso" style="font-size:13px;min-height:18px;color:#6b7280">
        La foto se achica sola antes de subir, así no se traba.
    </div>

    <button type="button" class="bc-sf-boton"
            onclick="bcSubirFotoGuia(this.closest('.bc-sf'))"
            style="align-self:flex-start;border:none;border-radius:9px;padding:10px 18px;
                   font-size:14px;font-weight:700;cursor:pointer;background:#d97706;color:#fff">
        Guardar foto
    </button>
</div>

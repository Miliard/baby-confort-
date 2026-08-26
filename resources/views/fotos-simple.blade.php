<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Subir fotos de paquetes — Baby-Confort</title>
<style>
    *{box-sizing:border-box}
    body{margin:0;padding:18px;background:#0f1626;color:#eef2f7;
         font-family:'Segoe UI',system-ui,-apple-system,sans-serif;font-size:16px;line-height:1.5}
    .caja{max-width:560px;margin:0 auto}
    h1{font-size:21px;margin:0 0 4px}
    .sub{color:#94a3b8;font-size:14px;margin:0 0 18px}
    form{background:#182236;border:1px solid rgba(255,255,255,.10);border-radius:14px;padding:16px;margin-bottom:16px}
    label{display:block;font-size:13px;color:#94a3b8;margin:0 0 5px}
    input[type=text],input[type=file]{width:100%;padding:12px;border:1px solid rgba(255,255,255,.16);
        border-radius:10px;background:#0f1828;color:#eef2f7;font-size:16px;margin-bottom:14px}
    button{width:100%;border:none;border-radius:10px;padding:15px;font-size:17px;font-weight:800;
           cursor:pointer;background:#d97706;color:#fff}
    .aviso{border-radius:12px;padding:13px 15px;margin-bottom:16px;font-size:15px}
    .ok{background:rgba(46,158,107,.16);border:1px solid #2e9e6b;color:#9fe1cb}
    .mal{background:rgba(229,105,95,.14);border:1px solid #e5695f;color:#f5c4b3}
    .pista{font-size:13px;color:#7d8ba0;margin-top:14px;line-height:1.6}
    .volver{display:inline-block;margin-top:18px;color:#4aa3df;text-decoration:none;font-weight:700}
    .ult{background:#182236;border:1px solid rgba(255,255,255,.10);border-radius:14px;padding:14px}
    .ult h2{font-size:14px;margin:0 0 10px;color:#94a3b8;font-weight:700}
    .fila{display:flex;align-items:center;gap:11px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06)}
    .fila:last-child{border-bottom:none}
    .fila img{width:44px;height:44px;object-fit:cover;border-radius:8px;flex:none}
    .fila b{font-size:15px}
    .fila span{display:block;font-size:12.5px;color:#94a3b8}
</style>
</head>
<body>
<div class="caja">

    <h1>Subir foto de paquete</h1>
    <p class="sub">Página directa, sin lectura de QR ni nada que se pueda trabar.</p>

    @if(session('bc_ok'))
        <div class="aviso ok">✅ {{ session('bc_ok') }}</div>
    @endif
    @if(session('bc_mal'))
        <div class="aviso mal">✕ {{ session('bc_mal') }}</div>
    @endif
    @if($errors->any())
        <div class="aviso mal">✕ {{ $errors->first() }}</div>
    @endif

    {{-- Estado del disco. Si está lleno, las fotos se guardan VACÍAS y el
         sistema dice que todo salió bien. Por eso va arriba y bien visible. --}}
    @php $esp = \App\Http\Controllers\GuiaFotoController::espacioUsado(); @endphp
    <form method="POST" action="{{ route('fotos.liberar') }}"
          style="background:{{ ($esp['apretado'] || $esp['vacios'] > 0) ? 'rgba(229,105,95,.14)' : '#182236' }};
                 border:1px solid {{ ($esp['apretado'] || $esp['vacios'] > 0) ? '#e5695f' : 'rgba(255,255,255,.10)' }}">
        @csrf
        <div style="font-size:14px;line-height:1.7;margin-bottom:12px">
            <b>Disco del servidor</b><br>
            Fotos: {{ $esp['archivos'] }} · {{ $esp['legible'] }}
            <span style="color:#94a3b8">({{ $esp['promedio'] }} cada una)</span><br>
            Espacio libre: <b>{{ $esp['libreLegible'] }}</b><br>
            Se guardan <b>{{ $esp['dias'] }} días</b>
            @if($esp['masVieja'])
                · la más vieja es del {{ \Illuminate\Support\Carbon::parse($esp['masVieja'])->format('d/m/Y') }}
            @endif
            @if($esp['vacios'] > 0)
                <br><b style="color:#f5c4b3">{{ $esp['vacios'] }} fotos quedaron vacías (el disco se llenó).</b>
            @endif
        </div>
        <button type="submit" style="background:#2e9e6b">🧹 Liberar espacio</button>
    </form>

    {{-- Palanca principal cuando el disco se llena: acortar el tiempo que se
         guardan las fotos. No se pierde ningún dato del pedido, solo imágenes
         viejas que ya no vas a consultar. --}}
    <form method="POST" action="{{ route('fotos.dias') }}">
        @csrf
        <label for="dias">Guardar las fotos por</label>
        <input type="text" id="dias" name="dias" inputmode="numeric"
               value="{{ $esp['dias'] }}" style="margin-bottom:8px">
        <div style="font-size:12.5px;color:#7d8ba0;margin-bottom:12px;line-height:1.6">
            Bajalo a <b>7</b> si el disco está lleno: borra las imágenes de más de una semana
            y libera espacio al instante. La guía, el cliente, el teléfono y qué llevaba
            <b>no se borran nunca</b>.
        </div>
        <button type="submit" style="background:#4aa3df">Aplicar y limpiar ahora</button>
    </form>

    {{-- Formulario de toda la vida: lo manda el navegador, no JavaScript.
         Si esto no funciona, el problema está en el servidor y no hay
         ninguna otra pieza a la que culpar. --}}
    <form method="POST" action="{{ route('fotos.simple.guardar') }}" enctype="multipart/form-data">
        @csrf

        <label for="guia">Número de guía</label>
        <input type="text" id="guia" name="guia" inputmode="numeric"
               value="{{ old('guia') }}" placeholder="El que está impreso en la etiqueta" required>

        <label for="foto">Foto de la etiqueta</label>
        <input type="file" id="foto" name="foto" accept="image/*" required>

        <button type="submit">Subir foto</button>
    </form>

    @if($ultimas->isNotEmpty())
        <div class="ult">
            <h2>Últimas subidas</h2>
            @foreach($ultimas as $f)
                <div class="fila">
                    <img src="{{ $f->url() }}" alt="Etiqueta {{ $f->guia }}">
                    <div>
                        <b>Guía {{ $f->guia }}</b>
                        <span>{{ $f->nombre ?: 'Sin nombre' }} · {{ $f->updated_at?->format('d/m H:i') }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <p class="pista">
        Si la guía ya existe, la foto se le pega y conserva su cliente y su pedido.
        Si no existe, se crea con esa foto.
    </p>

    <a class="volver" href="{{ url('/admin/crear-guia') }}">← Volver al panel</a>
</div>
</body>
</html>

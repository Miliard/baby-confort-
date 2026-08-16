<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Remuneración AIWIBI {{ $periodo }}</title>
<style>
    *{box-sizing:border-box}
    body{margin:0;padding:28px 30px;background:#fff;color:#1b2a3a;
         font-family:'Segoe UI',system-ui,-apple-system,sans-serif;font-size:12.5px;line-height:1.45}
    .cab{display:flex;justify-content:space-between;align-items:flex-start;
         border-bottom:2px solid #1b2a3a;padding-bottom:12px;margin-bottom:18px}
    h1{font-size:20px;margin:0 0 3px}
    .sub{font-size:12px;color:#6b7c8c}
    .tot{text-align:right}
    .tot .et{font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;color:#6b7c8c}
    .tot .n{font-size:26px;font-weight:800;color:#16a34a;line-height:1.1}
    table{width:100%;border-collapse:collapse;margin-bottom:18px}
    th{text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:#6b7c8c;
       border-bottom:1.5px solid #d7dfe7;padding:6px 8px 6px 0}
    td{padding:5px 8px 5px 0;border-bottom:1px solid #eef2f6;vertical-align:top}
    td.der,th.der{text-align:right;padding-right:0}
    .guia{font-size:10.5px;color:#94a3b8}
    .cuenta{width:320px;margin-left:auto}
    .lin{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #eef2f6}
    .lin.fin{border-bottom:none;border-top:2px solid #1b2a3a;margin-top:4px;padding-top:8px;font-size:15px}
    .lin b.neg{color:#dc2626}
    .pie{margin-top:26px;font-size:10.5px;color:#94a3b8;border-top:1px solid #eef2f6;padding-top:10px}
    .barra{position:fixed;top:0;left:0;right:0;background:#1b2a3a;color:#fff;padding:9px 16px;
           font-size:13px;display:flex;justify-content:space-between;align-items:center}
    .barra button{border:none;border-radius:7px;padding:6px 14px;font-weight:700;font-size:12.5px;
                  cursor:pointer;background:#4aa3df;color:#fff}
    body{padding-top:60px}
    @media print{ .barra{display:none} body{padding:0} @page{margin:14mm} }
</style>
</head>
<body>

<div class="barra">
    <span>Para guardarlo como PDF: tocá Imprimir y elegí <b>“Guardar como PDF”</b> en Destino.</span>
    <button onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
</div>

<div class="cab">
    <div>
        <h1>Remuneración AIWIBI</h1>
        <div class="sub">{{ $periodo }}</div>
        <div class="sub">Baby-Confort · emitido el {{ now()->format('d/m/Y') }}</div>
    </div>
    <div class="tot">
        <div class="et">Total a pagar</div>
        <div class="n">${{ number_format($m['aPagar'], 2) }}</div>
        <div class="sub">{{ $m['envios'] }} {{ $m['envios'] === 1 ? 'envío' : 'envíos' }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:60px">Fecha</th>
            <th>Cliente</th>
            <th style="width:150px">Zona</th>
            <th class="der" style="width:90px">Monto</th>
        </tr>
    </thead>
    <tbody>
        @foreach($m['filas'] as $f)
            <tr>
                <td>{{ $f->fecha->format('d/m/Y') }}</td>
                <td>
                    {{ $f->nombre }}
                    <span class="guia">Guía {{ $f->orden }}</span>
                </td>
                <td>{{ $f->zona }}</td>
                <td class="der">${{ number_format($f->monto, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="cuenta">
    <div class="lin"><span>Cobrado</span><b>${{ number_format($m['cobrado'], 2) }}</b></div>
    <div class="lin">
        <span>Comisión {{ rtrim(rtrim(number_format($m['comisionPct'], 2), '0'), '.') }}%</span>
        <b class="neg">− ${{ number_format($m['comision'], 2) }}</b>
    </div>
    <div class="lin"><span>Subtotal</span><b>${{ number_format($m['subtotal'], 2) }}</b></div>
    <div class="lin">
        <span>{{ $m['envios'] }} envíos × ${{ number_format($m['porEnvio'], 2) }}</span>
        <b class="neg">− ${{ number_format($m['descuento'], 2) }}</b>
    </div>
    <div class="lin fin"><b>Total a pagar</b><b style="color:#16a34a">${{ number_format($m['aPagar'], 2) }}</b></div>
</div>

<div class="pie">
    @if($m['sinCobro'] > 0)
        {{ $m['sinCobro'] }} {{ $m['sinCobro'] === 1 ? 'envío vino' : 'envíos vinieron' }} en $0 (ya estaban pagados o no se cobró al entregar).
    @endif
    @if($m['devueltos'] > 0)
        {{ $m['devueltos'] }} {{ $m['devueltos'] === 1 ? 'envío devuelto no cuenta' : 'envíos devueltos no cuentan' }} para el cobro de flete.
    @endif
</div>

<script>
    // Abre el diálogo de impresión solo: el usuario elige "Guardar como PDF".
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
</script>
</body>
</html>

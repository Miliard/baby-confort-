<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Baby-Confort | Pañales y calzoncitos para bebé</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root{
            --azul:#4aa3df;--azul-osc:#2f7fbf;--azul-claro:#eaf5fc;
            --coral:#ff8a80;--coral-osc:#e5695f;--texto:#2b3a4a;--gris:#6b7c8c;
            --borde:#e2e8ee;--ok:#2e9e6b;--fondo:#f7fbfe;
            --sombra:0 6px 18px rgba(47,127,191,.10);--radio:16px;
        }
        *{box-sizing:border-box}
        body{margin:0;background:var(--fondo);color:var(--texto);
            font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased}
        img{max-width:100%;display:block}
        .contenedor{max-width:1080px;margin:0 auto;padding:0 16px}
        .header{position:sticky;top:0;z-index:50;background:#fff;border-bottom:1px solid var(--borde);box-shadow:0 2px 8px rgba(47,127,191,.05)}
        .header-inner{display:flex;align-items:center;justify-content:space-between;height:64px}
        .logo{display:flex;align-items:center;gap:10px;font-weight:800;font-size:20px}
        .logo-badge{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,var(--azul),var(--azul-osc));color:#fff;display:grid;place-items:center;font-size:20px}
        .logo .marca span{color:var(--azul-osc)}
        .logo .marca small{display:block;font-size:11px;color:var(--gris);font-weight:500}
        .btn-carrito{position:relative;border:1px solid var(--borde);background:#fff;padding:9px 14px;border-radius:999px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:15px}
        .btn-carrito:hover{border-color:var(--azul)}
        .badge{position:absolute;top:-8px;right:-8px;background:var(--coral);color:#fff;border-radius:999px;min-width:20px;height:20px;font-size:12px;font-weight:800;display:grid;place-items:center;padding:0 5px}
        .hero{background:linear-gradient(135deg,var(--azul-claro),#fff);border-bottom:1px solid var(--borde);padding:40px 0}
        .hero h1{margin:0 0 8px;font-size:30px;line-height:1.15}
        .hero p{margin:0;color:var(--gris);font-size:16px;max-width:620px}
        .pills{margin-top:18px;display:flex;flex-wrap:wrap;gap:8px}
        .pill{background:#fff;border:1px solid var(--borde);color:var(--azul-osc);padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600}
        .seccion-titulo{font-size:22px;margin:32px 0 4px}
        .seccion-sub{color:var(--gris);margin:0 0 20px;font-size:15px}
        .grid{display:grid;gap:18px;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));padding-bottom:48px}
        .card{background:#fff;border:1px solid var(--borde);border-radius:var(--radio);overflow:hidden;display:flex;flex-direction:column;box-shadow:var(--sombra)}
        .card-img{background:var(--azul-claro);aspect-ratio:1/1;display:grid;place-items:center;padding:14px}
        .card-img img{max-height:100%;object-fit:contain}
        .card-body{padding:16px;display:flex;flex-direction:column;gap:10px;flex:1}
        .card-marca{font-size:12px;color:var(--gris);text-transform:uppercase;letter-spacing:.5px}
        .card-nombre{font-size:17px;font-weight:700;line-height:1.25}
        .card-desc{font-size:13.5px;color:var(--gris);line-height:1.5;flex:1}
        .card-precio{display:flex;align-items:baseline;gap:8px}
        .card-precio b{font-size:22px;color:var(--azul-osc)}
        .card-combo{background:#fff4f3;color:var(--coral-osc);border:1px dashed var(--coral);border-radius:10px;padding:6px 10px;font-size:12.5px;font-weight:700}
        .campo{display:flex;flex-direction:column;gap:5px}
        .campo label{font-size:13px;font-weight:600}
        select,input,textarea{width:100%;border:1px solid var(--borde);border-radius:10px;padding:10px 12px;font-size:15px;font-family:inherit;background:#fff;color:var(--texto)}
        select:focus,input:focus,textarea:focus{outline:none;border-color:var(--azul)}
        .fila-cant{display:flex;gap:8px;align-items:flex-end}
        .btn{border:none;border-radius:12px;padding:12px 16px;font-size:15px;font-weight:700;cursor:pointer;width:100%}
        .btn-azul{background:var(--azul);color:#fff}.btn-azul:hover{background:var(--azul-osc)}
        .btn-coral{background:var(--coral);color:#fff}.btn-coral:hover{background:var(--coral-osc)}
        .btn-verde{background:var(--ok);color:#fff}
        .btn-linea{background:#fff;border:1px solid var(--borde);color:var(--texto)}
        .btn:disabled{opacity:.5;cursor:not-allowed}
        .agotado{font-size:12px;color:var(--coral-osc);font-weight:600}
        .overlay{position:fixed;inset:0;background:rgba(20,40,60,.45);z-index:60;display:flex;justify-content:flex-end}
        .drawer{width:100%;max-width:420px;background:var(--fondo);height:100%;display:flex;flex-direction:column;box-shadow:-8px 0 30px rgba(0,0,0,.15)}
        .drawer-head{padding:18px;background:#fff;border-bottom:1px solid var(--borde);display:flex;align-items:center;justify-content:space-between}
        .drawer-head h3{margin:0;font-size:18px}
        .cerrar{border:none;background:none;font-size:26px;cursor:pointer;color:var(--gris);line-height:1}
        .drawer-body{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:12px}
        .drawer-foot{background:#fff;border-top:1px solid var(--borde);padding:16px}
        .item{background:#fff;border:1px solid var(--borde);border-radius:12px;padding:12px;display:flex;gap:12px}
        .item img{width:56px;height:56px;object-fit:contain;background:var(--azul-claro);border-radius:8px;padding:4px}
        .item .nom{font-weight:700;font-size:14px;line-height:1.3}
        .item .meta{font-size:12.5px;color:var(--gris);margin-top:2px}
        .lineabajo{display:flex;align-items:center;justify-content:space-between;margin-top:8px}
        .qtybox{display:flex;align-items:center;gap:8px}
        .qtybox button{width:28px;height:28px;border-radius:8px;border:1px solid var(--borde);background:#fff;cursor:pointer;font-size:16px;font-weight:700}
        .quitar{border:none;background:none;color:var(--coral-osc);font-size:12.5px;cursor:pointer;font-weight:600}
        .total-fila{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
        .total-fila b{font-size:22px;color:var(--azul-osc)}
        .vacio{text-align:center;color:var(--gris);padding:40px 10px}
        .vacio .emoji{font-size:40px}
        .form-grid{display:flex;flex-direction:column;gap:12px}
        .pago-ops{display:flex;flex-direction:column;gap:8px}
        .pago-op{border:1px solid var(--borde);border-radius:10px;padding:11px 12px;display:flex;gap:10px;align-items:center;cursor:pointer;background:#fff;font-size:14px}
        .pago-op.sel{border-color:var(--azul);background:var(--azul-claro)}
        .pago-op input{width:auto}
        .aviso{font-size:12.5px;color:var(--gris);background:var(--azul-claro);padding:10px 12px;border-radius:10px}
        .req{color:var(--coral-osc)}
        .error{font-size:12.5px;color:var(--coral-osc)}
        .footer{border-top:1px solid var(--borde);background:#fff;padding:28px 0;margin-top:20px;color:var(--gris);font-size:14px}
        .footer b{color:var(--texto)}
        @media(max-width:520px){.hero h1{font-size:25px}}
    </style>
</head>
<body>
    @yield('content')
</body>
</html>

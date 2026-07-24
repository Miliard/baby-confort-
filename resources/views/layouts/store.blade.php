<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>(function(){try{if(localStorage.getItem('bc_theme')==='dark'){document.documentElement.classList.add('dark');}}catch(e){}})();</script>
    <title>@yield('title', 'Baby-Confort | Pañales Aiwibi antialérgicos en El Salvador')</title>
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Baby-Confort">
    <meta property="og:title" content="@yield('og_title', 'Pañales Aiwibi antialérgicos | Baby-Confort El Salvador 👶')">
    <meta property="og:description" content="@yield('og_desc', 'Pañales y calzoncitos Aiwibi antialérgicos, alta absorción y protección de noche. Entrega en todo El Salvador. Pide fácil por WhatsApp.')">
    <meta property="og:image" content="{{ request()->schemeAndHttpHost() }}/@yield('og_image', 'og-image.png')">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="description" content="@yield('meta_desc', 'Pañales Aiwibi antialérgicos en El Salvador: alta absorción, hipoalergénicos y protección de noche. Calzoncitos, pañales de bebé y talla especial. Pide por WhatsApp con entrega a domicilio.')">
    <meta name="keywords" content="@yield('meta_keywords', 'pañales Aiwibi, pañales antialérgicos, pañales El Salvador, calzoncitos Aiwibi, pañales de noche, pañales hipoalergénicos, pañales de bebé, talla especial')">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    @php $fbPixel = \App\Models\Setting::get('fb_pixel', ''); @endphp
    @if($fbPixel)
    <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
    document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '{{ $fbPixel }}');
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id={{ $fbPixel }}&ev=PageView&noscript=1"/></noscript>
    @endif
    <script>window.BC_ENVIO = {{ (float) ($envio ?? 2.5) }};</script>
    <script>window.BC_ENVIO_GRATIS = {{ (float) \App\Models\Setting::envioGratisDesde() }};</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root{
            --azul:#4aa3df;--azul-osc:#2f7fbf;--azul-claro:#eaf5fc;
            --teal:#2fb2ac;--teal-osc:#259a95;
            --coral:#ff8a80;--coral-osc:#e5695f;--texto:#2b3a4a;--gris:#6b7c8c;
            --borde:#e2e8ee;--ok:#2e9e6b;--fondo:#f7fbfe;--sombra:0 6px 18px rgba(47,127,191,.10);--radio:16px;
        }
        *{box-sizing:border-box}
        body{margin:0;background:var(--fondo);color:var(--texto);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased}
        img{max-width:100%;display:block}
        a{color:inherit;text-decoration:none}
        .contenedor{max-width:1100px;margin:0 auto;padding:0 16px}
        .header{position:sticky;top:0;z-index:50;background:#fff;border-bottom:1px solid var(--borde);box-shadow:0 2px 8px rgba(47,127,191,.05)}
        .header-inner{display:flex;align-items:center;justify-content:space-between;height:64px}
        .logo{display:flex;align-items:center;gap:10px;font-weight:800;font-size:20px}
        .logo-badge{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,var(--azul),var(--azul-osc));color:#fff;display:grid;place-items:center;font-size:20px}
        .logo .marca span{color:var(--azul-osc)}
        .logo .marca small{display:block;font-size:11px;color:var(--gris);font-weight:500}
        .btn-carrito{position:relative;border:1px solid var(--borde);background:#fff;padding:9px 14px;border-radius:999px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:15px}
        .btn-carrito:hover{border-color:var(--azul)}
        .ham-btn{background:none;border:1px solid var(--borde);border-radius:10px;width:40px;height:40px;font-size:20px;cursor:pointer;color:var(--azul-osc);display:grid;place-items:center;flex:none}
        .ham-btn:hover{border-color:var(--azul)}
        .ham-menu{background:#fff;border-bottom:1px solid var(--borde);box-shadow:0 8px 18px rgba(47,127,191,.08)}
        .ham-menu .contenedor{display:flex;flex-direction:column;padding:6px 16px 12px}
        .ham-menu a{padding:12px 6px;border-bottom:1px solid var(--borde);color:var(--texto);text-decoration:none;font-weight:700;font-size:15px}
        .ham-menu a:last-child{border-bottom:none}
        .ham-menu a:hover{color:var(--azul-osc)}
        .badge{position:absolute;top:-8px;right:-8px;background:var(--coral);color:#fff;border-radius:999px;min-width:20px;height:20px;font-size:12px;font-weight:800;display:grid;place-items:center;padding:0 5px}
        .hero{background:linear-gradient(135deg,var(--azul-claro),#fff);border-bottom:1px solid var(--borde);padding:36px 0}
        .hero h1{margin:0 0 8px;font-size:28px;line-height:1.15}
        .hero p{margin:0;color:var(--gris);font-size:16px;max-width:620px}
        .pills{margin-top:16px;display:flex;flex-wrap:wrap;gap:8px}
        .pill-i{background:#fff;border:1px solid var(--borde);color:var(--azul-osc);padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600}
        .seccion-titulo{font-size:22px;margin:28px 0 4px}
        .seccion-sub{color:var(--gris);margin:0 0 20px;font-size:15px}
        /* catálogo */
        .grid{display:grid;gap:18px;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));padding-bottom:48px}
        @media(max-width:600px){.grid{grid-template-columns:repeat(2,1fr);gap:12px}}
        .pcard{background:#fff;border:1px solid var(--borde);border-radius:var(--radio);overflow:hidden;display:flex;flex-direction:column;box-shadow:var(--sombra);transition:transform .08s, box-shadow .08s}
        .pcard:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(47,127,191,.16)}
        .pcard .img{background:var(--azul-claro);aspect-ratio:1/1;display:grid;place-items:center;padding:14px}
        .pcard .img img{max-height:100%;object-fit:contain}
        .pcard .body{padding:16px;display:flex;flex-direction:column;gap:6px;flex:1}
        .pcard .marca{font-size:12px;color:var(--gris);text-transform:uppercase;letter-spacing:.5px}
        .pcard .nom{font-size:16px;font-weight:700;line-height:1.25;flex:1}
        .pcard .precio{color:var(--azul-osc);font-weight:800;font-size:18px}
        .pcard .ver{margin-top:6px;background:var(--teal);color:#fff;text-align:center;border-radius:10px;padding:9px;font-weight:700;font-size:14px}
        .pcard:hover .ver{background:var(--teal-osc)}
        /* ===== Página de producto ===== */
        .pdp{display:grid;grid-template-columns:1.1fr 1fr;gap:30px;padding:24px 0 48px}
        @media(max-width:820px){.pdp{grid-template-columns:1fr;gap:20px}}
        .gal-main{background:#0e1420;border-radius:16px;overflow:hidden;aspect-ratio:1/1;display:grid;place-items:center;position:relative}
        .gal-main img{width:100%;height:100%;object-fit:contain}
        .gal-nav{z-index:3}
        .gal-main img.anim{animation:slideFade .7s ease}
        @keyframes slideFade{0%{opacity:0;transform:translateX(34px)}100%{opacity:1;transform:translateX(0)}}
        .gal-nav{position:absolute;top:50%;transform:translateY(-50%);width:40px;height:40px;border-radius:999px;background:rgba(255,255,255,.85);border:none;cursor:pointer;font-size:20px;display:grid;place-items:center}
        .gal-nav.prev{left:10px}.gal-nav.next{right:10px}
        .gal-thumbs{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap}
        .gal-thumbs .th{width:74px;height:74px;border-radius:10px;overflow:hidden;border:2px solid var(--borde);cursor:pointer;background:#0e1420;display:grid;place-items:center;padding:4px}
        .gal-thumbs .th.sel{border-color:var(--teal)}
        .gal-thumbs .th img{width:100%;height:100%;object-fit:contain}
        .trust{display:flex;align-items:center;gap:10px;background:#fff5f2;border-radius:12px;padding:10px 12px;font-size:13px}
        .trust .avs{display:flex}
        .trust .avs span{width:26px;height:26px;border-radius:999px;background:var(--azul-claro);border:2px solid #fff;margin-left:-8px;display:grid;place-items:center;font-size:13px}
        .pdp h1{font-size:26px;margin:14px 0 8px;line-height:1.2}
        .pdp .desc{color:var(--gris);font-size:15px;line-height:1.6}
        .pdp .precio{font-size:22px;font-weight:800;margin:12px 0}
        .pdp .precio small{color:var(--gris);font-weight:600;font-size:14px}
        hr.sep{border:none;border-top:1px solid var(--borde);margin:16px 0}
        .feat{display:flex;align-items:flex-start;gap:10px;margin:9px 0;font-size:14.5px}
        .feat .ic{width:30px;height:30px;border-radius:8px;background:var(--azul-claro);display:grid;place-items:center;font-size:16px;flex:none}
        .infobar{display:flex;align-items:center;gap:8px;background:#fff5f2;border-radius:10px;padding:9px 12px;font-size:13.5px;margin:12px 0;color:var(--texto)}
        .infobar .dot{width:9px;height:9px;border-radius:999px;background:var(--teal)}
        .size-label{font-size:12px;font-weight:700;color:var(--gris);letter-spacing:.5px;margin:14px 0 8px}
        .size-pills{display:flex;flex-wrap:wrap;gap:10px}
        .spill{border:1px solid var(--borde);background:#fff;border-radius:999px;padding:9px 16px;cursor:pointer;font-weight:700;font-size:14px}
        .spill.sel{border-color:var(--teal);background:#e9f8f7;color:var(--teal-osc)}
        .spill:disabled{opacity:.7;cursor:not-allowed;color:var(--gris);border-style:dashed;background:#f7f9fb}
        .spill:disabled small{color:var(--coral-osc);font-weight:800;font-size:10.5px}
        .cta{margin-top:16px;background:var(--teal);color:#fff;border:none;border-radius:12px;padding:15px;font-size:16px;font-weight:800;width:100%;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}
        .cta:hover{background:var(--teal-osc)}
        .metarow{display:flex;gap:16px;margin-top:16px;flex-wrap:wrap}
        .metarow .m{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gris)}
        .metarow .m b{color:var(--texto)}
        .warn{margin-top:16px;border:1px solid #f3c6c0;background:#fff5f2;border-radius:12px;padding:14px}
        .warn .t{color:var(--coral-osc);font-weight:800;font-size:14px;margin-bottom:4px}
        .warn .b{font-size:13.5px;color:var(--texto);line-height:1.55}
        .volver{display:inline-flex;gap:6px;align-items:center;color:var(--gris);font-size:14px;margin:16px 0 0}
        /* ===== inputs / botones comunes ===== */
        .campo{display:flex;flex-direction:column;gap:5px}
        .campo label{font-size:13px;font-weight:600}
        select,input,textarea{width:100%;border:1px solid var(--borde);border-radius:10px;padding:10px 12px;font-size:15px;font-family:inherit;background:#fff;color:var(--texto)}
        select:focus,input:focus,textarea:focus{outline:none;border-color:var(--azul)}
        .btn{border:none;border-radius:12px;padding:12px 16px;font-size:15px;font-weight:700;cursor:pointer;width:100%}
        .btn-azul{background:var(--azul);color:#fff}.btn-azul:hover{background:var(--azul-osc)}
        .btn-coral{background:var(--coral);color:#fff}.btn-coral:hover{background:var(--coral-osc)}
        .btn-verde{background:var(--ok);color:#fff}
        .btn-linea{background:#fff;border:1px solid var(--borde);color:var(--texto)}
        .btn:disabled{opacity:.5;cursor:not-allowed}
        /* ===== carrito (drawer) ===== */
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
        .vacio{text-align:center;color:var(--gris);padding:40px 10px}.vacio .emoji{font-size:40px}
        .form-grid{display:flex;flex-direction:column;gap:12px}
        .pago-ops{display:flex;flex-direction:column;gap:8px}
        .pago-op{border:1px solid var(--borde);border-radius:10px;padding:11px 12px;display:flex;gap:10px;align-items:center;cursor:pointer;background:#fff;font-size:14px}
        .pago-op.sel{border-color:var(--azul);background:var(--azul-claro)}.pago-op input{width:auto}
        .aviso{font-size:12.5px;color:var(--gris);background:var(--azul-claro);padding:10px 12px;border-radius:10px}
        .envio-gratis-bar{background:linear-gradient(135deg,#eef8f2,#eaf5fc);border:1px solid #cfe8dc;border-radius:12px;padding:11px 13px;margin-bottom:4px}
        .egb-msg{font-size:13px;color:var(--texto);margin-bottom:8px}
        .egb-msg b{color:var(--teal-osc)}
        .egb-ok{color:var(--teal-osc);font-weight:700}
        .egb-track{height:8px;background:#e2ebe6;border-radius:999px;overflow:hidden}
        .egb-fill{height:100%;background:linear-gradient(90deg,var(--teal),var(--ok));border-radius:999px;transition:width .35s ease}
        .error{font-size:12.5px;color:var(--coral-osc)}
        .cupon-box{margin-bottom:10px}
        .cupon-row{display:flex;gap:8px}
        .cupon-input{flex:1;border:1px dashed var(--borde);border-radius:10px;padding:10px 12px;font-size:14px}
        .cupon-btn{flex:none;border:none;background:var(--azul);color:#fff;border-radius:10px;padding:0 16px;font-weight:700;font-size:14px;cursor:pointer}
        .cupon-btn:disabled{opacity:.6;cursor:default}
        .cupon-ok{display:flex;align-items:center;justify-content:space-between;gap:10px;background:#eef8f2;border:1px solid #bfe6cf;border-radius:10px;padding:9px 12px;font-size:14px;color:var(--texto)}
        .cupon-ok b{color:var(--teal-osc)}
        .cupon-quitar{border:none;background:none;color:var(--coral-osc);font-size:12.5px;font-weight:700;cursor:pointer}
        .cupon-error{font-size:12.5px;color:var(--coral-osc);margin-top:6px}
        html.dark .cupon-input{background:#16202f;color:var(--texto)}
        html.dark .cupon-ok{background:#14261c;border-color:#2f5a3f}
        .footer{border-top:1px solid var(--borde);background:#fff;padding:28px 0;margin-top:20px;color:var(--gris);font-size:14px}
        .footer b{color:var(--texto)}
        .footer-links{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:12px}
        .footer-links a{color:var(--azul-osc);font-weight:700;text-decoration:none;font-size:13.5px}
        .footer-links a:hover{text-decoration:underline}
        .wa-float{position:fixed;right:18px;bottom:18px;z-index:70;width:58px;height:58px;border-radius:999px;background:#25D366;display:grid;place-items:center;box-shadow:0 6px 18px rgba(0,0,0,.28);transition:transform .1s}
        .wa-float:hover{transform:scale(1.07)}
        .wa-float svg{width:32px;height:32px;fill:#fff}
        .wa-float .tip{position:absolute;right:70px;background:#fff;color:var(--texto);border:1px solid var(--borde);border-radius:10px;padding:7px 11px;font-size:13px;font-weight:600;white-space:nowrap;box-shadow:var(--sombra);opacity:0;pointer-events:none;transition:opacity .15s}
        .wa-float:hover .tip{opacity:1}
        /* Fondo galaxia pastel (limpio y suave) */
        body{background:
            radial-gradient(1100px 620px at 12% 8%, rgba(179,201,255,.40), transparent 62%),
            radial-gradient(1000px 700px at 88% 16%, rgba(224,190,255,.34), transparent 60%),
            radial-gradient(950px 650px at 78% 92%, rgba(255,201,231,.26), transparent 60%),
            radial-gradient(700px 500px at 30% 80%, rgba(198,240,255,.30), transparent 62%),
            linear-gradient(180deg,#eef3ff 0%,#f5f0ff 45%,#fef6fb 100%);
            background-attachment:fixed;}
        .hero{background:transparent !important;border-bottom:1px solid rgba(255,255,255,.6) !important}
        .footer{background:rgba(255,255,255,.7)}
        /* Slider guía de tallas */
        .sg-wrap{max-width:640px;margin:28px auto;background:rgba(255,255,255,.75);backdrop-filter:blur(6px);border:1px solid #e7ddff;border-radius:22px;padding:22px;text-align:center;box-shadow:0 10px 30px rgba(120,120,200,.15)}
        .sg-wrap h3{margin:0 0 14px;font-size:20px;color:var(--azul-osc)}
        .sg-view{display:flex;align-items:center;justify-content:center;gap:10px}
        .sg-slide{flex:1;background:linear-gradient(135deg,#eaf2ff,#f3ecff);border-radius:16px;padding:24px 16px;min-height:120px;display:flex;flex-direction:column;justify-content:center;gap:6px;animation:sgIn .5s ease}
        @keyframes sgIn{0%{opacity:.2;transform:translateX(18px)}100%{opacity:1;transform:translateX(0)}}
        .sg-talla{font-size:26px;font-weight:800;color:#5a6fb0}
        .sg-kg{font-size:20px;font-weight:700;color:var(--texto)}
        .sg-lb{font-size:15px;color:var(--gris)}
        .sg-arrow{width:40px;height:40px;border-radius:999px;border:none;background:#fff;box-shadow:var(--sombra);cursor:pointer;font-size:22px;color:var(--azul-osc);flex:none}
        .sg-dots{display:flex;gap:7px;justify-content:center;margin-top:14px}
        .sg-dots span{width:9px;height:9px;border-radius:999px;background:#d7ddef;cursor:pointer}
        .sg-dots span.on{background:var(--azul);width:22px;border-radius:999px}
        .sg-msg{margin:16px 0 12px;color:var(--texto);font-weight:600}
        .sg-wa{display:inline-flex;align-items:center;gap:8px;background:#25D366;color:#fff;padding:10px 18px;border-radius:999px;font-weight:700;font-size:14px}
        .sg-sub{margin:-8px 0 16px;color:var(--gris);font-size:13.5px}
        .sg-group-title{text-align:left;color:var(--azul-osc);font-size:12.5px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;margin:0 0 9px}
        .sg-group-title.alt{color:#c04d80;margin-top:18px}
        .sg-grid{display:grid;gap:10px}
        .sg-grid-3{grid-template-columns:repeat(3,1fr)}
        .sg-grid-2{grid-template-columns:repeat(2,1fr)}
        @media(max-width:460px){.sg-grid-3{grid-template-columns:repeat(2,1fr)}}
        .sg-card{display:flex;flex-direction:column;align-items:center;gap:7px;background:#fff;border:1px solid var(--borde);border-radius:12px;padding:13px 8px;text-align:center;transition:border-color .12s,box-shadow .12s}
        .sg-card:hover{border-color:var(--azul);box-shadow:0 4px 12px rgba(74,163,223,.16)}
        .sg-card.nino{border-color:#f4c0d1}
        .sg-card.nino:hover{border-color:#d4537e;box-shadow:0 4px 12px rgba(212,83,126,.16)}
        .sg-cpill{background:linear-gradient(135deg,var(--azul),var(--azul-osc));color:#fff;font-weight:800;font-size:13.5px;border-radius:999px;padding:5px 15px}
        .sg-cpill.alt{background:linear-gradient(135deg,#e87ba6,#d4537e)}
        .sg-crango{color:var(--gris);font-size:12px;font-weight:600}
        .sg-help{background:var(--azul-claro);border-radius:12px;padding:12px 14px;color:var(--texto);font-size:13px;margin:16px 0 12px}
        .sg-list{display:grid;gap:9px}
        @media(min-width:560px){.sg-list{grid-template-columns:1fr 1fr}}
        .sg-row{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,#eef4ff,#f4eeff);border:1px solid #e7ddff;border-radius:14px;padding:10px 12px}
        .sg-pill{flex:none;min-width:92px;text-align:center;background:linear-gradient(135deg,#8fb4f2,#a99bf0);color:#fff;font-weight:800;border-radius:10px;padding:7px 10px;font-size:14px}
        .sg-pill.alt{background:linear-gradient(135deg,#f6a5c8,#c79bf0);min-width:104px}
        .sg-range{font-weight:700;color:var(--texto);font-size:14.5px}
        .sg-esp-title{margin:16px 0 8px;font-weight:800;color:#b06aa8}
        .sg-row{cursor:pointer;transition:border-color .12s, box-shadow .12s}
        .sg-row:hover{border-color:#c3aefb}
        .sg-row.sel{border-color:var(--azul);box-shadow:0 0 0 2px rgba(74,163,223,.25)}
        .sg-more{margin-left:auto;font-size:12px;color:var(--azul-osc);font-weight:800;white-space:nowrap}
        .sg-panel{margin-top:14px;background:#fff;border:1px solid var(--borde);border-radius:14px;padding:14px;text-align:left}
        .sg-panel .h{font-weight:800;margin-bottom:10px;color:var(--azul-osc)}
        .sg-prod{display:flex;gap:12px;align-items:center;background:#f8fbff;border:1px solid var(--borde);border-radius:12px;padding:9px 10px;margin-bottom:8px}
        .sg-prod img{width:48px;height:48px;object-fit:contain;background:var(--azul-claro);border-radius:8px;padding:3px;flex:none}
        .sg-prod .n{font-weight:700;font-size:14px;line-height:1.25}
        .sg-prod .d{font-size:12.5px;color:var(--gris);margin-top:2px}
        .sg-prod .go{margin-left:auto;color:var(--teal-osc);font-weight:800;font-size:13px;white-space:nowrap}
        .sg-none{font-size:13.5px;color:var(--gris);background:#f8fbff;border:1px dashed var(--borde);border-radius:10px;padding:10px}
        .oferta-bubble{position:absolute;top:10px;left:10px;z-index:3;background:linear-gradient(135deg,#ff8a80,#e5695f);color:#fff;font-weight:800;font-size:12.5px;padding:7px 13px;border-radius:999px;box-shadow:0 4px 12px rgba(229,105,95,.45);transform:rotate(-7deg);letter-spacing:.3px}
        .pcard .img{position:relative}
        .gal-main .oferta-bubble{top:12px;left:12px;font-size:13.5px}
        .precio-antes{color:var(--gris);text-decoration:line-through;font-weight:600;margin-right:8px;font-size:.82em}
        /* ===== Botón modo noche ===== */
        .theme-btn{background:none;border:1px solid var(--borde);border-radius:999px;width:40px;height:40px;font-size:18px;cursor:pointer;display:grid;place-items:center;flex:none;line-height:1}
        .theme-btn:hover{border-color:var(--azul)}
        .theme-btn .th-sun{display:none}
        html.dark .theme-btn .th-moon{display:none}
        html.dark .theme-btn .th-sun{display:inline}
        /* ===== MODO NOCHE ===== */
        html.dark{
            --azul:#5cb0e6;--azul-osc:#9ccff2;--azul-claro:#16273f;
            --teal:#3cc7c0;--teal-osc:#63d8d1;
            --coral:#ff9a90;--coral-osc:#ffb2aa;
            --texto:#e7eef6;--gris:#9db0c2;
            --borde:#2a3547;--ok:#43c07f;--fondo:#0e1420;
            --sombra:0 6px 18px rgba(0,0,0,.4);
        }
        html.dark body{
            background:
                radial-gradient(1100px 620px at 12% 8%, rgba(46,74,130,.35), transparent 62%),
                radial-gradient(1000px 700px at 88% 16%, rgba(70,52,116,.32), transparent 60%),
                radial-gradient(900px 650px at 78% 95%, rgba(40,80,110,.22), transparent 62%),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='130' height='130' viewBox='0 0 130 130'%3E%3Cg fill='%23ffffff' opacity='0.5'%3E%3Ccircle cx='16' cy='22' r='1.4'/%3E%3Ccircle cx='48' cy='64' r='1'/%3E%3Ccircle cx='84' cy='30' r='1.7'/%3E%3Ccircle cx='104' cy='86' r='1.1'/%3E%3Ccircle cx='30' cy='100' r='1'/%3E%3Ccircle cx='68' cy='112' r='1.3'/%3E%3Ccircle cx='116' cy='48' r='1'/%3E%3Ccircle cx='58' cy='16' r='.9'/%3E%3C/g%3E%3Cg fill='%23ffd98a' opacity='0.55'%3E%3Ccircle cx='95' cy='102' r='3.4'/%3E%3C/g%3E%3C/svg%3E"),
                linear-gradient(180deg,#0e1420 0%,#111a2b 55%,#0e1622 100%);
            background-attachment:fixed;
        }
        html.dark .hero{background:transparent !important;border-bottom:1px solid rgba(255,255,255,.08) !important}
        html.dark .header,
        html.dark .ham-menu,
        html.dark .drawer-head,
        html.dark .drawer-foot,
        html.dark .buy-sticky{background:#121b2a;border-color:var(--borde)}
        html.dark .footer{background:rgba(18,27,42,.85);border-color:var(--borde)}
        html.dark .pcard,
        html.dark .btn-carrito,
        html.dark .ham-btn,
        html.dark .item,
        html.dark .qtybox button,
        html.dark .pago-op,
        html.dark .sg-card,
        html.dark .sg-prod,
        html.dark .sg-panel,
        html.dark .sg-arrow,
        html.dark .faq-item,
        html.dark .gracias-card,
        html.dark .cat-chip,
        html.dark .pill-i,
        html.dark .buscador,
        html.dark .btn-linea,
        html.dark input,
        html.dark select,
        html.dark textarea{background:#16202f;color:var(--texto);border-color:var(--borde)}
        html.dark .trust,
        html.dark .infobar{background:#182338;border:1px solid var(--borde)}
        html.dark .warn{background:#2a1e1e;border-color:#5a3a36}
        html.dark .faq-garantia{background:#14261c;border-color:#2f5a3f}
        html.dark .envio-gratis-bar{background:linear-gradient(135deg,#14261c,#132339);border-color:var(--borde)}
        html.dark .egb-track{background:#233043}
        html.dark .buscador-x{background:#233043;color:var(--gris)}
        html.dark .sg-wrap{background:rgba(20,30,48,.78);border-color:var(--borde)}
        html.dark .sg-slide{background:linear-gradient(135deg,#16233a,#1e1838)}
        html.dark .sg-dots span{background:#33405a}
        html.dark .sg-none{background:#16202f;border-color:var(--borde)}
        html.dark .drawer{background:var(--fondo)}
        html.dark .conf-card,
        html.dark .resena-card,
        html.dark .trk-card,
        html.dark .gbtn-more{background:#16202f;color:var(--texto);border-color:var(--borde)}
        html.dark .trk-line{background:#233043}
        html.dark .trk-dot{background:#16202f;border-color:#233043;color:var(--gris)}
        html.dark .trk-estado{background:#182338;border-color:var(--borde)}
        html.dark .cat-cta{background:linear-gradient(135deg,#16273f,#1e1838);border-color:var(--borde)}
        html.dark .gracias-resumen{background:#182338}
    </style>
</head>
<body>
<div x-data>
    {{-- Header --}}
    @php
        $catsConProductos = \App\Models\Product::where('active', true)->whereNotNull('categoria')->distinct()->pluck('categoria')->all();
        $menuCats = \App\Models\Product::categoriasTienda($catsConProductos);
    @endphp
    <header class="header" x-data="{ menu: false }">
        <div class="contenedor header-inner">
            <div style="display:flex;align-items:center;gap:10px">
                <button class="ham-btn" @click="menu = !menu" aria-label="Menú">☰</button>
                <a href="/" class="logo">
                    <span class="logo-badge">👶</span>
                    <span class="marca">Baby-<span>Confort</span><small>Bienestar para tu bebé</small></span>
                </a>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <button class="theme-btn" onclick="bcToggleTheme()" aria-label="Cambiar modo día/noche" title="Modo día/noche">
                    <span class="th-moon">🌙</span><span class="th-sun">☀️</span>
                </button>
                <button class="btn-carrito" @click="$store.cart.abierto = true">
                    🛒 Carrito
                    <span class="badge" x-show="$store.cart.cantidadTotal() > 0" x-text="$store.cart.cantidadTotal()"></span>
                </button>
            </div>
        </div>
        <div class="ham-menu" x-show="menu" x-transition @click.away="menu = false" style="display:none">
            <div class="contenedor">
                <a href="{{ route('store.index') }}">🏠 Todo el catálogo</a>
                @foreach($menuCats as $c)
                    <a href="{{ route('store.categoria', $c->slug) }}">{{ $c->icono }} {{ $c->nombre }}</a>
                @endforeach
                <a href="{{ route('store.rastreo.guia') }}">📦 Rastrea tu pedido</a>
            </div>
        </div>
    </header>

    @yield('content')

    <footer class="footer">
        <div class="contenedor"><b>Baby-Confort</b> · Pedidos por WhatsApp · El Salvador 🇸🇻<br>Atención personalizada para coordinar tu entrega.
            <div class="footer-links">
                <a href="{{ route('store.rastreo.guia') }}">📦 Rastrea tu pedido</a>
                <a href="{{ route('store.nosotros') }}">Nosotros</a>
                <a href="{{ route('store.devoluciones') }}">Cambios y devoluciones</a>
                <a href="{{ route('store.privacidad') }}">Privacidad</a>
            </div>
        </div>
    </footer>

    {{-- Carrito / checkout (en todas las páginas) --}}
    <template x-if="$store.cart.abierto">
        <div class="overlay" @click="$store.cart.abierto = false">
            <aside class="drawer" @click.stop x-data="{ get c(){ return $store.cart } }">
                <div class="drawer-head">
                    <h3 x-text="c.paso === 'carrito' ? 'Tu carrito 🛒' : 'Tus datos 📝'"></h3>
                    <button class="cerrar" @click="c.cerrar()">×</button>
                </div>

                <template x-if="c.items.length === 0">
                    <div class="drawer-body"><div class="vacio"><div class="emoji">🍼</div><p>Tu carrito está vacío.</p>
                        <button class="btn btn-linea" @click="c.abierto=false">Ver productos</button></div></div>
                </template>

                <template x-if="c.items.length > 0 && c.paso === 'carrito'">
                    <div style="display:flex;flex-direction:column;flex:1;overflow:hidden">
                        <div class="drawer-body">
                            <template x-if="c.gratisDesde > 0">
                                <div class="envio-gratis-bar">
                                    <div class="egb-msg" x-show="c.faltaGratis() > 0">
                                        🚚 Te faltan <b x-text="c.money(c.faltaGratis())"></b> para <b>envío gratis</b>
                                    </div>
                                    <div class="egb-msg egb-ok" x-show="c.faltaGratis() === 0">
                                        🎉 ¡Felicidades! Tienes <b>envío gratis</b>
                                    </div>
                                    <div class="egb-track"><div class="egb-fill" :style="'width:'+c.progresoGratis()+'%'"></div></div>
                                </div>
                            </template>
                            <template x-for="i in c.items" :key="i.key">
                                <div class="item">
                                    <img :src="i.imagen" :alt="i.nombre">
                                    <div style="flex:1">
                                        <div class="nom" x-text="i.nombre"></div>
                                        <div class="meta">Talla: <span x-text="i.talla"></span> · <span x-text="c.money(i.precio)"></span> c/u
                                            <template x-if="i.combo"><span> · combo <span x-text="i.combo.cantidad"></span>x<span x-text="c.money(i.combo.precio)"></span></span></template>
                                        </div>
                                        <div class="lineabajo">
                                            <div class="qtybox"><button @click="c.cambiar(i.key,-1)">−</button><span x-text="i.cantidad"></span><button @click="c.cambiar(i.key,1)">+</button></div>
                                            <b x-text="c.money(c.subtotal(i))"></b>
                                        </div>
                                        <button class="quitar" @click="c.quitar(i.key)">Quitar</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="drawer-foot">
                            <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--gris);margin-bottom:4px"><span>Productos</span><span x-text="c.money(c.totalProductos())"></span></div>
                            <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--gris);margin-bottom:8px"><span>Envío</span><span x-text="c.envioEfectivo() === 0 ? '¡Gratis! 🎉' : c.money(c.envioEfectivo())"></span></div>
                            <div class="cupon-box">
                                <template x-if="!c.cupon">
                                    <div class="cupon-row">
                                        <input class="cupon-input" x-model="c.cuponInput" placeholder="🎟️ ¿Tienes un cupón?" @keydown.enter.prevent="c.aplicarCupon()">
                                        <button class="cupon-btn" @click="c.aplicarCupon()" :disabled="c.cuponCargando"><span x-text="c.cuponCargando ? '…' : 'Aplicar'"></span></button>
                                    </div>
                                </template>
                                <template x-if="c.cupon">
                                    <div class="cupon-ok"><span>🎟️ <b x-text="c.cupon.codigo"></b> · −<span x-text="c.cupon.porcentaje"></span>%</span><button class="cupon-quitar" @click="c.quitarCupon()">Quitar</button></div>
                                </template>
                                <div class="cupon-error" x-show="c.cuponError" x-text="c.cuponError"></div>
                            </div>
                            <template x-if="c.cupon">
                                <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--teal-osc);font-weight:700;margin-bottom:8px"><span>Descuento (<span x-text="c.cupon.porcentaje"></span>%)</span><span x-text="'− ' + c.money(c.descuento())"></span></div>
                            </template>
                            <div class="total-fila"><span>Total</span><b x-text="c.money(c.totalFinal())"></b></div>
                            <button class="btn btn-coral" @click="c.irADatos()">Continuar con el pedido →</button>
                            <button class="btn btn-linea" style="margin-top:8px" @click="c.abierto=false">← Seguir comprando</button>
                        </div>
                    </div>
                </template>

                <template x-if="c.items.length > 0 && c.paso === 'datos'">
                    <div style="display:flex;flex-direction:column;flex:1;overflow:hidden">
                        <div class="drawer-body"><div class="form-grid">
                            <div class="campo"><label>Nombre completo *</label><input x-model="c.cliente.customer_name" placeholder="Ej: Lupe de López"></div>
                            <div class="campo"><label>Teléfono / WhatsApp *</label><input x-model="c.cliente.phone" inputmode="tel" placeholder="Ej: 7777-7777"></div>
                            <div class="campo"><label>Municipio *</label><input x-model="c.cliente.municipio" placeholder="Ej: San Vicente, San Vicente"></div>
                            <div class="campo"><label>Dirección exacta *</label><textarea rows="3" x-model="c.cliente.address" placeholder="Colonia, calle, casa, referencia"></textarea></div>
                            <div class="campo"><label>Forma de pago</label><div class="pago-ops">
                                <template x-for="(label,k) in c.pagos" :key="k">
                                    <label class="pago-op" :class="c.cliente.payment===k?'sel':''"><input type="radio" name="pago" :value="k" x-model="c.cliente.payment"><span x-text="label"></span></label>
                                </template>
                            </div></div>
                            <template x-if="c.cliente.payment==='link'"><div class="aviso">Te enviaremos el link de pago por WhatsApp al confirmar. 💳</div></template>
                            <div class="campo"><label>Código de vendedor <span style="color:var(--gris);font-weight:400">(opcional)</span></label><input x-model="c.cliente.revendedor" placeholder="Si alguien te atendió, escribe su código" style="text-transform:uppercase"></div>
                            <template x-if="c.error"><div class="error" x-text="c.error"></div></template>
                        </div></div>
                        <div class="drawer-foot">
                            <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--gris);margin-bottom:4px"><span>Productos</span><span x-text="c.money(c.totalProductos())"></span></div>
                            <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--gris);margin-bottom:8px"><span>Envío</span><span x-text="c.envioEfectivo() === 0 ? '¡Gratis! 🎉' : c.money(c.envioEfectivo())"></span></div>
                            <template x-if="c.cupon">
                                <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--teal-osc);font-weight:700;margin-bottom:8px"><span>Descuento <span x-text="c.cupon.codigo"></span> (<span x-text="c.cupon.porcentaje"></span>%)</span><span x-text="'− ' + c.money(c.descuento())"></span></div>
                            </template>
                            <div class="total-fila"><span>Total a pagar</span><b x-text="c.money(c.totalFinal())"></b></div>
                            <button class="btn btn-verde" @click="c.confirmar()" :disabled="c.enviando"><span x-text="c.enviando?'Enviando…':'Confirmar y enviar pedido ✅'"></span></button>
                            <button class="btn btn-linea" style="margin-top:8px" @click="c.paso='carrito'">← Volver al carrito</button>
                        </div>
                    </div>
                </template>
            </aside>
        </div>
    </template>

    <a class="wa-float" href="https://wa.me/{{ config('babyconfort.whatsapp') }}?text=Hola%2C%20tengo%20una%20duda%20sobre%20sus%20productos%20%F0%9F%91%B6" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp">
        <span class="tip">¿Dudas? Escríbenos 💬</span>
        <svg viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01C17.18 3.03 14.69 2 12.04 2zm5.8 14.03c-.24.68-1.2 1.25-1.97 1.42-.53.11-1.22.2-3.56-.76-2.99-1.24-4.91-4.28-5.06-4.48-.15-.2-1.21-1.61-1.21-3.07 0-1.46.77-2.18 1.04-2.48.27-.3.59-.37.79-.37.2 0 .39 0 .56.01.18.01.42-.07.66.5.24.58.82 2 .89 2.14.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.18-.31.4-.44.53-.15.15-.3.31-.13.6.17.3.76 1.25 1.63 2.03 1.12 1 2.07 1.31 2.37 1.46.3.15.47.12.64-.07.17-.2.73-.85.93-1.14.2-.3.4-.25.66-.15.27.1 1.71.81 2 .96.3.15.5.22.57.35.07.12.07.72-.17 1.4z"/></svg>
    </a>
</div>

<script>
function bcToggleTheme(){
    var h = document.documentElement;
    h.classList.toggle('dark');
    try { localStorage.setItem('bc_theme', h.classList.contains('dark') ? 'dark' : 'light'); } catch(e){}
}
</script>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('cart', {
        items: JSON.parse(localStorage.getItem('bc_cart') || '[]'),
        envio: (window.BC_ENVIO ?? 2.5),
        gratisDesde: (window.BC_ENVIO_GRATIS ?? 0),
        abierto: false, paso: 'carrito', enviando: false, error: '',
        cupon: JSON.parse(localStorage.getItem('bc_cupon') || 'null'), cuponInput: '', cuponError: '', cuponCargando: false,
        pagos: { transferencia:'Transferencia bancaria', efectivo:'Efectivo (contra entrega)', link:'Link de pago' },
        cliente: { customer_name:'', phone:'', municipio:'', address:'', payment:'transferencia', revendedor:'' },

        save(){ localStorage.setItem('bc_cart', JSON.stringify(this.items)); },
        money(n){ return '$' + Number(n||0).toFixed(2); },

        agregar(item){ // {id, talla, cantidad, nombre, imagen, precio, combo}
            const key = item.id + '|' + item.talla;
            const ex = this.items.find(i => i.key === key);
            if (ex) ex.cantidad += item.cantidad;
            else this.items.push({ ...item, key });
            this.save(); this.abierto = true; this.paso = 'carrito';
            if (window.fbq) fbq('track','AddToCart',{content_type:'product',content_ids:[String(item.id)],content_name:item.nombre,value:Number((item.precio*Math.max(1,item.cantidad)).toFixed(2)),currency:'USD'});
        },
        cambiar(key,d){ const i=this.items.find(x=>x.key===key); if(!i)return; i.cantidad+=d; if(i.cantidad<=0)this.quitar(key); else this.save(); },
        quitar(key){ this.items=this.items.filter(i=>i.key!==key); this.save(); },
        subtotal(i){ if(i.combo && i.combo.cantidad>0){ const g=Math.floor(i.cantidad/i.combo.cantidad), r=i.cantidad%i.combo.cantidad; return g*i.combo.precio + r*i.precio; } return i.cantidad*i.precio; },
        totalProductos(){ return this.items.reduce((s,i)=>s+this.subtotal(i),0); },
        envioEfectivo(){ const g=Number(this.gratisDesde||0); if(g>0 && this.totalProductos()>=g) return 0; return Number(this.envio||0); },
        faltaGratis(){ const g=Number(this.gratisDesde||0); if(g<=0) return 0; return Math.max(0, g - this.totalProductos()); },
        progresoGratis(){ const g=Number(this.gratisDesde||0); if(g<=0) return 0; return Math.min(100, (this.totalProductos()/g)*100); },
        descuento(){ if(!this.cupon) return 0; return this.totalProductos() * (Number(this.cupon.porcentaje||0)/100); },
        async aplicarCupon(){
            this.cuponError='';
            const code=(this.cuponInput||'').trim();
            if(!code){ this.cuponError='Escribe un código.'; return; }
            this.cuponCargando=true;
            try{
                const res=await fetch('/cupon/validar?codigo='+encodeURIComponent(code),{headers:{'Accept':'application/json'}});
                const data=await res.json();
                if(!data.ok){ this.cupon=null; this.cuponError=data.error||'Cupón no válido.'; this.cuponCargando=false; return; }
                this.cupon={codigo:data.codigo, porcentaje:data.porcentaje};
                this.cuponInput=data.codigo; this.cuponCargando=false;
                try{ localStorage.setItem('bc_cupon', JSON.stringify(this.cupon)); }catch(e){}
            }catch(e){ this.cuponError='No se pudo validar. Revisa tu conexión.'; this.cuponCargando=false; }
        },
        quitarCupon(){ this.cupon=null; this.cuponInput=''; this.cuponError=''; try{ localStorage.removeItem('bc_cupon'); }catch(e){} },
        irADatos(){
            if (window.fbq) fbq('track','InitiateCheckout',{value:Number(this.totalFinal().toFixed(2)),currency:'USD',num_items:this.cantidadTotal(),content_ids:this.items.map(i=>String(i.id))});
            this.paso='datos';
        },
        totalFinal(){ return Math.max(0, this.totalProductos() - this.descuento()) + this.envioEfectivo(); },
        cantidadTotal(){ return this.items.reduce((s,i)=>s+i.cantidad,0); },
        cerrar(){ this.abierto=false; this.paso='carrito'; this.error=''; },

        async confirmar(){
            this.error='';
            const c=this.cliente;
            if(!c.customer_name.trim()||!c.phone.trim()||!c.municipio.trim()||!c.address.trim()){ this.error='Completa todos los campos obligatorios (*).'; return; }
            this.enviando=true;
            try{
                const token=document.querySelector('meta[name="csrf-token"]').content;
                const res=await fetch('/pedido',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':token,'Accept':'application/json'},
                    body:JSON.stringify({...c, cupon:(this.cupon?this.cupon.codigo:null), items:this.items.map(i=>({product_id:i.id,size:i.talla,cantidad:i.cantidad}))})});
                const data=await res.json();
                if(!res.ok||!data.ok){ this.error=data.error||'Ocurrió un error.'; this.enviando=false; return; }
                if(window.fbq){ fbq('track','Purchase',{value:Number(this.totalFinal().toFixed(2)),currency:'USD',num_items:this.cantidadTotal(),content_type:'product',content_ids:this.items.map(i=>String(i.id))}); }
                this.items=[]; this.save(); this.enviando=false;
                this.cupon=null; this.cuponInput=''; try{ localStorage.removeItem('bc_cupon'); }catch(e){}
                window.location.href = '/gracias/' + data.folio;
            }catch(e){ this.error='No se pudo enviar. Revisa tu conexión.'; this.enviando=false; }
        },
    });
});
</script>
</body>
</html>

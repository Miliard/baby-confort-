<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Baby-Confort | Pañales y calzoncitos para bebé')</title>
    <script>window.BC_ENVIO = {{ (float) ($envio ?? 2.5) }};</script>
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
        .spill:disabled{opacity:.4;cursor:not-allowed;text-decoration:line-through}
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
        .error{font-size:12.5px;color:var(--coral-osc)}
        .footer{border-top:1px solid var(--borde);background:#fff;padding:28px 0;margin-top:20px;color:var(--gris);font-size:14px}
        .footer b{color:var(--texto)}
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
    </style>
</head>
<body>
<div x-data>
    {{-- Header --}}
    <header class="header">
        <div class="contenedor header-inner">
            <a href="/" class="logo">
                <span class="logo-badge">👶</span>
                <span class="marca">Baby-<span>Confort</span><small>Bienestar para tu bebé</small></span>
            </a>
            <button class="btn-carrito" @click="$store.cart.abierto = true">
                🛒 Carrito
                <span class="badge" x-show="$store.cart.cantidadTotal() > 0" x-text="$store.cart.cantidadTotal()"></span>
            </button>
        </div>
    </header>

    @yield('content')

    <footer class="footer">
        <div class="contenedor"><b>Baby-Confort</b> · Pedidos por WhatsApp · El Salvador 🇸🇻<br>Atención personalizada para coordinar tu entrega.</div>
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
                            <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--gris);margin-bottom:8px"><span>Envío</span><span x-text="c.money(c.envio)"></span></div>
                            <div class="total-fila"><span>Total</span><b x-text="c.money(c.totalFinal())"></b></div>
                            <button class="btn btn-coral" @click="c.paso='datos'">Continuar con el pedido →</button>
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
                            <template x-if="c.error"><div class="error" x-text="c.error"></div></template>
                        </div></div>
                        <div class="drawer-foot">
                            <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--gris);margin-bottom:4px"><span>Productos</span><span x-text="c.money(c.totalProductos())"></span></div>
                            <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--gris);margin-bottom:8px"><span>Envío</span><span x-text="c.money(c.envio)"></span></div>
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
document.addEventListener('alpine:init', () => {
    Alpine.store('cart', {
        items: JSON.parse(localStorage.getItem('bc_cart') || '[]'),
        envio: (window.BC_ENVIO ?? 2.5),
        abierto: false, paso: 'carrito', enviando: false, error: '',
        pagos: { transferencia:'Transferencia bancaria', efectivo:'Efectivo (contra entrega)', link:'Link de pago' },
        cliente: { customer_name:'', phone:'', municipio:'', address:'', payment:'transferencia' },

        save(){ localStorage.setItem('bc_cart', JSON.stringify(this.items)); },
        money(n){ return '$' + Number(n||0).toFixed(2); },

        agregar(item){ // {id, talla, cantidad, nombre, imagen, precio, combo}
            const key = item.id + '|' + item.talla;
            const ex = this.items.find(i => i.key === key);
            if (ex) ex.cantidad += item.cantidad;
            else this.items.push({ ...item, key });
            this.save(); this.abierto = true; this.paso = 'carrito';
        },
        cambiar(key,d){ const i=this.items.find(x=>x.key===key); if(!i)return; i.cantidad+=d; if(i.cantidad<=0)this.quitar(key); else this.save(); },
        quitar(key){ this.items=this.items.filter(i=>i.key!==key); this.save(); },
        subtotal(i){ if(i.combo && i.combo.cantidad>0){ const g=Math.floor(i.cantidad/i.combo.cantidad), r=i.cantidad%i.combo.cantidad; return g*i.combo.precio + r*i.precio; } return i.cantidad*i.precio; },
        totalProductos(){ return this.items.reduce((s,i)=>s+this.subtotal(i),0); },
        totalFinal(){ return this.totalProductos() + Number(this.envio||0); },
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
                    body:JSON.stringify({...c, items:this.items.map(i=>({product_id:i.id,size:i.talla,cantidad:i.cantidad}))})});
                const data=await res.json();
                if(!res.ok||!data.ok){ this.error=data.error||'Ocurrió un error.'; this.enviando=false; return; }
                window.open(data.whatsapp_url,'_blank');
                this.items=[]; this.save(); this.enviando=false; this.cerrar();
                alert('¡Pedido enviado! Te contactaremos por WhatsApp para coordinar la entrega. 💙');
            }catch(e){ this.error='No se pudo enviar. Revisa tu conexión.'; this.enviando=false; }
        },
    });
});
</script>
</body>
</html>

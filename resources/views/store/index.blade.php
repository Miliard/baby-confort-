@extends('layouts.store')

@section('content')
@php
    // Preparamos los datos para el carrito (JavaScript)
    $productosJs = $products->map(function ($p) {
        return [
            'id'          => $p->id,
            'nombre'      => $p->name,
            'precio'      => (float) $p->price,
            'combo'       => $p->combo_qty ? ['cantidad' => (int) $p->combo_qty, 'precio' => (float) $p->combo_price] : null,
            'imagen'      => $p->image,
        ];
    })->values();
@endphp

<div x-data="tienda(@js($productosJs))">

    <header class="header">
        <div class="contenedor header-inner">
            <a href="/" class="logo">
                <span class="logo-badge">👶</span>
                <span class="marca">Baby-<span>Confort</span>
                    <small>Bienestar para tu bebé</small>
                </span>
            </a>
            <button class="btn-carrito" @click="abierto = true">
                🛒 Carrito
                <span class="badge" x-show="cantidadTotal() > 0" x-text="cantidadTotal()"></span>
            </button>
        </div>
    </header>

    <section class="hero">
        <div class="contenedor">
            <h1>Todo para el confort de tu bebé 👶</h1>
            <p>Pañales y calzoncitos premium, suaves y de alta absorción. Haz tu
               pedido en minutos y recíbelo en la puerta de tu casa.</p>
            <div class="pills">
                <span class="pill">✅ Alta absorción</span>
                <span class="pill">🚚 Entrega en El Salvador</span>
                <span class="pill">💳 Transferencia · Efectivo · Link</span>
            </div>
        </div>
    </section>

    <main class="contenedor">
        <h2 class="seccion-titulo">Nuestros productos</h2>
        <p class="seccion-sub">Elige talla y cantidad, agrégalos al carrito y listo.</p>

        <div class="grid">
            @foreach ($products as $p)
                @php
                    $sizes = $p->sizes->map(fn ($s) => ['size' => $s->size, 'quantity' => (int) $s->quantity])->values();
                    $hayStock = $p->sizes->sum('quantity') > 0 || $p->sizes->count() === 0;
                @endphp
                <div class="card" x-data="{ talla: '{{ $p->sizes->first()->size ?? '' }}', cantidad: 1 }">
                    <div class="card-img">
                        <img src="{{ $p->image }}" alt="{{ $p->name }}" loading="lazy">
                    </div>
                    <div class="card-body">
                        <div class="card-marca">{{ $p->brand }}</div>
                        <div class="card-nombre">{{ $p->name }}</div>
                        <div class="card-desc">{{ $p->description }}</div>

                        <div class="card-precio">
                            <b>${{ number_format($p->price, 2) }}</b>
                            <span style="color:var(--gris);font-size:13px">c/paquete</span>
                        </div>

                        @if ($p->combo_qty)
                            <div class="card-combo">
                                🎉 Combo: {{ $p->combo_qty }} por ${{ number_format($p->combo_price, 2) }}
                            </div>
                        @endif

                        <div class="campo">
                            <label>Talla</label>
                            <select x-model="talla">
                                @foreach ($p->sizes as $s)
                                    <option value="{{ $s->size }}" @if($s->quantity <= 0) disabled @endif>
                                        {{ $s->size }}
                                        @if ($s->quantity > 0) ({{ $s->quantity }} disponibles) @else (agotado) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="fila-cant">
                            <div class="campo" style="max-width:110px">
                                <label>Cantidad</label>
                                <input type="number" min="1" x-model.number="cantidad">
                            </div>
                            <button class="btn btn-azul"
                                @click="agregar({{ $p->id }}, talla, Math.max(1, cantidad))">
                                Agregar 🛒
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </main>

    <footer class="footer">
        <div class="contenedor">
            <b>Baby-Confort</b> · Pedidos por WhatsApp · El Salvador 🇸🇻<br>
            Atención personalizada para coordinar tu entrega.
        </div>
    </footer>

    {{-- ================= CARRITO / CHECKOUT ================= --}}
    <template x-if="abierto">
        <div class="overlay" @click="abierto = false">
            <aside class="drawer" @click.stop>
                <div class="drawer-head">
                    <h3 x-text="paso === 'carrito' ? 'Tu carrito 🛒' : 'Tus datos 📝'"></h3>
                    <button class="cerrar" @click="cerrar()">×</button>
                </div>

                {{-- Carrito vacío --}}
                <template x-if="items.length === 0">
                    <div class="drawer-body">
                        <div class="vacio">
                            <div class="emoji">🍼</div>
                            <p>Tu carrito está vacío.</p>
                            <button class="btn btn-linea" @click="abierto = false">Ver productos</button>
                        </div>
                    </div>
                </template>

                {{-- Paso 1: lista de productos --}}
                <template x-if="items.length > 0 && paso === 'carrito'">
                    <div style="display:flex;flex-direction:column;flex:1;overflow:hidden">
                        <div class="drawer-body">
                            <template x-for="i in items" :key="i.key">
                                <div class="item">
                                    <img :src="i.imagen" :alt="i.nombre">
                                    <div style="flex:1">
                                        <div class="nom" x-text="i.nombre"></div>
                                        <div class="meta">
                                            Talla: <span x-text="i.talla"></span> ·
                                            $<span x-text="i.precio.toFixed(2)"></span> c/u
                                            <template x-if="i.combo">
                                                <span> · combo <span x-text="i.combo.cantidad"></span>x$<span x-text="i.combo.precio.toFixed(2)"></span></span>
                                            </template>
                                        </div>
                                        <div class="lineabajo">
                                            <div class="qtybox">
                                                <button @click="cambiar(i.key, -1)">−</button>
                                                <span x-text="i.cantidad"></span>
                                                <button @click="cambiar(i.key, 1)">+</button>
                                            </div>
                                            <b>$<span x-text="subtotal(i).toFixed(2)"></span></b>
                                        </div>
                                        <button class="quitar" @click="quitar(i.key)">Quitar</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="drawer-foot">
                            <div class="total-fila"><span>Total</span><b>$<span x-text="total().toFixed(2)"></span></b></div>
                            <button class="btn btn-coral" @click="paso = 'datos'">Continuar con el pedido →</button>
                        </div>
                    </div>
                </template>

                {{-- Paso 2: datos del cliente --}}
                <template x-if="items.length > 0 && paso === 'datos'">
                    <div style="display:flex;flex-direction:column;flex:1;overflow:hidden">
                        <div class="drawer-body">
                            <div class="form-grid">
                                <div class="campo">
                                    <label>Nombre completo <span class="req">*</span></label>
                                    <input x-model="cliente.customer_name" placeholder="Ej: María López">
                                </div>
                                <div class="campo">
                                    <label>Teléfono / WhatsApp <span class="req">*</span></label>
                                    <input x-model="cliente.phone" inputmode="tel" placeholder="Ej: 7777-7777">
                                </div>
                                <div class="campo">
                                    <label>Municipio <span class="req">*</span></label>
                                    <input x-model="cliente.municipio" placeholder="Ej: San Salvador, Soyapango, Santa Ana...">
                                </div>
                                <div class="campo">
                                    <label>Dirección de entrega <span class="req">*</span></label>
                                    <textarea rows="3" x-model="cliente.address" placeholder="Colonia, calle, casa, punto de referencia"></textarea>
                                </div>
                                <div class="campo">
                                    <label>Forma de pago</label>
                                    <div class="pago-ops">
                                        <template x-for="(label, k) in pagos" :key="k">
                                            <label class="pago-op" :class="cliente.payment === k ? 'sel' : ''">
                                                <input type="radio" name="pago" :value="k" x-model="cliente.payment">
                                                <span x-text="label"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                                <template x-if="cliente.payment === 'link'">
                                    <div class="aviso">Te enviaremos el link de pago por WhatsApp al confirmar tu pedido. 💳</div>
                                </template>
                                <template x-if="error">
                                    <div class="error" x-text="error"></div>
                                </template>
                            </div>
                        </div>
                        <div class="drawer-foot">
                            <div class="total-fila"><span>Total a pagar</span><b>$<span x-text="total().toFixed(2)"></span></b></div>
                            <button class="btn btn-verde" @click="confirmar()" :disabled="enviando">
                                <span x-text="enviando ? 'Enviando…' : 'Confirmar y enviar pedido ✅'"></span>
                            </button>
                            <button class="btn btn-linea" style="margin-top:8px" @click="paso = 'carrito'">← Volver al carrito</button>
                        </div>
                    </div>
                </template>
            </aside>
        </div>
    </template>
</div>

<script>
function tienda(productos) {
    return {
        productos: productos,
        items: [],
        abierto: false,
        paso: 'carrito',
        enviando: false,
        error: '',
        pagos: {
            transferencia: 'Transferencia bancaria',
            efectivo: 'Efectivo (contra entrega)',
            link: 'Link de pago',
        },
        cliente: { customer_name: '', phone: '', municipio: '', address: '', payment: 'transferencia' },

        agregar(id, talla, cantidad) {
            if (!talla) { alert('Elige una talla.'); return; }
            const prod = this.productos.find(p => p.id === id);
            if (!prod) return;
            const key = id + '|' + talla;
            const existe = this.items.find(i => i.key === key);
            if (existe) {
                existe.cantidad += cantidad;
            } else {
                this.items.push({
                    key, id, talla, cantidad,
                    nombre: prod.nombre, precio: prod.precio,
                    combo: prod.combo, imagen: prod.imagen,
                });
            }
            this.abierto = true;
            this.paso = 'carrito';
        },
        cambiar(key, delta) {
            const i = this.items.find(x => x.key === key);
            if (!i) return;
            i.cantidad += delta;
            if (i.cantidad <= 0) this.quitar(key);
        },
        quitar(key) { this.items = this.items.filter(i => i.key !== key); },
        subtotal(i) {
            if (i.combo && i.combo.cantidad > 0) {
                const grupos = Math.floor(i.cantidad / i.combo.cantidad);
                const resto = i.cantidad % i.combo.cantidad;
                return grupos * i.combo.precio + resto * i.precio;
            }
            return i.cantidad * i.precio;
        },
        total() { return this.items.reduce((s, i) => s + this.subtotal(i), 0); },
        cantidadTotal() { return this.items.reduce((s, i) => s + i.cantidad, 0); },
        cerrar() { this.abierto = false; this.paso = 'carrito'; this.error = ''; },

        async confirmar() {
            this.error = '';
            const c = this.cliente;
            if (!c.customer_name.trim() || !c.phone.trim() || !c.municipio.trim() || !c.address.trim()) {
                this.error = 'Por favor completa todos los campos obligatorios (*).';
                return;
            }
            this.enviando = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const res = await fetch('/pedido', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        ...c,
                        items: this.items.map(i => ({ product_id: i.id, size: i.talla, cantidad: i.cantidad })),
                    }),
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    this.error = data.error || 'Ocurrió un error. Revisa los datos e intenta de nuevo.';
                    this.enviando = false;
                    return;
                }
                // Abrir WhatsApp con el pedido
                window.open(data.whatsapp_url, '_blank');
                // Limpiar
                this.items = [];
                this.enviando = false;
                this.cerrar();
                alert('¡Pedido enviado! Te contactaremos por WhatsApp para coordinar la entrega. 💙');
            } catch (e) {
                this.error = 'No se pudo enviar. Revisa tu conexión e intenta de nuevo.';
                this.enviando = false;
            }
        },
    };
}
</script>
@endsection

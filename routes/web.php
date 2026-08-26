<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

// Tienda: catálogo
Route::get('/', [StoreController::class, 'index'])->name('store.index');

// Página individual de un producto
Route::get('/producto/{product}', [StoreController::class, 'show'])->name('store.show');

// Colección por talla (todos los productos en esa talla)
Route::get('/talla/{talla}', [StoreController::class, 'talla'])->name('store.talla');

// Categoría (menú hamburguesa)
Route::get('/categoria/{cat}', [StoreController::class, 'categoria'])->name('store.categoria');

// Rastreo genérico por número de guía (para clientes que escriben directo)
Route::get('/rastreo', [StoreController::class, 'rastreoGuia'])->name('store.rastreo.guia');

// Página de seguimiento del pedido (barra de progreso)
Route::get('/rastreo/{order}', [StoreController::class, 'rastreo'])->name('store.rastreo');

// Página de "¡Gracias por tu pedido!" (post-compra)
Route::get('/gracias/{order}', [StoreController::class, 'gracias'])->name('store.gracias');

// Páginas de confianza
Route::get('/nosotros', [StoreController::class, 'nosotros'])->name('store.nosotros');
Route::get('/devoluciones', [StoreController::class, 'devoluciones'])->name('store.devoluciones');
Route::get('/privacidad', [StoreController::class, 'privacidad'])->name('store.privacidad');

// Mapa del sitio para buscadores (SEO)
Route::get('/sitemap.xml', [StoreController::class, 'sitemap'])->name('store.sitemap');
Route::get('/mensajes.json', [StoreController::class, 'mensajes'])->name('store.mensajes');

// Hoja imprimible de la remuneración (el navegador la guarda como PDF).
// Solo para quien esté dentro del panel.
Route::get('/remuneracion/imprimir', function (\Illuminate\Http\Request $request) {
    $desde = $request->query('desde') ?: null;
    $hasta = $request->query('hasta') ?: null;

    $m = \App\Models\CierreDia::remuneracion(
        $desde,
        $hasta,
        (float) ($request->query('comision') ?: 2.5),
        (float) ($request->query('porEnvio') ?: 3.40),
    );

    $periodo = ($desde || $hasta)
        ? 'Del ' . ($desde ? \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') : 'inicio')
          . ' al ' . ($hasta ? \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y') : 'hoy')
        : 'Todo el historial';

    return view('remuneracion-imprimir', compact('m', 'periodo'));
})->middleware(['web', 'auth'])->name('remuneracion.imprimir');

// Foto de vista previa para WhatsApp/Facebook, servida desde nuestro dominio.
Route::get('/og/producto/{product}/{talla?}', [\App\Http\Controllers\OgImageController::class, 'producto'])
    ->where('talla', '.*')->name('store.og');

// Fotos de paquetes (solo con sesión iniciada en el panel)
Route::middleware('auth')->group(function () {
    // Página de respaldo: formulario simple, sin JavaScript. Si la subida
    // normal se traba, esta siempre debería funcionar.
    Route::get('/subir-foto', [\App\Http\Controllers\GuiaFotoController::class, 'formularioSimple'])->name('fotos.simple');
    Route::post('/subir-foto', [\App\Http\Controllers\GuiaFotoController::class, 'guardarSimple'])->name('fotos.simple.guardar');

    // Borra fotos vacías, vacía el registro de errores y aplica la retención.
    Route::post('/liberar-espacio', [\App\Http\Controllers\GuiaFotoController::class, 'liberar'])->name('fotos.liberar');
    Route::post('/dias-fotos', [\App\Http\Controllers\GuiaFotoController::class, 'cambiarDias'])->name('fotos.dias');

    Route::post('/fotos-paquete', [\App\Http\Controllers\GuiaFotoController::class, 'subir'])->name('fotos.subir');
    Route::post('/guias-pdf', [\App\Http\Controllers\GuiaFotoController::class, 'importarPdf'])->name('guias.pdf');
    Route::delete('/fotos-paquete/{foto}', [\App\Http\Controllers\GuiaFotoController::class, 'eliminar'])->name('fotos.eliminar');
});

// Valida un cupón (devuelve el % de descuento si es válido).
// throttle: máx. 30 intentos por minuto por IP (evita adivinar códigos por fuerza bruta).
Route::get('/cupon/validar', [StoreController::class, 'validarCupon'])
    ->middleware('throttle:30,1')->name('store.cupon');

// Recibe el pedido del carrito (JSON) y devuelve el link de WhatsApp.
// throttle: máx. 10 pedidos por minuto por IP (evita pedidos basura automatizados).
Route::post('/pedido', [OrderController::class, 'store'])
    ->middleware('throttle:10,1')->name('order.store');

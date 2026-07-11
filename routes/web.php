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

// Valida un cupón (devuelve el % de descuento si es válido)
Route::get('/cupon/validar', [StoreController::class, 'validarCupon'])->name('store.cupon');

// Recibe el pedido del carrito (JSON) y devuelve el link de WhatsApp
Route::post('/pedido', [OrderController::class, 'store'])->name('order.store');

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

// Recibe el pedido del carrito (JSON) y devuelve el link de WhatsApp
Route::post('/pedido', [OrderController::class, 'store'])->name('order.store');

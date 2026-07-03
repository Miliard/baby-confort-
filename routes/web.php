<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

// Tienda pública
Route::get('/', [StoreController::class, 'index'])->name('store.index');

// Recibe el pedido del carrito (JSON) y devuelve el link de WhatsApp
Route::post('/pedido', [OrderController::class, 'store'])->name('order.store');

// El panel de administrador vive en /admin (lo maneja Filament).

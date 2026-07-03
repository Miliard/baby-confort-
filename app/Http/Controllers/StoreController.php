<?php

namespace App\Http\Controllers;

use App\Models\Product;

class StoreController extends Controller
{
    public function index()
    {
        // Solo productos activos, con sus tallas
        $products = Product::with('sizes')
            ->where('active', true)
            ->orderBy('id')
            ->get();

        return view('store.index', compact('products'));
    }
}

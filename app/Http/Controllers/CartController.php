<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Resource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request)
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $cart->load('items.resource');

        return view('cart.show', compact('cart'));
    }

    public function store(Request $request, Resource $resource): RedirectResponse
    {
        abort_unless($resource->status === 'published' && $resource->visibility === 'public', 404);
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $cart->items()->firstOrCreate(['resource_id' => $resource->id]);

        return back()->with('success', 'Ressource ajoutée au panier.');
    }

    public function destroy(Request $request, Resource $resource): RedirectResponse
    {
        $request->user()->cart?->items()->where('resource_id', $resource->id)->delete();

        return back()->with('success', 'Ressource retirée du panier.');
    }
}

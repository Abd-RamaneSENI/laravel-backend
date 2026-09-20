<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $order = DB::transaction(function () use ($request) {
            $cart = $request->user()->cart()->with('items.resource')->lockForUpdate()->firstOrFail();
            $resources = $cart->items->pluck('resource')->filter(fn ($resource) => $resource->status === 'published' && $resource->visibility === 'public');

            if ($resources->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Votre panier est vide ou contient des ressources qui ne sont plus disponibles. Ajoutez une ressource au catalogue avant de créer la commande.',
                ]);
            }

            $subtotal = $resources->sum('price');
            $order = Order::create([
                'user_id' => $request->user()->id,
                'reference' => 'CMD-'.strtoupper(Str::random(14)),
                'subtotal' => $subtotal,
                'discount' => 0,
                'total' => $subtotal,
                'currency' => config('payments.currency'),
                'status' => 'pending',
            ]);

            foreach ($resources as $resource) {
                $order->items()->create([
                    'resource_id' => $resource->id,
                    'title_snapshot' => $resource->title,
                    'unit_price' => $resource->price,
                    'quantity' => 1,
                    'total' => $resource->price,
                ]);
            }

            $cart->items()->delete();

            return $order;
        });

        return redirect()->route('orders.show', $order);
    }
}
